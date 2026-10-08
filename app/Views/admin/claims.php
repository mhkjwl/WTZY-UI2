<?php declare(strict_types=1);
/**
 * @file app/Views/admin/claims.php
 * @description 理赔处理页：状态统计 + 检索 + 审核/驳回对话框
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;
use App\Models\ClaimModel;

/**
 * @var array $rows
 * @var int $page
 * @var int $pages
 * @var int $status
 * @var array $counts
 * @var array $perms
 * @var string $csrf
 */
$initialUrl = '/admin/claims?' . http_build_query(array_filter(['status' => $status, 'page' => $page], static fn($v): bool => $v !== -1));
?>
<section class="astat-grid">
    <div class="astat astat--amber"><div class="astat__icon">📨</div>
        <div><div class="astat__value" id="cl-c0"><?= (int) ($counts[0] ?? 0) ?></div><div class="astat__label">待受理</div></div></div>
    <div class="astat astat--blue"><div class="astat__icon">🔧</div>
        <div><div class="astat__value" id="cl-c1"><?= (int) ($counts[1] ?? 0) ?></div><div class="astat__label">处理中</div></div></div>
    <div class="astat astat--cyan"><div class="astat__icon">🔍</div>
        <div><div class="astat__value" id="cl-c2"><?= (int) ($counts[2] ?? 0) ?></div><div class="astat__label">初审通过待财务审</div></div></div>
    <div class="astat astat--purple"><div class="astat__icon">📊</div>
        <div><div class="astat__value" id="cl-c5"><?= (int) ($counts[5] ?? 0) ?></div><div class="astat__label">财务已审待终审</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">💸</div>
        <div><div class="astat__value" id="cl-c6"><?= (int) ($counts[6] ?? 0) ?></div><div class="astat__label">终审通过待赔付</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">✅</div>
        <div><div class="astat__value" id="cl-c4"><?= (int) ($counts[4] ?? 0) ?></div><div class="astat__label">已赔付</div></div></div>
</section>

<section class="panel">
    <div class="adm-toolbar">
        <select class="form-input" id="cl-status">
            <option value="-1">全部状态</option>
            <?php foreach (ClaimModel::STATUS as $code => $label): ?>
                <option value="<?= $code ?>" <?= $status === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--primary" onclick="admSearch('<?= View::e($base) ?>', {status: val('cl-status')})">检索</button>
        <span class="form-hint">赔付将按核定金额直接给用户余额入账并生成理赔赔偿流水，请先完成核实。</span>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('admin/claims_table', get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-claim">
    <div class="adm-dialog__head" id="claim-title">理赔初审</div>
    <div class="adm-dialog__body">
        <input type="hidden" id="claim-id">
        <input type="hidden" id="claim-action">
        <div class="form-field" id="claim-amount-field"><label class="form-label">核定赔付金额（元，需大于 0）</label>
            <input class="form-input" type="number" step="0.01" min="0.01" id="claim-amount"></div>
        <div class="form-field"><label class="form-label" id="claim-remark-label">初审意见（选填）</label>
            <textarea class="form-input" id="claim-remark" rows="3"></textarea></div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" id="claim-btn" onclick="submitClaim()">确认提交</button>
    </div>
</dialog>

<script>
function openClaim(id, action, claimNo, amount) {
    document.getElementById('claim-id').value = id;
    document.getElementById('claim-action').value = action;
    var isReview = action === 'review';
    document.getElementById('claim-title').textContent = (isReview ? '理赔初审 · ' : '驳回理赔 · ') + claimNo;
    document.getElementById('claim-amount-field').style.display = isReview ? '' : 'none';
    document.getElementById('claim-amount').value = isReview ? amount : '';
    document.getElementById('claim-remark').value = '';
    document.getElementById('claim-remark-label').textContent = isReview ? '初审意见（选填）' : '驳回原因（必填，将通知用户）';
    var btn = document.getElementById('claim-btn');
    btn.textContent = isReview ? '提交初审' : '确认驳回';
    btn.className = isReview ? 'btn btn--primary' : 'btn btn--danger';
    document.getElementById('dlg-claim').showModal();
}
function openClaimReview(id, claimNo, amount) { openClaim(id, 'review', claimNo, amount); }
function openClaimReject(id, claimNo) { openClaim(id, 'reject', claimNo, 0); }
function submitClaim() {
    var action = val('claim-action');
    var remark = val('claim-remark').trim();
    var amount = parseFloat(val('claim-amount')) || 0;
    if (action === 'reject' && remark === '') { Notify.warning('请填写驳回原因'); return; }
    if (action === 'review' && amount <= 0) { Notify.warning('核定赔付金额必须大于 0（如无需赔付请驳回）'); return; }

    // 初审即核定赔付金额，驳回为终态并通知用户，均需二次确认（规范 §4.1 状态变更类）
    Confirm.show({
        title: action === 'review' ? '核定赔付金额确认' : '驳回理赔确认',
        // 驳回置为终态不可回退，用 L2 需勾选确认；初审后续仍可驳回，用 L1
        level: action === 'reject' ? 'L2' : 'L1',
        message: action === 'review'
            ? '将核定赔付金额 ¥' + amount.toFixed(2) + ' 并提交财务审核，后续赔付按该金额入账，请确认金额无误。'
            : '将驳回该理赔申请并通知用户，驳回后该单不可再进入审核流程。驳回原因：' + remark,
        confirmText: action === 'review' ? '确认提交初审' : '确认驳回',
        danger: true,
        onConfirm: async function () {
            var btn = document.getElementById('claim-btn');
            btn.disabled = true;
            try {
                var res = await App.post(action === 'review' ? '/admin/claims/review' : '/admin/claims/reject', {
                    id: val('claim-id'),
                    approved_amount: val('claim-amount') || 0,
                    remark: remark
                });
                App.handle(res);
                if (res.success) { document.getElementById('dlg-claim').close(); admRefresh(); }
            } finally { btn.disabled = false; }
        }
    });
}
function claimListAction(url, data, fromStatus, message, opts) {
    opts = opts || {};
    Confirm.show({
        title: opts.title || '操作确认',
        // 等级由调用方决定（赔付等不可逆资金操作应传 L2），默认 L1
        level: opts.level || 'L1',
        message: message,
        confirmText: opts.confirmText || '确认执行',
        // 与 confirm.js 默认一致：默认按危险操作呈现，传 danger:false 可关闭
        danger: opts.danger !== false,
        onConfirm: async function () {
            var res = await App.post(url, data);
            App.handle(res);
            if (res.success) {
                admRefresh();
                claimStatsMove(fromStatus, res.data ? res.data.status : undefined);
            }
        }
    });
}
function claimStatsMove(from, to) {
    if (to === undefined || to === null) { return; }
    var card = { 0: 'cl-c0', 1: 'cl-c1', 2: 'cl-c2', 5: 'cl-c5', 6: 'cl-c6', 4: 'cl-c4' };
    function bump(id, delta) {
        var el = document.getElementById(id);
        if (el) { el.textContent = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta); }
    }
    if (from !== to && card[from]) { bump(card[from], -1); }
    if (card[to]) { bump(card[to], 1); }
}
</script>
