<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/FinanceController.php
 * @description 会员中心：余额概览、充值、资金流水、发票管理
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

namespace App\Controllers\Member;

use App\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Enums\Status;
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
    /**
     * 余额概览
     */
    public function index(): never
    {
        $this->requireLogin();
        $this->memberView('member/finance', [
            'title'   => '余额与充值',
            'account' => $this->account(),
            'flows'   => FundFlowModel::search($this->userId(), -1, 8, 0)['rows'],
            'pending' => (int) RechargeOrderModel::userSearch($this->userId(), 1, 0)['total'],
            'invoiceEnabled' => $this->invoiceEnabled,
        ], 'finance', '余额与充值');
    }

    /**
     * 充值页
     */
    public function recharge(): never
    {
        $this->requireLogin();
        $page = $this->pageParams();
        $config = AdminFinanceController::rechargeConfig();
        // 超时单的实际关闭在写操作（发起支付/创建充值单）中完成；此处只读，倒计时由 remain_seconds 呈现（规范 §5.5.3 禁止 GET 触发写操作）
        $result = RechargeOrderModel::userSearch($this->userId(), $page['limit'], $page['offset'], $config['expire_minutes']);
        $data = [
            'title'       => '余额充值',
            'account'     => $this->account(),
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page['page'],
            'pages'       => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'minAmount'   => (float) SettingsModel::get('min_recharge_amount', '100'),
            'config'      => $config,
            'onlineMethods' => self::availableOnlineMethods($config),
        ];
        // 翻页只回统计卡与记录表两块区域，不整页刷新（规范 §2.5）
        if ($this->isPartial()) {
            $this->renderTable('member/recharge_region', $data);
        }
        $this->memberView('member/recharge', $data, 'finance', '余额充值');
    }

    /**
     * 可选的在线支付方式（网关已开通且后台已勾选的方式，键为本地支付方式编码）
     * @return array<int, string>
     */
    public static function availableOnlineMethods(array $config): array
    {
        if (!$config['gateway_ready']) {
            return [];
        }
        $methods = [];
        foreach (EpayService::enabledTypes() as $code => $type) {
            $methods[$code] = EpayService::TYPES[$type];
        }
        return $methods;
    }

    /**
     * 发起充值：生成待支付单，付款方式由后台「充值设置」决定
     */
    public function createRecharge(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $amount = round((float) Request::input('amount', 0), 2);
        $payMethod = (int) Request::input('pay_method', 0);
        $min = (float) SettingsModel::get('min_recharge_amount', '100');
        $config = AdminFinanceController::rechargeConfig();

        if ($amount < $min) {
            $this->fail('单笔充值不得低于 ¥' . number_format($min, 2));
        }
        if ($amount > 500000) {
            $this->fail('单笔充值不得超过 ¥500,000.00，大额请分批或联系客服');
        }
        // 只接受后台已启用的支付方式：在线网关渠道或人工入账（银行转账）
        $allowed = array_keys(self::availableOnlineMethods($config));
        if ($config['manual_enabled'] === '1') {
            $allowed[] = 5;
        }
        if (!in_array($payMethod, $allowed, true)) {
            $this->fail('该支付方式未开放，请重新选择');
        }
        if ($config['custom_enabled'] === '0' && !in_array((int) $amount, array_map('intval', $config['amounts_list']), true)) {
            $this->fail('请从预设档位中选择充值金额');
        }

        // 同一用户待支付单过多时限制，避免刷单（先清掉本人超时单，避免过期单占额度）
        RechargeOrderModel::closeExpired($config['expire_minutes'], $this->userId());
        $pending = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('recharge_orders') . ' WHERE user_id = :uid AND status IN (0, 5)'
        );
        $pending->execute([':uid' => $this->userId()]);
        if ((int) $pending->fetchColumn() >= 3) {
            $this->fail('您有多个待处理充值单，请先完成支付或取消后再发起');
        }

        try {
            $id = RechargeOrderModel::createForUser(
                $this->userId(),
                $amount,
                $payMethod,
                trim((string) Request::input('remark', ''))
            );
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        // 返回支付指引，供前端弹窗展示（在线渠道给跳转/扫码参数，人工入账给收款信息）
        $order = RechargeOrderModel::findById($id) ?? [];
        $expireMinutes = $config['expire_minutes'];
        $this->ok([
            'id'             => $id,
            'order_no'       => (string) ($order['order_no'] ?? ''),
            'amount'         => $amount,
            'pay_method'     => $payMethod,
            'method_name'    => Status::payMethod($payMethod),
            'is_manual'      => $payMethod === 5,
            'is_online'      => in_array($payMethod, array_keys(EpayService::CODE_TO_TYPE), true),
            'manual_info'    => $payMethod === 5 ? $config['manual_info'] : '',
            'expire_seconds' => $expireMinutes * 60,
        ], '充值单已创建');
    }

    /**
     * 向网关发起支付：返回 pay_type 与 pay_info，由前端跳转或渲染二维码
     */
    public function payOrder(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $config = AdminFinanceController::rechargeConfig();
        // 有效时长已过的本人单先行关闭，避免对过期单发起支付
        RechargeOrderModel::closeExpired($config['expire_minutes'], $this->userId());
        $order = $this->loadPayableOrder((int) Request::input('id', 0), $config['expire_minutes']);
        $type = EpayService::CODE_TO_TYPE[(int) $order['pay_method']] ?? '';
        if ($type === '') {
            $this->fail('该支付方式需线下付款，请按收款信息转账后点击「我已完成支付」');
        }
        if (!in_array($type, EpayService::enabledTypes(), true)) {
            $this->fail('该支付方式未开放，请重新选择');
        }

        try {
            $result = EpayService::create(
                [
                    'order_no'     => (string) $order['order_no'],
                    'amount'       => (float) $order['amount'],
                    'channel_code' => $type,
                    'name'         => '余额充值 ' . $order['order_no'],
                ],
                self::device(),
                EpayService::url('/pay/notify/epay'),
                EpayService::url('/pay/return/epay'),
                Request::ip()
            );
        } catch (RuntimeException $e) {
            Logger::write('pay', [
                'event'  => 'epay_create_fail',
                'order'  => $order['order_no'],
                'reason' => $e->getMessage(),
            ], 'warning');
            $this->fail($e->getMessage());
        }

        RechargeOrderModel::attachGateway((int) $order['id'], $result['trade_no'], $result['pay_type'], '');
        Logger::write('pay', [
            'event'    => 'epay_create_ok',
            'order'    => $order['order_no'],
            'trade_no' => $result['trade_no'],
            'pay_type' => $result['pay_type'],
            'mode'     => EpayService::config()['mode'],
        ]);
        $this->ok([
            'pay_type' => $result['pay_type'],
            'pay_info' => $result['pay_info'],
            'order_no' => $order['order_no'],
            'amount'   => (float) $order['amount'],
            'remain'   => (int) ($order['remain_seconds'] ?? 0),
        ], '已创建支付请求');
    }

    /**
     * 支付状态查询：用户端实时监测用；sync=1 时顺带向网关核对一次（降频调用，避免高频打网关）
     * 返回状态、金额与剩余有效秒数，前端据此倒计时并在到时时提示已关闭
     *
     * 在线渠道靠网关回执翻转，线下人工入账渠道靠管理员核实入账翻转——两者最终都落到
     * status=1，故本接口对渠道不做区分，人工单同样可被前端轮询到账（pending 用于让前端
     * 把「已声明付款、等待核实」与「尚未付款」两种等待态呈现为不同文案）
     */
    public function payStatus(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $expireMinutes = AdminFinanceController::expireMinutes();
        // 先清理本人超时单，让前端实时看到「已关闭」而不是一直停在待支付
        RechargeOrderModel::closeExpired($expireMinutes, $this->userId());
        $order = RechargeOrderModel::findForUser($id, $this->userId(), $expireMinutes);
        if ($order === null) {
            $this->fail('充值单不存在');
        }
        $sync = (int) Request::input('sync', 0) === 1;
        if ($sync && (int) $order['status'] === 0
            && (string) ($order['gateway'] ?? '') === 'epay'
            && (string) ($order['gateway_trade_no'] ?? '') !== '') {
            try {
                RechargeService::syncFromGateway($id);
            } catch (Throwable $e) {
                // 查询失败不打断轮询，前端按本地状态继续展示
                Logger::write('pay', ['event' => 'epay_query_fail', 'order' => $order['order_no'], 'reason' => $e->getMessage()], 'warning');
            }
            $order = RechargeOrderModel::findForUser($id, $this->userId(), $expireMinutes);
        }
        $status = (int) ($order['status'] ?? 0);
        $this->ok([
            'id'         => $id,
            'status'     => $status,
            'text'       => Status::recharge($status),
            'paid'       => $status === 1,
            'pending'    => $status === 5,
            'closed'     => in_array($status, [3, 4], true),
            'pay_method' => (int) ($order['pay_method'] ?? 0),
            'amount'     => (float) ($order['amount'] ?? 0),
            // 剩余秒数由 MySQL 计算，避免 PHP 与数据库时区不一致导致倒计时偏移
            'remain'     => (int) ($order['remain_seconds'] ?? 0),
            // 一并回传最新余额，前端局部更新统计卡，避免整页刷新
            'balance'    => (float) $this->account()['balance'],
        ]);
    }

    /**
     * 取本人待支付充值单（附带剩余有效秒数）；不存在或状态已变化时给出可读提示
     */
    private function loadPayableOrder(int $id, int $expireMinutes): array
    {
        $order = RechargeOrderModel::findForUser($id, $this->userId(), $expireMinutes);
        if ($order === null) {
            $this->fail('充值单不存在');
        }
        if ((int) $order['status'] !== 0) {
            $this->fail('该充值单当前状态为「' . Status::recharge((int) $order['status']) . '」，无法发起支付');
        }
        return $order;
    }

    /**
     * 网关 device 取值：按浏览器 UA 判断，用于选择跳转、扫码等发起方式
     */
    private static function device(): string
    {
        $ua = strtolower((string) Request::header('User-Agent'));
        return match (true) {
            str_contains($ua, 'micromessenger') => 'wechat',
            str_contains($ua, 'alipayclient')   => 'alipay',
            str_contains($ua, 'qq/')            => 'qq',
            (bool) preg_match('/android|iphone|ipad|ipod|mobile/', $ua) => 'mobile',
            default                              => 'pc',
        };
    }

    /**
     * 用户标记「我已完成支付」→ 进入待入账，等管理员核实
     */
    public function markPaid(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        try {
            RechargeOrderModel::markPaidByUser(
                (int) Request::input('id', 0),
                $this->userId(),
                trim((string) Request::input('trade_no', ''))
            );
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '已提交付款确认，客服核实到账后将尽快入账');
    }

    public function cancelRecharge(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        try {
            RechargeOrderModel::cancelByUser((int) Request::input('id', 0), $this->userId());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '充值单已取消');
    }

    /**
     * 资金流水
     */
    public function flows(): never
    {
        $this->requireLogin();
        $page = $this->pageParams();
        $flowType = (int) Request::query('type', '-1');
        $result = FundFlowModel::search($this->userId(), $flowType, $page['limit'], $page['offset']);

        $data = [
            'title'    => '资金流水',
            'account'  => $this->account(),
            'rows'     => $result['rows'],
            'total'    => $result['total'],
            'page'     => $page['page'],
            'pages'    => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'flowType' => $flowType,
        ];
        if ($this->isPartial()) {
            $this->renderTable('member/flows_table', $data);
        }
        $this->memberView('member/flows', $data, 'flows', '资金流水');
    }

    /**
     * 发票管理
     */
    public function invoices(): never
    {
        $this->requireLogin();
        $page = $this->pageParams();
        $result = InvoiceModel::search($this->userId(), $page['limit'], $page['offset']);
        $data = [
            'title'      => '发票管理',
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page['page'],
            'pages'      => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'invoiceable' => InvoiceModel::invoiceableAmount($this->userId()),
            'enabled'    => $this->invoiceEnabled,
        ];
        // 翻页只回统计与记录区域，不整页刷新（规范 §2.5）
        if ($this->isPartial()) {
            $this->renderTable('member/invoices_region', $data);
        }
        $this->memberView('member/invoices', $data, 'invoices', '发票管理');
    }

    public function createInvoice(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();

        // 后台关闭开票功能后拒绝新申请：前端入口已隐藏，此处兜底拦截直接请求
        if (!$this->invoiceEnabled) {
            $this->fail('发票申请功能已关闭，如有开票需求请联系客服');
        }

        $title = trim((string) Request::input('title', ''));
        $taxNo = trim((string) Request::input('tax_no', ''));
        $type = (int) Request::input('type', 1);
        $amount = round((float) Request::input('amount', 0), 2);
        $email = trim((string) Request::input('email', ''));

        if ($title === '' || mb_strlen($title) > 100) {
            $this->fail('发票抬头需为 1~100 字');
        }
        if (!in_array($type, [1, 2], true)) {
            $this->fail('无效的发票类型');
        }
        if ($amount <= 0) {
            $this->fail('开票金额需大于 0');
        }
        $invoiceable = InvoiceModel::invoiceableAmount($this->userId());
        if ($amount > $invoiceable + 0.001) {
            $this->fail('开票金额不得超过可开票额度 ¥' . number_format($invoiceable, 2) . '（已扣除已申请的发票金额）');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail('接收邮箱格式不正确');
        }
        if ($type === 2 && $taxNo === '') {
            $this->fail('专用发票必须填写纳税人识别号');
        }

        try {
            InvoiceModel::create($this->userId(), [
                'title' => $title, 'tax_no' => $taxNo, 'type' => $type,
                'amount' => $amount, 'email' => $email,
                'remark' => trim((string) Request::input('remark', '')),
            ]);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '开票申请已提交，财务处理后可在列表中查看进度');
    }

    private function account(): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT balance, frozen_amount, total_recharge, total_consume
             FROM ' . Database::table('user_accounts') . ' WHERE user_id = :uid LIMIT 1'
        );
        $stmt->execute([':uid' => $this->userId()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false
            ? ['balance' => 0, 'frozen_amount' => 0, 'total_recharge' => 0, 'total_consume' => 0]
            : $row;
    }
}
