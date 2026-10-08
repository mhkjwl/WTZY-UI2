<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/WaybillController.php
 * @description 会员中心：转运下单（试算/提交）、我的运单（详情/支付/取消）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

namespace App\Controllers\Member;

use App\Core\Database;
use App\Core\Request;
use App\Models\ChannelModel;
use App\Models\FundFlowModel;
use App\Models\PackageModel;
use App\Models\UserAddressModel;
use App\Models\ValueAddedServiceModel;
use App\Models\WaybillModel;
use App\Services\FreightService;
use InvalidArgumentException;
use RuntimeException;

final class WaybillController extends BaseController
{
    public function index(): never
    {
        $this->requireLogin();
        $keyword = (string) Request::query('kw', '');
        $status = (int) Request::query('status', '-1');
        $page = $this->pageParams();
        $result = WaybillModel::userSearch($this->userId(), $keyword, $status, $page['limit'], $page['offset']);

        $data = [
            'title'   => '转运订单',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page['page'],
            'pages'   => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword' => $keyword,
            'status'  => $status,
            'counts'  => WaybillModel::userStatusCounts($this->userId()),
            'balance' => $this->balance(),
        ];
        if ($this->isPartial()) {
            $this->renderTable('member/waybills_table', $data);
        }
        $this->memberView('member/waybills', $data, 'waybills', '转运订单');
    }

    /**
     * 下单页：可转运包裹 + 收货地址 + 渠道 + 增值服务
     */
    public function create(): never
    {
        $this->requireLogin();
        $preselect = (int) Request::query('package', '0');
        $claimable = PackageModel::claimableForWaybill($this->userId());
        $this->memberView('member/waybill_create', [
            'title'      => '新建转运',
            'packages'   => $claimable['rows'],
            'packageTotal' => $claimable['total'],
            'addresses'  => UserAddressModel::listForUser($this->userId()),
            'channels'   => $this->enabledChannels(),
            'services'   => ValueAddedServiceModel::enabled(),
            'priceModes' => ValueAddedServiceModel::PRICE_MODES,
            'preselect'  => $preselect,
            'insuranceRate' => (float) \App\Models\SettingsModel::get('insurance_rate', '3'),
            'balance'    => $this->balance(),
        ], 'create', '新建转运');
    }

    /**
     * 运费试算（不落库）
     */
    public function estimate(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $result = FreightService::quote($this->quoteParams());
        if (!$result['ok']) {
            $this->fail($result['message']);
        }
        $this->ok($result['detail'], '试算完成');
    }

    /**
     * 提交转运订单：先按同一套规则重算运费，避免前端传值被篡改
     */
    public function save(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $packageIds = array_values(array_filter(array_map('intval', (array) Request::input('package_ids', []))));
        if ($packageIds === []) {
            $this->fail('请至少选择一个包裹');
        }
        if (count($packageIds) > 20) {
            $this->fail('单次最多合并 20 个包裹');
        }
        $packages = $this->ownedPackages($packageIds);

        $address = UserAddressModel::findForUser((int) Request::input('address_id', 0), $this->userId());
        if ($address === null) {
            $this->fail('请选择有效的收货地址');
        }

        $billType = (int) Request::input('bill_type', 2);
        if (count($packages) === 1 && $billType === 2) {
            $billType = 1; // 单个包裹无需合箱
        }
        if (!in_array($billType, [1, 2], true)) {
            $this->fail('暂不支持分箱转运，请选择原箱或合箱');
        }

        // 计费重量：实重与体积重取大者
        $actual = 0.0;
        $volume = 0.0;
        foreach ($packages as $p) {
            $actual += (float) ($p['weight'] ?? 0);
            $volume += (float) ($p['volume_weight'] ?? 0);
        }
        $declared = 0.0;
        $warehouseId = 0;
        foreach ($packages as $p) {
            $declared += (float) $p['declared_value'];
            $warehouseId = (int) $p['warehouse_id'];
        }
        $billable = round(max($actual, $volume), 3);
        if ($billable <= 0) {
            $this->fail('所选包裹尚未称重，请等待仓库完成称重后再提交');
        }

        $params = $this->quoteParams();
        $params['weight'] = $billable;
        $params['declared_value'] = $declared;
        $params['dest_country'] = (string) $address['country'];
        $result = FreightService::quote($params);
        if (!$result['ok']) {
            $this->fail($result['message']);
        }
        $quote = $result['detail'];
        $waybillNo = $this->nextWaybillNo();

        try {
            $waybillId = WaybillModel::createForUser($this->userId(), [
                'waybill_no'    => $waybillNo,
                'warehouse_id'  => $warehouseId,
                'bill_type'     => $billType,
                'package_ids'   => $packageIds,
                'receiver_name' => (string) $address['receiver_name'],
                'receiver_phone' => (string) $address['receiver_phone'],
                'dest_country'  => (string) $address['country'],
                'dest_address'  => [
                    'state' => $address['state'], 'city' => $address['city'], 'district' => $address['district'],
                    'address1' => $address['address1'], 'address2' => $address['address2'], 'zip_code' => $address['zip_code'],
                ],
                'declare_items' => $this->declareItems(),
                'carrier'       => (string) $quote['channel_name'],
                'actual_weight' => round($actual, 3),
                'volume_weight' => round($volume, 3),
                'billable_weight' => $billable,
                'remark'        => trim((string) Request::input('remark', '')),
            ], $quote);
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            // 余额不足时携带标记，前端引导用户先充值
            if (str_contains($msg, '余额不足')) {
                $this->fail($msg, 400, ['needs_recharge' => true]);
            }
            $this->fail($msg);
        }

        $this->ok(['id' => $waybillId, 'total' => $quote['total_amount']], '转运订单已提交并完成支付，仓库将安排打包出库');
    }

    public function detail(string $id): never
    {
        $this->requireLogin();
        $waybill = WaybillModel::findForUser((int) $id, $this->userId());
        if ($waybill === null) {
            $this->memberView('member/waybill_missing', ['title' => '运单不存在'], 'waybills', '运单详情');
        }
        $this->memberView('member/waybill_detail', [
            'title'    => '运单详情 · ' . $waybill['waybill_no'],
            'waybill'  => $waybill,
            'services' => WaybillModel::services((int) $waybill['id']),
            'packages' => WaybillModel::packages((int) $waybill['id']),
            'tracking' => WaybillModel::tracking((int) $waybill['id']),
            'logs'     => WaybillModel::statusLogs((int) $waybill['id']),
            'balance'  => $this->balance(),
            'feeDetail' => json_decode((string) ($waybill['fee_detail'] ?? '{}'), true) ?: [],
        ], 'waybills', '运单详情');
    }

    /**
     * 余额支付：涵盖「打包差额补缴」与「全额支付」两种情况。
     * 补缴（due_amount>0，即打包重算后余额不足被挂起）结清后运单转「待出库」，仓库随即可以交接；
     * 扣款、运单状态、状态日志三者同事务，避免扣了钱却没记日志
     */
    public function pay(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $waybill = WaybillModel::findForUser($id, $this->userId());
        if ($waybill === null) {
            $this->fail('运单不存在');
        }
        if ((int) $waybill['status'] !== 2) {
            $this->fail('当前状态无需支付');
        }
        // 挂起待补缴的只收差额；其余为全额支付
        $due = (float) ($waybill['due_amount'] ?? 0);
        $amount = $due > 0 ? $due : (float) $waybill['total_amount'];
        if ($amount <= 0) {
            $this->fail('运单金额异常，请联系客服');
        }

        $pdo = Database::pdo();
        try {
            FundFlowModel::record(
                $this->userId(), 2, 2, $amount, 1, (string) $waybill['waybill_no'], null,
                $due > 0 ? '补缴打包运费差额' : '支付转运运费'
            );
            if ($due > 0) {
                WaybillModel::markDuePaid($id, 1);
            } else {
                WaybillModel::markPaid($id, 1);
            }
            $pdo->prepare(
                'INSERT INTO ' . Database::table('waybill_status_logs') . '
                 (waybill_id, from_status, to_status, operator_type, remark, operator_id)
                 VALUES (:w, 2, :to, 1, :remark, :op)'
            )->execute([
                ':w'      => $id,
                ':to'     => $due > 0 ? 4 : 3,
                ':remark' => $due > 0 ? '用户余额补缴打包差额' : '用户余额支付运费',
                ':op'     => $this->userId(),
            ]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            $msg = $e->getMessage();
            // 余额不足时携带标记，前端引导用户先充值
            if (str_contains($msg, '余额不足')) {
                $this->fail($msg, 400, ['needs_recharge' => true]);
            }
            $this->fail($msg);
        }

        $this->ok(null, $due > 0 ? '差额已补缴，仓库将安排出库' : '支付成功，仓库将尽快安排出库');
    }

    public function cancel(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $reason = trim((string) Request::input('reason', ''));
        if (mb_strlen($reason) < 2) {
            $this->fail('请填写取消原因');
        }
        try {
            WaybillModel::cancelForUser((int) Request::input('id', 0), $this->userId(), $reason);
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '运单已取消，包裹已释放，运费已退回余额');
    }

    /**
     * 用户确认签收：货物到手后主动确认，签收即登记待激活积分
     */
    public function sign(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        try {
            WaybillModel::signByUser((int) Request::input('id', 0), $this->userId());
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '已确认签收，积分将在缓冲期后到账');
    }

    /**
     * 解析报关明细：至少一行有效申报（中英文品名必填），随订单落 declare_items
     */
    private function declareItems(): array
    {
        $namesCn = (array) Request::input('declare_name_cn', []);
        $namesEn = (array) Request::input('declare_name_en', []);
        $hsCodes = (array) Request::input('declare_hs_code', []);
        $qtys = (array) Request::input('declare_qty', []);
        $units = (array) Request::input('declare_unit', []);
        $prices = (array) Request::input('declare_unit_price', []);
        $currencies = (array) Request::input('declare_currency', []);
        $brands = (array) Request::input('declare_brand', []);

        $items = [];
        $count = max(count($namesCn), count($namesEn));
        for ($i = 0; $i < $count; $i++) {
            $cn = trim((string) ($namesCn[$i] ?? ''));
            $en = trim((string) ($namesEn[$i] ?? ''));
            if ($cn === '' && $en === '') {
                continue;
            }
            if ($cn === '' || $en === '') {
                throw new InvalidArgumentException('报关明细第 ' . ($i + 1) . ' 行：中文与英文品名需同时填写');
            }
            $currency = strtoupper(trim((string) ($currencies[$i] ?? 'USD')));
            if ($currency === '') {
                $currency = 'USD';
            }
            if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new InvalidArgumentException('报关明细第 ' . ($i + 1) . ' 行：币种需为三位字母（ISO 4217）');
            }
            $items[] = [
                'name_cn'    => mb_substr($cn, 0, 100),
                'name_en'    => mb_substr($en, 0, 100),
                'hs_code'    => mb_substr(trim((string) ($hsCodes[$i] ?? '')), 0, 20),
                'qty'        => max(1, (int) ($qtys[$i] ?? 1)),
                'unit'       => mb_substr(trim((string) ($units[$i] ?? '件')) ?: '件', 0, 10),
                'unit_price' => round(max(0.0, (float) ($prices[$i] ?? 0)), 2),
                'currency'   => $currency,
                'brand'      => mb_substr(trim((string) ($brands[$i] ?? '')), 0, 50),
            ];
        }
        if ($items === []) {
            throw new InvalidArgumentException('请至少填写一行报关明细（中文品名 + 英文品名）');
        }
        return $items;
    }

    /**
     * 试算入参（下单与试算共用，保证口径一致）
     */
    private function quoteParams(): array
    {
        $services = (array) Request::input('services', []);
        $clean = [];
        foreach ($services as $sid => $qty) {
            $clean[(int) $sid] = max(1, (int) $qty);
        }
        return [
            'channel_id'       => (int) Request::input('channel_id', 0),
            'dest_country'     => (string) Request::input('dest_country', ''),
            'weight'           => (float) Request::input('weight', 0),
            'declared_value'   => (float) Request::input('declared_value', 0),
            'goods_type'       => (int) Request::input('goods_type', 1),
            'services'         => $clean,
            'insurance_amount' => (float) Request::input('insurance_amount', 0),
            'member_level'     => (int) $this->user['member_level'],
        ];
    }

    /**
     * 校验包裹归属与可下单状态：仅「已认领」(4) 可下单，
     * 下单后包裹置为待打包(5) 起到锁定作用，避免重复提交
     */
    private function ownedPackages(array $ids): array
    {
        $packages = [];
        foreach ($ids as $id) {
            $row = PackageModel::findForUser($id, $this->userId());
            if ($row === null) {
                $this->fail('包裹不存在或不属于当前账号');
            }
            if ((int) $row['status'] !== 4) {
                $this->fail('包裹 ' . $row['package_no'] . ' 当前状态不可转运（需先认领，且未被其它运单占用）');
            }
            $packages[] = $row;
        }
        return $packages;
    }

    private function enabledChannels(): array
    {
        $stmt = Database::pdo()->query(
            'SELECT id, channel_code, name, carrier, goods_type, aging_desc, tax_mode
             FROM ' . Database::table('channels') . '
             WHERE deleted_at IS NULL AND status = 1 ORDER BY sort ASC, id ASC'
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['countries'] = FreightService::channelCountries((int) $row['id']);
        }
        unset($row);
        return $rows;
    }

    private function balance(): float
    {
        $stmt = Database::pdo()->prepare('SELECT IFNULL(balance, 0) FROM ' . Database::table('user_accounts') . ' WHERE user_id = :uid');
        $stmt->execute([':uid' => $this->userId()]);
        return (float) $stmt->fetchColumn();
    }

    private function nextWaybillNo(): string
    {
        $pdo = Database::pdo();
        for ($i = 0; $i < 5; $i++) {
            $no = 'W' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('waybills') . ' WHERE waybill_no = :no');
            $stmt->execute([':no' => $no]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $no;
            }
        }
        throw new RuntimeException('运单号生成失败，请稍后重试');
    }
}
