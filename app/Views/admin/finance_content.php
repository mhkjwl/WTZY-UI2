<?php declare(strict_types=1);
/**
 * @file app/Views/admin/finance_content.php
 * @description 财务中心局部内容：待处理充值（前置）→ 资金流水 → 汇率维护
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\RechargeOrderModel;

/**
 * @var array  $flows
 * @var int    $flowTotal
 * @var int    $flowPage
 * @var int    $flowPages
 * @var int    $flowType
 * @var array  $recharges
 * @var int    $rechargeTotal
 * @var int    $rechargePage
 * @var int    $rechargePages
 * @var array  $rechargeCounts
 * @var int    $reStatus
 * @var int    $expireMinutes
 * @var array  $rates
 * @var array  $perms
 * @var bool   $isSuper
 */
$flowNames = [1 => '充值', 2 => '运费支付', 3 => '退款', 4 => '仓储费', 5 => '理赔赔偿', 6 => '人工调整', 7 => '提现', 8 => '返利'];
$pendingCount = RechargeOrderModel::pendingCount($rechargeCounts);
$qs = static function (array $extra) use ($flowType, $reStatus, $flowPage, $rechargePage): string {
    return '/admin/finance?' . http_build_query(array_filter(array_merge([
        'flow_type' => $flowType, 'page' => $flowPage,
        're_status' => $reStatus, 're_page' => $rechargePage,
    ], $extra), static fn(int $v): bool => $v !== -1));
};
$flowQs = static fn(int $p): string => $qs(['page' => $p]);
$reQs = static fn(int $p): string => $qs(['re_page' => $p]);
$payMethods = [1 => '支付宝', 2 => '微信', 3 => 'PayPal', 4 => '信用卡', 5 => '银行转账', 6 => '后台人工'];
?>
<!-- ── 待处理充值（前置区）────────────────────────── -->
<h3 class="section-head"><span class="badge badge--green">充值单</span>
    <?php if ($pendingCount > 0): ?><span class="badge badge--orange">待处理 <?= (int) $pendingCount ?> 笔</span><?php endif; ?>
    <span class="rule"></span>
    <select class="form-input" style="min-width:130px" id="f-restatus" onchange="financeFilter()">
        <option value="-1">全部状态</option>
        <?php foreach (Status::RECHARGE as $k => $v): ?><option value="<?= $k ?>" <?= $reStatus === $k ? 'selected' : '' ?>><?= View::e($v) ?></option><?php endforeach; ?>
    </select>
</h3>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>充值单号</th><th>用户</th><th>金额</th><th>支付方式</th><th>状态</th><th>第三方流水号</th><th>时间</th><th class="nowrap">操作</th></tr></thead>
    <tbody>
    <?php if ($recharges === []): ?><tr><td colspan="8"><div class="empty-state">暂无充值单。用户端发起充值或银行转账登记后在此处理。</div></td></tr><?php endif; ?>
    <?php foreach ($recharges as $r): ?>
        <tr>
            <td class="mono"><b><?= View::e($r['order_no']) ?></b></td>
            <td><?= View::e($r['user_no'] ?: '—') ?> <small><?= View::e($r['nickname'] ?: '') ?></small></td>
            <td class="nowrap">¥<?= number_format((float) $r['amount'], 2) ?></td>
            <td><?= View::e($payMethods[(int) $r['pay_method']] ?? '—') ?></td>
            <td><span class="badge badge--<?= Status::rechargeTone((int) $r['status']) ?>"><?= View::e(Status::recharge((int) $r['status'])) ?></span></td>
            <td class="mono"><?= View::e($r['trade_no'] ?: '—') ?></td>
            <td class="nowrap"><?= View::e($r['created_at']) ?></td>
            <td class="nowrap">
                <?php
                $rst = (int) $r['status'];
                $canManage = !empty($perms['finance.manage']);
                $amountText = number_format((float) $r['amount'], 2);
                // 网关单：已下单且拿到网关流水号，才可主动向网关核对状态
                $isGateway = (string) ($r['gateway'] ?? '') === 'epay' && (string) ($r['gateway_trade_no'] ?? '') !== '';
                // 同步用于通知丢失时的人工兜底；结算允许 待支付(0)/已关闭(4)/待入账(5)，
                // 覆盖「超时自动关单后用户实际完成付款」这一情况
                $canSync = $canManage && $isGateway && in_array($rst, [0, 4, 5], true);
                // 可退金额 = 充值金额 − 已退金额；仅已支付的网关单可发起在线退款
                $refundable = round((float) $r['amount'] - (float) $r['refund_money'], 2);
                $canRefund = $canManage && $rst === 1 && $isGateway && $refundable > 0;
                ?>
                <?php if (!$canManage): ?>
                    <span class="msg-item__read">—</span>
                <?php elseif ($rst === 0 || $rst === 5): ?>
                    <button class="btn btn--primary btn--sm" onclick="admAction('/admin/finance/recharge/confirm', {id: <?= (int) $r['id'] ?>}, '确认已收到该笔充值 ¥<?= $amountText ?> 并入账吗？', {confirmText: '确认入账', title: '确认入账'})">入账</button>
                    <?php if ($canSync): ?>
                        <button class="btn btn--ghost btn--sm" onclick="admAction('/admin/finance/recharge/sync', {id: <?= (int) $r['id'] ?>}, '向支付平台查询该充值单的最新状态并按结果入账吗？', {confirmText: '同步状态', title: '同步网关状态', danger: false})">同步</button>
                    <?php endif; ?>
                    <button class="btn btn--ghost btn--sm" onclick="admAction('/admin/finance/recharge/close', {id: <?= (int) $r['id'] ?>}, '<?= $rst === 5 ? '该单为用户自行声明的「已付款」。请先核实款项是否实际到账，确认未到账再关闭；关闭后该单作废，用户需重新提交。确定关闭吗？' : '确定关闭该充值单吗？关闭后用户将无法再支付。' ?>', {confirmText: '确认关闭', title: '关闭充值单'})">关闭</button>
                <?php elseif ($rst === 1): ?>
                    <span class="badge badge--green">已入账</span>
                    <?php if ($canRefund): ?>
                        <button class="btn btn--ghost btn--sm" onclick='openRefund(<?= (int) $r['id'] ?>, <?= json_encode((string) $r['order_no'], JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?= $refundable ?>)'>退款</button>
                    <?php endif; ?>
                    <?php if ((float) $r['refund_money'] > 0): ?>
                        <br><small class="form-hint">已退 ¥<?= number_format((float) $r['refund_money'], 2) ?></small>
                    <?php endif; ?>
                <?php elseif ($rst === 4 && $canSync): ?>
                    <button class="btn btn--ghost btn--sm" onclick="admAction('/admin/finance/recharge/sync', {id: <?= (int) $r['id'] ?>}, '该单已超时关闭，但用户可能已完成付款。向支付平台查询并按结果入账吗？', {confirmText: '同步状态', title: '同步网关状态', danger: false})">同步</button>
                <?php elseif ((float) $r['refund_money'] > 0): ?>
                    <small class="form-hint">已退 ¥<?= number_format((float) $r['refund_money'], 2) ?></small>
                <?php else: ?>
                    <span class="msg-item__read">—</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($rechargePages > 1): ?>
    <nav class="pager">
        <?php for ($i = max(1, $rechargePage - 2); $i <= min($rechargePages, $rechargePage + 2); $i++): ?>
            <a class="<?= $i === $rechargePage ? 'is-current' : '' ?>" href="javascript:admTable('<?= View::e($reQs($i)) ?>')"><?= $i ?></a>
        <?php endfor; ?>
        <span class="is-disabled">共 <?= (int) $rechargeTotal ?> 条</span>
    </nav>
<?php endif; ?>

<!-- ── 资金流水 ─────────────────────────────────────── -->
<h3 class="section-head" style="margin-top:24px"><span class="badge badge--blue">资金流水</span><span class="rule"></span>
    <select class="form-input" style="min-width:130px" id="f-flow" onchange="financeFilter()">
        <option value="0">全部类型</option>
        <?php foreach ($flowNames as $k => $v): ?><option value="<?= $k ?>" <?= $flowType === $k ? 'selected' : '' ?>><?= View::e($v) ?></option><?php endforeach; ?>
    </select>
</h3>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>流水号</th><th>用户</th><th>类型</th><th>方向</th><th>金额</th><th>变动前 → 后</th><th>关联单号</th><th>时间</th></tr></thead>
    <tbody>
    <?php if ($flows === []): ?><tr><td colspan="8"><div class="empty-state">暂无资金流水。</div></td></tr><?php endif; ?>
    <?php foreach ($flows as $f): ?>
        <tr>
            <td class="mono"><?= View::e($f['flow_no']) ?></td>
            <td><?= View::e($f['user_no'] ?: '—') ?> <small><?= View::e($f['nickname'] ?: '') ?></small></td>
            <td><?= View::e($flowNames[(int) $f['flow_type']] ?? '未知') ?></td>
            <td><span class="badge badge--<?= (int) $f['direction'] === 1 ? 'green' : 'red' ?>"><?= (int) $f['direction'] === 1 ? '收入' : '支出' ?></span></td>
            <td class="nowrap"><?= (int) $f['direction'] === 1 ? '+' : '-' ?>¥<?= number_format((float) $f['amount'], 2) ?></td>
            <td class="mono nowrap"><?= number_format((float) $f['balance_before'], 2) ?> → <?= number_format((float) $f['balance_after'], 2) ?></td>
            <td class="mono"><?= View::e($f['biz_no'] ?: '—') ?></td>
            <td class="nowrap"><?= View::e($f['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($flowPages > 1): ?>
    <nav class="pager">
        <?php for ($i = max(1, $flowPage - 2); $i <= min($flowPages, $flowPage + 2); $i++): ?>
            <a class="<?= $i === $flowPage ? 'is-current' : '' ?>" href="javascript:admTable('<?= View::e($flowQs($i)) ?>')"><?= $i ?></a>
        <?php endfor; ?>
        <span class="is-disabled">共 <?= (int) $flowTotal ?> 条</span>
    </nav>
<?php endif; ?>

<!-- ── 汇率 ────────────────────────────────────────── -->
<h3 class="section-head" style="margin-top:24px"><span class="badge badge--violet">汇率</span><span class="rule"></span></h3>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>基准币种</th><th>目标币种</th><th>汇率</th><th>来源</th><th>更新时间</th></tr></thead>
    <tbody>
    <?php foreach ($rates as $rate): ?>
        <tr>
            <td class="mono"><?= View::e($rate['base_currency']) ?></td>
            <td class="mono"><?= View::e($rate['quote_currency']) ?></td>
            <td><b><?= View::e((string) $rate['rate']) ?></b></td>
            <td><?= View::e($rate['source']) ?></td>
            <td class="nowrap"><?= View::e($rate['updated_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if (!empty($perms['finance.manage'])): ?>
    <div class="adm-toolbar" style="margin-top:10px">
        <input class="form-input" id="r-base" placeholder="基准 CNY" style="width:110px">
        <input class="form-input" id="r-quote" placeholder="目标 USD" style="width:110px">
        <input class="form-input" type="number" step="0.000001" id="r-rate" placeholder="汇率" style="width:130px">
        <button class="btn btn--primary btn--sm" onclick="saveRate()">保存汇率</button>
    </div>
<?php endif; ?>
<?php if (!empty($perms['finance.manage'])): ?>
<dialog class="adm-dialog adm-dialog--sm" id="dlg-refund">
    <div class="adm-dialog__head">在线充值退款 · <span class="mono" id="rf-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="rf-id">
        <div class="form-field">
            <label class="form-label">退款金额（最多可退 <span id="rf-max"></span>）</label>
            <input class="form-input" type="number" step="0.01" min="0.01" id="rf-money">
        </div>
        <div class="form-field">
            <label class="form-label">退款说明（选填）</label>
            <input class="form-input" id="rf-remark" maxlength="200" placeholder="如：用户申请退款">
        </div>
        <p class="form-hint">退款会先向支付平台发起，成功后再从用户余额扣减并记退款流水；若余额已被消费则退款失败，需人工核对账户。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" type="button" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--danger" type="button" onclick="saveRefund()">确认退款</button>
    </div>
</dialog>
<?php endif; ?>
<script>
/** 在线充值退款：网关退款成功后系统会同步扣减用户余额并记退款流水 */
function openRefund(id, no, refundable) {
    document.getElementById('rf-id').value = id;
    document.getElementById('rf-no').textContent = no;
    var input = document.getElementById('rf-money');
    input.value = refundable.toFixed(2);
    input.max = refundable.toFixed(2);
    document.getElementById('rf-max').textContent = '¥' + refundable.toFixed(2);
    document.getElementById('rf-remark').value = '';
    document.getElementById('dlg-refund').showModal();
}
function saveRefund() {
    var money = parseFloat(val('rf-money'));
    var max = parseFloat(document.getElementById('rf-money').max);
    if (!(money > 0)) { Notify.warning('请填写大于 0 的退款金额'); return; }
    if (money > max + 0.001) { Notify.warning('退款金额不得超过可退金额 ¥' + max.toFixed(2)); return; }
    admConfirm('确定向支付平台发起 ¥' + money.toFixed(2) + ' 的退款吗？退款成功后将从该用户余额中扣减，余额不足会失败。', async function () {
        var res = await App.post('/admin/finance/recharge/refund', {
            id: val('rf-id'), money: money, remark: val('rf-remark').trim()
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-refund').close(); admRefresh(); }
    }, { title: '在线充值退款', confirmText: '确认退款', danger: true });
}
/** 两个筛选区共用同一份查询参数：切换任一筛选都回到各自第一页，不丢另一区的条件 */
function financeFilter() {
    var params = new URLSearchParams();
    var flowType = document.getElementById('f-flow') ? document.getElementById('f-flow').value : '';
    var reStatus = document.getElementById('f-restatus') ? document.getElementById('f-restatus').value : '';
    if (flowType !== '' && flowType !== '0') { params.set('flow_type', flowType); }
    if (reStatus !== '' && reStatus !== '-1') { params.set('re_status', reStatus); }
    var qs = params.toString();
    admTable('/admin/finance' + (qs ? '?' + qs : ''));
}
function saveRate() {
    var base = document.getElementById('r-base').value.trim().toUpperCase();
    var quote = document.getElementById('r-quote').value.trim().toUpperCase();
    var rate = document.getElementById('r-rate').value;
    if (!base || !quote) { Notify.warning('请填写基准币种与目标币种'); return; }
    if (!(parseFloat(rate) > 0)) { Notify.warning('请填写大于 0 的汇率'); return; }
    admConfirm('确定保存汇率 ' + base + ' → ' + quote + ' = ' + rate + ' 吗？保存后运费试算与账单将按新汇率计算。', async function () {
        var res = await App.post('/admin/finance/rate', { base_currency: base, quote_currency: quote, rate: rate });
        App.handle(res);
        if (res.success) { admRefresh(); }
    }, { title: '保存汇率', confirmText: '确认保存' });
}
</script>
