<?php declare(strict_types=1);
/**
 * @file app/Views/member/waybill_detail.php
 * @description 会员中心：运单详情（费用明细 / 服务 / 包裹 / 轨迹 / 支付与取消）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $waybill
 * @var array $services
 * @var array $packages
 * @var array $tracking
 * @var array $logs
 * @var float $balance
 * @var array $feeDetail
 */
$status = (int) $waybill['status'];
$payable = $status === 2;
$due = (float) ($waybill['due_amount'] ?? 0);
$cancellable = in_array($status, [0, 1, 2, 3], true);
$signable = in_array($status, [5, 6, 7, 8], true) && $waybill['signed_at'] === null;
$addr = json_decode((string) $waybill['dest_address'], true) ?: [];
?>
<div class="adm-toolbar">
    <a class="btn btn--ghost btn--sm" href="/user/waybills">‹ 返回列表</a>
    <span class="badge badge--<?= Status::waybillTone($status) ?>" id="wb-status-badge"><?= View::e($due > 0 ? '待补款' : Status::waybill($status)) ?></span>
    <span class="badge badge--<?= Status::clearTone((int) $waybill['clear_status']) ?>">清关：<?= View::e(Status::clear((int) $waybill['clear_status'])) ?></span>
    <?php if ($payable): ?>
        <button class="btn btn--primary btn--sm" style="margin-left:auto"
                onclick="payWaybill(<?= (int) $waybill['id'] ?>, '<?= View::e($waybill['waybill_no']) ?>', <?= $due > 0 ? $due : (float) $waybill['total_amount'] ?>, <?= (float) $balance ?>)"><?= $due > 0 ? '补缴差额' : '立即支付' ?></button>
    <?php endif; ?>
    <?php if ($signable): ?>
        <button class="btn btn--primary btn--sm" id="wb-sign-btn" <?= $payable ? '' : 'style="margin-left:auto"' ?>
                onclick="signWaybill(<?= (int) $waybill['id'] ?>)">确认签收</button>
    <?php endif; ?>
    <?php if ($cancellable): ?>
        <button class="btn btn--ghost btn--sm" onclick="cancelWaybill(<?= (int) $waybill['id'] ?>)">取消运单</button>
    <?php endif; ?>
</div>

<?php if ($due > 0): ?>
<section class="panel" style="border-left:4px solid var(--c-red)">
    <h3 class="section-head"><span class="badge badge--orange">待补款</span><span class="rule"></span></h3>
    <p>包裹已完成打包测重，按「实际重量与体积重取大」计费后应付总额为
        <b>￥<?= number_format((float) $waybill['total_amount'], 2) ?></b>，扣除下单时已支付的运费后需补缴
        <b style="color:var(--c-red)">￥<?= number_format($due, 2) ?></b>。
        请补缴后由仓库安排出库；余额不足可先到「余额与充值」充值。若不再需要本次转运，也可取消运单退回已付运费。</p>
</section>
<?php endif; ?>

<div class="site-grid">
    <section class="panel">
        <h3 class="section-head"><span class="badge badge--blue">运单信息</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>运单号</span><b class="v-code mono"><?= View::e($waybill['waybill_no']) ?></b></div>
            <div class="hero-address__row"><span>转运方式</span><span><?= View::badge([1 => '原箱转运', 2 => '合箱转运', 3 => '分箱转运'][(int) $waybill['bill_type']] ?? '—', 'indigo') ?> <?= View::badge((int) $waybill['package_count'] . ' 件', 'cyan') ?></span></div>
            <div class="hero-address__row"><span>物流渠道</span><b><?= View::e((string) ($waybill['channel_name'] ?? '—')) ?></b></div>
            <div class="hero-address__row"><span>参考时效</span><b><?= View::e((string) ($waybill['aging_desc'] ?? '—')) ?></b></div>
            <div class="hero-address__row"><span>承运单号</span><b class="v-code mono"><?= View::e($waybill['carrier_tracking_no'] ?: '出库后生成') ?></b></div>
            <div class="hero-address__row"><span>收件人</span><b><?= View::e($waybill['receiver_name']) ?> · <?= View::e($waybill['receiver_phone']) ?></b></div>
            <div class="hero-address__row"><span>目的地</span><b><?= View::e($waybill['dest_country']) ?> <?= View::e((string) ($addr['state'] ?? '')) ?> <?= View::e((string) ($addr['city'] ?? '')) ?></b></div>
            <div class="hero-address__row"><span>详细地址</span><b><?= View::e((string) ($addr['address1'] ?? '')) ?> <?= View::e((string) ($addr['address2'] ?? '')) ?> <?= View::e((string) ($addr['zip_code'] ?? '')) ?></b></div>
        </div>
    </section>

    <section class="panel">
        <h3 class="section-head"><span class="badge badge--violet">重量与计费重量</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>实际重量</span><b class="v-measure"><?= number_format((float) $waybill['actual_weight'], 3) ?> kg</b></div>
            <div class="hero-address__row"><span>体积重</span><b class="v-measure"><?= number_format((float) $waybill['volume_weight'], 3) ?> kg</b></div>
            <div class="hero-address__row"><span>计费重量</span><b style="color:#4f46e5"><?= number_format((float) $waybill['billable_weight'], 3) ?> kg</b></div>
            <div class="hero-address__row"><span>支付方式</span><?= View::badge((int) $waybill['pay_method'] === 1 ? '账户余额' : ((string) $waybill['pay_method'] === '' ? '未支付' : '其他'), (int) $waybill['pay_method'] === 1 ? 'teal' : 'gray') ?></div>
            <div class="hero-address__row"><span>支付时间</span><b><?= View::e($waybill['paid_at'] ? substr((string) $waybill['paid_at'], 0, 16) : '未支付') ?></b></div>
            <div class="hero-address__row"><span>出库时间</span><b><?= View::e($waybill['outbound_at'] ? substr((string) $waybill['outbound_at'], 0, 16) : '未出库') ?></b></div>
            <div class="hero-address__row"><span>签收时间</span><b><?= View::e($waybill['signed_at'] ? substr((string) $waybill['signed_at'], 0, 16) : '未签收') ?></b></div>
        </div>
        <p class="form-hint">计费重量取实际重量与体积重的较大者。</p>
    </section>
</div>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--emerald">费用明细</span><span class="rule"></span></h3>
    <div class="hero-address">
        <?php
        // 操作处理/住宅派送/其他三类附加费没有独立列，按下单时的费用快照展示
        $feeRows = [
            '运费' => $waybill['freight_amount'], '燃油附加费' => $waybill['fuel_fee'],
            '偏远附加费' => $waybill['remote_fee'], '敏感货附加费' => $waybill['sensitive_fee'],
            '其他附加费' => $feeDetail['other_surcharge_fee'] ?? 0,
            '增值服务' => $waybill['service_amount'],
            '保价费（保价额 ¥' . number_format((float) $waybill['insurance_amount'], 2) . '）' => $waybill['insurance_fee'],
            '其他费用' => $waybill['other_fee'],
        ];
        foreach ($feeRows as $label => $amount):
            if ((float) $amount <= 0) { continue; }
            ?>
            <div class="hero-address__row"><span><?= View::e($label) ?></span><b>¥<?= number_format((float) $amount, 2) ?></b></div>
        <?php endforeach; ?>
        <?php if ((float) $waybill['discount_amount'] > 0): ?>
            <div class="hero-address__row"><span>会员折扣</span><b class="v-money">- ¥<?= number_format((float) $waybill['discount_amount'], 2) ?></b></div>
        <?php endif; ?>
        <div class="hero-address__row"><span><b>应付合计</b></span><b style="color:#4f46e5;font-size:19px">¥<?= number_format((float) $waybill['total_amount'], 2) ?></b></div>
    </div>
</section>

<?php if ($services !== []): ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--purple">增值服务</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>服务</th><th>单价</th><th>数量</th><th>金额</th><th>状态</th></tr></thead>
            <tbody>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td><?= View::e($s['service_name']) ?></td>
                    <td class="nowrap">¥<?= number_format((float) $s['price'], 2) ?></td>
                    <td><?= (int) $s['qty'] ?></td>
                    <td class="nowrap">¥<?= number_format((float) $s['amount'], 2) ?></td>
                    <td><span class="badge badge--<?= (int) $s['status'] === 1 ? 'green' : 'gray' ?>"><?= (int) $s['status'] === 1 ? '已完成' : '待处理' ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--gray">关联包裹</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>包裹编号</th><th>商品</th><th>数量</th><th>重量</th></tr></thead>
            <tbody>
            <?php if ($packages === []): ?>
                <tr><td colspan="4"><div class="empty-state">无关联包裹记录。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($packages as $p): ?>
                <tr>
                    <td class="mono"><a href="/user/packages/<?= (int) $p['id'] ?>"><?= View::e($p['package_no']) ?></a></td>
                    <td><?= View::e($p['item_name']) ?></td>
                    <td><?= (int) $p['item_qty'] ?></td>
                    <td class="nowrap"><?= $p['weight'] !== null ? number_format((float) $p['weight'], 3) . ' kg' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">物流轨迹</span><span class="rule"></span>
        <a class="btn btn--ghost" href="/user/tracking?waybill=<?= (int) $waybill['id'] ?>">查看完整轨迹</a>
    </h3>
    <?php if ($tracking === []): ?>
        <div class="empty-state">暂无轨迹记录。出库后承运商轨迹会同步到这里。</div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th class="nowrap">时间</th><th>状态</th><th>地点</th><th>来源</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($tracking, 0, 8) as $t): ?>
                    <tr>
                        <td class="nowrap"><?= View::e(substr((string) $t['event_time'], 0, 16)) ?></td>
                        <td><?= View::e($t['status_desc']) ?></td>
                        <td><?= View::e((string) ($t['location'] ?? '—')) ?></td>
                        <td><span class="badge badge--gray"><?= View::e((string) ($t['source'] ?? '系统')) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($logs !== []): ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--gray">状态流转记录</span><span class="rule"></span></h3>
    <ul class="form-hint" style="line-height:2.1;padding-left:18px">
        <?php foreach ($logs as $log): ?>
            <li>
                <?= View::e(substr((string) $log['created_at'], 0, 16)) ?> ·
                <?= View::badge(Status::waybill((int) $log['from_status']), Status::waybillTone((int) $log['from_status'])) ?> → <?= View::badge(Status::waybill((int) $log['to_status']), Status::waybillTone((int) $log['to_status'])) ?>
                <?= View::e((string) ($log['remark'] ?? '')) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-cancel">
    <div class="adm-dialog__head">取消运单 · <?= View::e($waybill['waybill_no']) ?></div>
    <div class="adm-dialog__body">
        <div class="form-field"><label class="form-label">取消原因</label>
            <input class="form-input" id="cancel-reason" maxlength="200" placeholder="请说明取消原因"></div>
        <p class="form-hint">取消后关联包裹会被释放，可重新提交转运。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">再想想</button>
        <button class="btn btn--danger" onclick="submitCancel()">确认取消</button>
    </div>
</dialog>

<script>
function payWaybill(id, no, amount, balance) {
    if (balance < amount) {
        Notify.warning('账户余额不足（¥' + balance.toFixed(2) + ' / 应付 ¥' + amount.toFixed(2) + '），请先充值');
        return;
    }
    admConfirm('将使用账户余额支付 ¥' + amount.toFixed(2) + '，确认支付吗？', async function () {
        var res = await App.post('/user/waybills/pay', { id: id });
        App.handle(res, '支付成功');
        if (!res.success) { return; }
        // 局部更新：状态徽标改为已支付，移除支付按钮
        var statusBadge = document.querySelector('.admin-topbar ~ * .badge--orange, .adm-toolbar .badge--orange');
        if (statusBadge) { statusBadge.className = 'badge badge--blue'; statusBadge.textContent = '已支付'; }
        document.querySelectorAll('[onclick^="payWaybill"]').forEach(function (b) { b.remove(); });
    }, { title: '余额支付运费', confirmText: '确认支付', danger: true });
}

function cancelWaybill() {
    document.getElementById('cancel-reason').value = '';
    document.getElementById('dlg-cancel').showModal();
}

function submitCancel() {
    var reason = val('cancel-reason').trim();
    if (reason.length < 2) { Notify.warning('请填写取消原因'); return; }
    Confirm.show({
        title: '确认取消运单', level: 'L1', message: '取消后关联包裹会被释放，可重新提交转运。',
        confirmText: '确认取消', danger: true,
        onConfirm: async function () {
            var res = await App.post('/user/waybills/cancel', { id: <?= (int) $waybill['id'] ?>, reason: reason });
            App.handle(res, '运单已取消');
            if (!res.success) { return; }
            document.getElementById('dlg-cancel').close();
            var badge = document.querySelector('.adm-toolbar .badge');
            if (badge) { badge.className = 'badge badge--red'; badge.textContent = '已取消'; }
            document.querySelectorAll('[onclick^="cancelWaybill"], [onclick^="payWaybill"]').forEach(function (b) { b.remove(); });
        }
    });
}

/** 确认签收：成功后原位更新状态徽标并移除签收入口，不整页刷新（规范 §2.5） */
function signWaybill(id) {
    Confirm.show({
        title: '确认签收', level: 'L1',
        message: '确认货物已收到？签收后积分将在缓冲期后到账；若货物有问题请先联系客服，不要确认签收。',
        confirmText: '确认签收',
        onConfirm: async function () {
            var res = await App.post('/user/waybills/sign', { id: id });
            App.handle(res, '已确认签收');
            if (!res.success) { return; }
            var badge = document.getElementById('wb-status-badge');
            if (badge) { badge.className = 'badge badge--green'; badge.textContent = '已签收'; }
            var btn = document.getElementById('wb-sign-btn');
            if (btn) { btn.remove(); }
        }
    });
}
</script>
