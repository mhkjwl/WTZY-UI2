<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/FinanceController.php
 * @description 财务中心：总览统计 + 资金流水 + 充值单人工处理 + 汇率维护
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

namespace App\Controllers\Admin;

use App\Core\Crypto;
use App\Core\Logger;
use App\Core\Request;
use App\Core\StaffAuth;
use App\Models\FundFlowModel;
use App\Models\InvoiceModel;
use App\Models\RechargeOrderModel;
use App\Models\SettingsModel;
use App\Services\EpayService;
use App\Services\RechargeService;
use RuntimeException;
use Throwable;

final class FinanceController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('finance.view');
        $this->renderList(
            (int) Request::query('flow_type', '0'),
            (int) Request::query('re_status', '-1'),
            max(1, (int) Request::query('re_page', '1'))
        );
    }

    private function renderList(int $flowType, int $rechargeStatus, int $rechargePage): void
    {
        // 超时单的清理在「同步网关状态」等写操作中完成，此处只读，避免 GET 触发写操作（规范 §5.5.3）
        $page = $this->pageParams();
        $flows = FundFlowModel::search(0, $flowType, $page['limit'], $page['offset']);
        $recharges = RechargeOrderModel::search($rechargeStatus, $page['limit'], ($rechargePage - 1) * $page['limit']);
        $rechargeCounts = RechargeOrderModel::statusCounts();
        $data = [
            'title'          => '财务中心',
            'overview'       => FundFlowModel::overview(),
            'flows'          => $flows['rows'],
            'flowTotal'      => $flows['total'],
            'flowPage'       => $page['page'],
            'flowPages'      => (int) ceil($flows['total'] / $page['limit']) ?: 1,
            'flowType'       => $flowType,
            'recharges'      => $recharges['rows'],
            'rechargeTotal'  => $recharges['total'],
            'rechargePage'   => $rechargePage,
            'rechargePages'  => (int) ceil($recharges['total'] / $page['limit']) ?: 1,
            'rechargeCounts' => $rechargeCounts,
            'reStatus'       => $rechargeStatus,
            'expireMinutes'  => self::expireMinutes(),
            'rates'          => SettingsModel::rates(),
            'listUrl'        => self::listUrl($flowType, $rechargeStatus, $page['page'], $rechargePage),
        ];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/finance_content', $data);
        }
        $this->adminView('admin/finance', $data, 'finance', '财务中心');
    }

    /**
     * 局部刷新地址：流水与充值单两个分页区共用同一份查询参数，互不丢条件
     */
    private static function listUrl(int $flowType, int $rechargeStatus, int $flowPage, int $rechargePage): string
    {
        $params = array_filter([
            'flow_type' => $flowType,
            'page'      => $flowPage,
            're_status' => $rechargeStatus,
            're_page'   => $rechargePage,
        ], static fn(int $v): bool => $v !== -1);
        return '/admin/finance?' . http_build_query($params);
    }

    /**
     * 充值单人工确认入账（银行转账/线下凭证场景）
     */
    public function confirmRecharge(): never
    {
        $this->requireStaff();
        $this->enforce('finance.manage');
        $this->verifyCsrf();
        try {
            RechargeOrderModel::confirmManual((int) Request::input('id', 0), StaffAuth::id());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '已确认入账，余额与流水已同步');
    }

    public function closeRecharge(): never
    {
        $this->requireStaff();
        $this->enforce('finance.manage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $order = RechargeOrderModel::findById($id);
        if ($order === null) {
            $this->fail('充值单不存在');
        }
        try {
            RechargeOrderModel::close($id);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        // 网关单同步关闭，失败不阻断本地关单（部分通道不支持关闭）
        if ((string) ($order['gateway'] ?? '') === 'epay') {
            try {
                EpayService::close((string) $order['order_no']);
            } catch (Throwable $e) {
                Logger::write('pay', ['event' => 'epay_close_fail', 'order' => $order['order_no'], 'reason' => $e->getMessage()], 'warning');
            }
        }
        $this->ok(null, '充值单已关闭');
    }

    /**
     * 向网关查询并同步充值单状态（用户已付款但回调未到时的人工补救入口）
     */
    public function syncRecharge(): never
    {
        $this->requireStaff();
        $this->enforce('finance.manage');
        $this->verifyCsrf();
        // 无计划任务：借管理员主动同步的时机顺带做一次全表清理，超时未支付单转为「已关闭」
        RechargeOrderModel::closeExpired(self::expireMinutes());
        try {
            $result = RechargeService::syncFromGateway((int) Request::input('id', 0));
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        } catch (Throwable $e) {
            $this->fail('网关查询失败：' . $e->getMessage());
        }
        if (!$result['paid']) {
            $statusText = [0 => '未支付', 2 => '已退款', 3 => '已冻结', 4 => '预授权'][$result['gateway_status']] ?? ('状态 ' . $result['gateway_status']);
            $this->ok($result, '网关侧订单状态为「' . $statusText . '」，暂无需入账');
        }
        $this->ok($result, $result['flipped'] ? '已按网关结果入账，余额与流水已同步' : '该单此前已入账，未重复记账');
    }

    /**
     * 在线充值退款：网关退款成功后扣减用户余额并记退款流水
     */
    public function refundRecharge(): never
    {
        $this->requireStaff();
        $this->enforce('finance.manage');
        $this->verifyCsrf();
        $money = round((float) Request::input('money', 0), 2);
        $remark = trim((string) Request::input('remark', ''));
        try {
            $result = RechargeService::refund((int) Request::input('id', 0), $money, $remark, StaffAuth::id());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        } catch (Throwable $e) {
            $this->fail('退款失败：' . $e->getMessage());
        }
        Logger::write('pay', [
            'event'     => 'epay_refund_ok',
            'refund_no' => $result['refund_no'],
            'money'     => $result['money'],
            'order_id'  => (int) Request::input('id', 0),
        ]);
        $this->ok($result, '退款成功，用户余额与流水已同步');
    }

    /**
     * 充值设置：充值档位、在线支付渠道（易支付）、人工入账渠道
     */
    public function rechargeSettings(): never
    {
        $this->requireStaff();
        $this->enforce('finance.manage');
        $gateway = EpayService::config();
        $this->adminView('admin/finance_recharge_settings', [
            'title'       => '充值设置',
            'config'      => self::rechargeConfig(),
            'gateway'     => $gateway,
            'epayTypes'   => EpayService::TYPES,
            'epayModes'   => EpayService::MODES,
            'notifyUrl'   => EpayService::url('/pay/notify/epay'),
            'returnUrl'   => EpayService::url('/pay/return/epay'),
        ], 'recharge', '充值设置');
    }

    /**
     * 充值渠道配置（用户端据此渲染充值方式，与后台保持同一口径）
     * @return array{amounts: string, amounts_list: array, custom_enabled: string, min_amount: string,
     *               expire_minutes: int, online_list: array, gateway_ready: bool,
     *               manual_enabled: string, manual_info: string}
     */
    public static function rechargeConfig(): array
    {
        $amounts = SettingsModel::get('recharge_amounts', '100,200,500,1000,2000');
        $online = SettingsModel::get('recharge_online_methods', '1,2');
        return [
            'amounts'        => $amounts,
            'amounts_list'   => array_values(array_filter(array_map('intval', explode(',', $amounts)), static fn(int $v): bool => $v > 0)),
            'custom_enabled' => SettingsModel::get('recharge_custom_enabled', '1'),
            'min_amount'     => SettingsModel::get('min_recharge_amount', '100'),
            'expire_minutes' => self::expireMinutes(),
            // 在线渠道只保留网关支持的方式，历史遗留的其它编码在此被过滤掉
            'online_list'    => array_values(array_filter(
                array_map('intval', explode(',', $online)),
                static fn(int $v): bool => isset(EpayService::CODE_TO_TYPE[$v])
            )),
            'gateway_ready'  => EpayService::isConfigured(),
            'manual_enabled' => SettingsModel::get('recharge_manual_enabled', '1'),
            'manual_info'    => SettingsModel::get('recharge_manual_info', ''),
        ];
    }

    /**
     * 充值单有效时长（分钟），超时未支付自动关闭；两端共用同一口径
     */
    public static function expireMinutes(): int
    {
        return max(1, (int) SettingsModel::get('recharge_expire_minutes', '3'));
    }

    public function saveRechargeSettings(): never
    {
        $this->requireStaff();
        $this->enforce('finance.manage');
        $this->verifyCsrf();

        $amountsRaw = trim((string) Request::input('recharge_amounts', ''));
        $custom = (int) Request::input('recharge_custom_enabled', 0) === 1 ? '1' : '0';
        $onlineRaw = (array) Request::input('online_methods', []);
        $manual = (int) Request::input('recharge_manual_enabled', 0) === 1 ? '1' : '0';
        $manualInfo = trim((string) Request::input('recharge_manual_info', ''));
        $minAmount = round((float) Request::input('min_recharge_amount', 0), 2);

        // 易支付网关参数
        $epayEnabled = (int) Request::input('epay_enabled', 0) === 1;
        $epayApiUrl = rtrim(trim((string) Request::input('epay_api_url', '')), '/');
        $epayPid = trim((string) Request::input('epay_pid', ''));
        $epayPrivate = trim((string) Request::input('epay_private_key', ''));
        $epayPublic = trim((string) Request::input('epay_public_key', ''));
        $epayMode = trim((string) Request::input('epay_pay_mode', 'qrcode'));
        // 充值单有效时长（分钟）：超时未支付的单自动关闭
        $expireMinutes = (int) Request::input('recharge_expire_minutes', 3);

        $amounts = [];
        foreach (explode(',', $amountsRaw) as $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }
            if (preg_match('/^\d+(\.\d{1,2})?$/', $piece) !== 1) {
                $this->fail('充值档位只能填写数字，用英文逗号分隔，如 100,200,500');
            }
            $value = round((float) $piece, 2);
            if ($value < 1 || $value > 500000) {
                $this->fail('单个充值档位需在 1~500000 之间');
            }
            $amounts[] = $value;
        }
        $amounts = array_values(array_unique($amounts));
        sort($amounts);
        if (count($amounts) > 12) {
            $this->fail('充值档位最多 12 个');
        }

        $online = [];
        foreach ($onlineRaw as $m) {
            $m = (int) $m;
            if (isset(EpayService::CODE_TO_TYPE[$m])) {
                $online[] = $m;
            }
        }
        $online = array_values(array_unique($online));
        sort($online);

        // 易支付：接口地址由用户自己的支付平台提供，未配置时留空；启用时必须填全
        if ($epayEnabled) {
            if ($epayApiUrl === '') {
                $this->fail('启用易支付时必须填写接口地址（由你的支付平台提供）');
            }
            if (preg_match('#^https?://[A-Za-z0-9.\-]+(:\d+)?(/[A-Za-z0-9._\-/]*)?$#', $epayApiUrl) !== 1) {
                $this->fail('易支付接口地址需为合法的 http(s) 地址');
            }
            if ($epayPid === '' || preg_match('/^\d+$/', $epayPid) !== 1) {
                $this->fail('易支付商户号（pid）需为纯数字');
            }
            if (!isset(EpayService::MODES[$epayMode])) {
                $this->fail('无效的支付发起方式');
            }
            if (!EpayService::config()['has_private'] && $epayPrivate === '') {
                $this->fail('启用易支付必须填写商户私钥');
            }
            if (EpayService::config()['has_public'] === false && $epayPublic === '') {
                $this->fail('启用易支付必须填写平台公钥，否则无法校验网关回调');
            }
            if ($online === []) {
                $this->fail('启用易支付时至少勾选一种在线支付方式');
            }
        }
        if ($expireMinutes < 1 || $expireMinutes > 1440) {
            $this->fail('充值单有效时长需在 1~1440 分钟之间');
        }
        if ($minAmount < 1 || $minAmount > 500000) {
            $this->fail('单笔起充金额需在 1~500000 之间');
        }
        if (!$epayEnabled && $online !== []) {
            $this->fail('在线支付方式依赖易支付网关，请先启用易支付或取消勾选');
        }
        if ($custom === '0' && $amounts === []) {
            $this->fail('关闭自定义金额时，至少配置一个充值档位');
        }
        if ($amounts !== [] && $minAmount > min($amounts)) {
            $this->fail('单笔起充金额不得高于最低充值档位 ¥' . number_format(min($amounts), 2));
        }
        if ($online === [] && $manual === '0') {
            $this->fail('至少需要启用一种支付方式（在线支付或人工入账）');
        }
        if ($manual === '1' && $manualInfo === '') {
            $this->fail('启用人工入账时请填写收款信息，否则用户无法完成付款');
        }

        $format = static fn(float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        SettingsModel::upsert('recharge_amounts', implode(',', array_map($format, $amounts)), 'pay', '充值档位');
        SettingsModel::upsert('min_recharge_amount', $format($minAmount), 'pay', '单笔起充金额');
        SettingsModel::upsert('recharge_custom_enabled', $custom, 'pay', '允许自定义充值金额');
        SettingsModel::upsert('recharge_expire_minutes', (string) $expireMinutes, 'pay', '充值单有效时长（分钟）');
        SettingsModel::upsert('recharge_online_methods', implode(',', $online), 'pay', '在线支付渠道（易支付）');
        SettingsModel::upsert('recharge_manual_enabled', $manual, 'pay', '人工入账开关');
        SettingsModel::upsert('recharge_manual_info', $manualInfo, 'pay', '人工入账收款信息');

        // 网关参数：接口地址与商户号按填写内容原样保存（未填即为空）
        SettingsModel::upsert('epay_enabled', $epayEnabled ? '1' : '0', 'pay', '易支付网关开关');
        SettingsModel::upsert('epay_api_url', $epayApiUrl, 'pay', '易支付接口地址');
        SettingsModel::upsert('epay_pid', $epayPid, 'pay', '易支付商户号 pid');
        SettingsModel::upsert('epay_pay_mode', $epayMode, 'pay', '在线支付发起方式 jump跳转 / qrcode扫码');
        if ($epayPublic !== '') {
            SettingsModel::upsert('epay_public_key', $epayPublic, 'pay', '易支付平台公钥（验签用）');
        }
        if ($epayPrivate !== '') {
            SettingsModel::upsert('epay_private_key', Crypto::encrypt($epayPrivate), 'pay', '易支付商户私钥（AES 加密存储）');
        }
        $this->ok(null, '充值设置已保存');
    }

    public function saveRate(): never
    {
        $this->requireStaff();
        $this->enforce('finance.manage');
        $this->verifyCsrf();
        $base = strtoupper(trim((string) Request::input('base_currency', '')));
        $quote = strtoupper(trim((string) Request::input('quote_currency', '')));
        $rate = (float) Request::input('rate', 0);
        if (!preg_match('/^[A-Z]{3}$/', $base) || !preg_match('/^[A-Z]{3}$/', $quote)) {
            $this->fail('币种需为三位字母（ISO 4217）');
        }
        if ($base === $quote || $rate <= 0) {
            $this->fail('请填写有效的汇率（同币对无效）');
        }
        SettingsModel::saveRate($base, $quote, $rate);
        $this->ok(null, '汇率已保存');
    }

    /**
     * 发票管理：跨用户查看开票申请并处理
     */
    public function invoices(): never
    {
        $this->requireStaff();
        $this->enforce('finance.view');
        $this->renderInvoices(
            (int) Request::query('iv_status', '-1'),
            trim((string) Request::query('iv_kw', '')),
            max(1, (int) Request::query('iv_page', '1'))
        );
    }

    private function renderInvoices(int $status, string $keyword, int $pageNo): void
    {
        $page = $this->pageParams();
        $result = InvoiceModel::adminSearch($status, $keyword, $page['limit'], ($pageNo - 1) * $page['limit']);
        $counts = InvoiceModel::statusCounts();
        $data = [
            'title'        => '发票管理',
            'rows'         => $result['rows'],
            'total'        => $result['total'],
            'page'         => $pageNo,
            'pages'        => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'ivStatus'     => $status,
            'ivKeyword'    => $keyword,
            'pendingCount' => (int) ($counts[InvoiceModel::PENDING] ?? 0),
            'issuedCount'  => (int) ($counts[InvoiceModel::ISSUED] ?? 0),
            'issuedAmount' => InvoiceModel::issuedAmount(),
            'enabled'      => self::invoiceEnabled(),
            'listUrl'      => self::invoiceListUrl($status, $keyword, $pageNo),
        ];
        // 翻页与筛选只替换数据区，不整页刷新（规范 §2.5）
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/invoices_region', $data);
        }
        $this->adminView('admin/invoices', $data, 'invoices', '发票管理');
    }

    /**
     * 局部刷新地址：状态筛选、关键词与页码共用同一份查询参数
     */
    private static function invoiceListUrl(int $status, string $keyword, int $pageNo): string
    {
        $params = [];
        if ($status >= 0) {
            $params['iv_status'] = $status;
        }
        if ($keyword !== '') {
            $params['iv_kw'] = $keyword;
        }
        if ($pageNo > 1) {
            $params['iv_page'] = $pageNo;
        }
        return '/admin/finance/invoices' . ($params === [] ? '' : '?' . http_build_query($params));
    }

    /**
     * 发票申请开关：关闭后会员端隐藏开票入口并拒绝新申请，
     * 已提交的申请不受影响（否则关闭时存量申请将无人处理）
     */
    public static function invoiceEnabled(): bool
    {
        return SettingsModel::get('invoice_enabled', '1') === '1';
    }

    /**
     * 开票申请处理：开票或驳回；驳回后该笔金额不再占用额度，用户可重新申请
     */
    public function handleInvoice(): never
    {
        $this->requireStaff();
        $this->enforce('finance.manage');
        $this->verifyCsrf();

        $id = (int) Request::input('id', 0);
        $action = (string) Request::input('result', '');
        $remark = trim((string) Request::input('remark', ''));
        if ($id <= 0) {
            $this->fail('缺少申请记录');
        }
        $status = match ($action) {
            'issue'  => InvoiceModel::ISSUED,
            'reject' => InvoiceModel::REJECTED,
            default  => -1,
        };
        if ($status < 0) {
            $this->fail('无效的处理结果');
        }
        if (mb_strlen($remark) > 500) {
            $this->fail('处理意见不得超过 500 字');
        }
        if ($status === InvoiceModel::REJECTED && $remark === '') {
            $this->fail('驳回需填写原因，用户会在开票记录中看到');
        }
        try {
            InvoiceModel::handle(
                $id,
                $status,
                $remark,
                (int) (StaffAuth::id() ?? 0),
                (string) ($this->staff['real_name'] ?? '')
            );
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, $status === InvoiceModel::ISSUED ? '已标记为已开票' : '已驳回该开票申请');
    }

    /**
     * 开关发票申请功能：仅影响会员端新申请的入口与提交，不影响已有申请的处理
     */
    public function toggleInvoice(): never
    {
        $this->requireStaff();
        $this->enforce('finance.manage');
        $this->verifyCsrf();

        $enabled = (string) Request::input('enabled', '') === '1';
        SettingsModel::upsert(
            'invoice_enabled',
            $enabled ? '1' : '0',
            'pay',
            '发票申请开关（关闭后用户端不再提供开票入口与申请提交）'
        );
        $this->ok(['enabled' => $enabled], $enabled ? '已开启发票申请' : '已关闭发票申请');
    }
}
