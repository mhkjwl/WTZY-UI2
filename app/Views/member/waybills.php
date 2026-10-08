<?php declare(strict_types=1);
/**
 * @file app/Views/member/waybills.php
 * @description 会员中心：转运订单列表（统计 + 检索 + 局部表）
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 * @var string $keyword
 * @var int   $status
 * @var array $counts
 * @var float $balance
 */
$totalAmount = 0.0;
foreach ($rows as $r) {
    $totalAmount += (float) $r['total_amount'];
}
$initialUrl = '/user/waybills?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $page],
    static fn($v): bool => $v !== '' && $v !== -1
));
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">🧾</div>
        <div><div class="astat__value"><?= array_sum(array_map('intval', $counts)) ?></div><div class="astat__label">全部运单</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) ($counts[2] ?? 0) ?></div><div class="astat__label">待补款</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">🚚</div>
        <div><div class="astat__value"><?= (int) ($counts[4] ?? 0) + (int) ($counts[5] ?? 0) + (int) ($counts[6] ?? 0) ?></div><div class="astat__label">运输中</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">💰</div>
        <div><div class="astat__value">¥<?= number_format($balance, 2) ?></div><div class="astat__label">账户余额</div></div></div>
</section>

<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="w-kw" placeholder="运单号 / 承运单号 / 收件人" value="<?= View::e($keyword) ?>">
        <select class="form-input" id="w-status">
            <option value="-1">全部状态</option>
            <?php foreach (Status::WAYBILL as $code => $label): ?>
                <option value="<?= $code ?>" <?= $status === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--primary" onclick="admSearch('/user/waybills', {kw: val('w-kw'), status: val('w-status')})">检索</button>
        <a class="btn btn--primary" style="margin-left:auto" href="/user/waybills/create">+ 新建转运</a>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('member/waybills_table', get_defined_vars()) ?>
    </div>
</section>

<script>
function payWaybill(id, no, amount, balance) {
    if (balance < amount) {
        Notify.warning('账户余额不足（¥' + balance.toFixed(2) + ' / 应付 ¥' + amount.toFixed(2) + '），请先充值');
        return;
    }
    admConfirm('将使用账户余额支付运单 ' + no + ' 共 ¥' + amount.toFixed(2) + '，确认支付吗？', async function () {
        var res = await App.post('/user/waybills/pay', { id: id });
        App.handle(res, '支付成功');
        if (res.success) { admRefresh(); }
    }, { title: '余额支付运费', confirmText: '确认支付', danger: true });
}
document.getElementById('w-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { admSearch('/user/waybills', { kw: this.value, status: val('w-status') }); }
});
</script>
