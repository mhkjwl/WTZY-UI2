<?php declare(strict_types=1);
/**
 * @file app/Views/admin/claim_detail.php
 * @description 理赔详情页：完整信息 + 多级审核时间线 + 操作按钮（按状态和权限显隐）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\ClaimModel;

/**
 * @var array $claim
 * @var array $perms
 * @var string $csrf
 */
$s = (int) $claim['status'];
$canReview = !empty($perms['claim.review']);
$canFinanceAudit = !empty($perms['claim.finance_audit']);
$canAdminAudit = !empty($perms['claim.admin_audit']);
$canPay = !empty($perms['claim.pay']);
?>
<div class="adm-toolbar">
    <a class="btn btn--ghost btn--sm" href="/admin/claims">← 返回理赔列表</a>
</div>

<section class="module-hero" style="margin-top:12px">
    <div class="module-hero__icon" style="background:#e11d48">🛡️</div>
    <div>
        <div class="module-hero__title"><?= View::e($claim['claim_no']) ?>
            <span class="badge badge--<?= Status::claimTone($s) ?>" id="claim-status-badge" style="margin-left:8px"><?= View::e(ClaimModel::STATUS[$s] ?? '未知') ?></span>
        </div>
        <div class="module-hero__desc"><?= View::e(ClaimModel::TYPES[(int) $claim['claim_type']] ?? '未知') ?> · 申请人 <?= View::e($claim['user_no'] ?: '') ?> <?= View::e($claim['nickname'] ?: '') ?></div>
    </div>
</section>

<div class="site-grid">
    <section class="panel">
        <h3 class="section-head"><span class="badge badge--blue">理赔信息</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>关联运单</span><b class="mono"><?= View::e($claim['waybill_no'] ?: '—') ?></b></div>
            <div class="hero-address__row"><span>申请金额</span><b>¥<?= number_format((float) $claim['amount'], 2) ?></b></div>
            <div class="hero-address__row"><span>核定赔付金额</span><b id="claim-approved-row"><?= $claim['approved_amount'] !== null ? '¥' . number_format((float) $claim['approved_amount'], 2) : '待定' ?></b></div>
            <div class="hero-address__row"><span>提交时间</span><b><?= View::e($claim['created_at']) ?></b></div>
            <div class="hero-address__row"><span>处理时间</span><b id="claim-handled-row"><?= View::e($claim['handled_at'] ?: '—') ?></b></div>
        </div>
        <h4 style="font-size:14px;margin:14px 0 6px">问题描述</h4>
        <p style="font-size:14px;line-height:1.8;color:var(--ac-sub, #64748b)"><?= View::e($claim['description']) ?></p>
        <?php
        $images = json_decode((string) ($claim['images'] ?? '[]'), true) ?: [];
        if ($images !== []): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                <?php foreach ($images as $img): ?>
                    <img src="<?= View::e($img) ?>" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0" alt="凭证">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h3 class="section-head"><span class="badge badge--violet">审核时间线</span><span class="rule"></span></h3>
        <div style="padding:0 4px">
            <?php
            $timeline = [
                'submit'  => ['提交理赔申请', $claim['created_at']],
                'finance' => ['财务审核', $claim['finance_approved_at'] ?? null],
                'admin'   => ['管理员终审', $claim['admin_approved_at'] ?? null],
                'pay'     => ['赔付执行', $s === 4 ? $claim['handled_at'] : null],
            ];
            $step = 0;
            foreach ($timeline as $key => [$label, $time]): $step++; ?>
                <div id="tl-<?= $key ?>" style="display:flex;align-items:center;gap:12px;padding:8px 0">
                    <span style="width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;
                        background: <?= $time !== null ? '#16a34a' : '#e2e8f0' ?>; color: <?= $time !== null ? '#fff' : '#94a3b8' ?>;">
                        <?= $time !== null ? '✓' : $step ?>
                    </span>
                    <span style="font-size:13px;color:<?= $time !== null ? 'var(--ac-ink, #0f172a)' : '#94a3b8' ?>"><?= $label ?></span>
                    <?php if ($time !== null): ?><span style="font-size:12px;color:#94a3b8;margin-left:auto"><?= View::e($time) ?></span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($claim['handle_remark'])): ?>
            <div style="background:#f8fafc;border-radius:8px;padding:12px;margin-top:12px">
                <b style="font-size:13px">处理意见：</b>
                <p style="font-size:13px;color:var(--ac-sub, #64748b);margin-top:4px"><?= View::e($claim['handle_remark']) ?></p>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--orange">操作</span><span class="rule"></span></h3>
    <div class="adm-toolbar" id="claim-ops">
        <?php if ($canReview && $s === 0): ?>
            <button class="btn btn--primary" onclick="claimAct('/admin/claims/accept', '受理该理赔单并进入处理中吗？')">受理</button>
        <?php endif; ?>
        <?php if ($canReview && $s === 1): ?>
            <button class="btn btn--primary" onclick="claimOpenReview()">初审通过</button>
        <?php endif; ?>
        <?php if ($canFinanceAudit && $s === 2): ?>
            <button class="btn btn--primary" onclick="claimAct('/admin/claims/finance-audit', '确认财务审核通过吗？通过后将提交管理员终审。', {confirmText: '确认通过'})">财务审核通过</button>
        <?php endif; ?>
        <?php if ($canAdminAudit && $s === 5): ?>
            <button class="btn btn--primary" onclick="claimAct('/admin/claims/admin-audit', '确认管理员终审通过吗？通过后可执行赔付。', {confirmText: '终审通过'})">终审通过</button>
        <?php endif; ?>
        <?php if ($canPay && $s === 6): ?>
            <button class="btn btn--primary" onclick="claimAct('/admin/claims/pay', '将按核定金额 ¥<?= number_format((float) ($claim['approved_amount'] ?? 0), 2) ?> 给用户余额入账。该操作不可撤销，且入账后无法重试。', {confirmText: '确认赔付', title: '执行赔付确认', level: 'L2'})">执行赔付</button>
        <?php endif; ?>
        <?php if ($canReview && in_array($s, [0, 1, 2], true)): ?>
            <button class="btn btn--danger" onclick="claimOpenReject()">驳回</button>
        <?php endif; ?>
        <?php if (!in_array($s, [0, 1, 2, 5, 6], true)): ?>
            <span class="form-hint">当前状态无可用操作。</span>
        <?php endif; ?>
    </div>
</section>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-claim-d">
    <div class="adm-dialog__head" id="cd-title">理赔操作</div>
    <div class="adm-dialog__body">
        <input type="hidden" id="cd-mode">
        <div class="form-field" id="cd-amount-field"><label class="form-label">核定赔付金额（元，需大于 0）</label>
            <input class="form-input" type="number" step="0.01" min="0.01" id="cd-amount"></div>
        <div class="form-field"><label class="form-label" id="cd-remark-label">初审意见（选填）</label>
            <textarea class="form-input" id="cd-remark" rows="3"></textarea></div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" id="cd-btn" onclick="claimSubmit()">确认提交</button>
    </div>
</dialog>

<script>
var CLAIM = {
    id: <?= (int) $claim['id'] ?>,
    status: <?= $s ?>,
    amount: <?= (float) $claim['amount'] ?>,
    approved: <?= $claim['approved_amount'] !== null ? (string) (float) $claim['approved_amount'] : 'null' ?>,
    perms: { review: <?= $canReview ? 'true' : 'false' ?>, finance: <?= $canFinanceAudit ? 'true' : 'false' ?>, admin: <?= $canAdminAudit ? 'true' : 'false' ?>, pay: <?= $canPay ? 'true' : 'false' ?> }
};

function claimOpenDialog(mode) {
    var isReview = mode === 'review';
    document.getElementById('cd-mode').value = mode;
    document.getElementById('cd-amount-field').style.display = isReview ? '' : 'none';
    document.getElementById('cd-amount').value = isReview ? (CLAIM.approved !== null ? CLAIM.approved : CLAIM.amount) : '';
    document.getElementById('cd-remark').value = '';
    document.getElementById('cd-remark-label').textContent = isReview ? '初审意见（选填）' : '驳回原因（必填，将通知用户）';
    document.getElementById('cd-title').textContent = isReview ? '理赔初审 · 核定赔付金额' : '驳回理赔';
    var btn = document.getElementById('cd-btn');
    btn.textContent = isReview ? '提交初审' : '确认驳回';
    btn.className = isReview ? 'btn btn--primary' : 'btn btn--danger';
    document.getElementById('dlg-claim-d').showModal();
}
function claimOpenReview() { claimOpenDialog('review'); }
function claimOpenReject() { claimOpenDialog('reject'); }
function claimSubmit() {
    var mode = val('cd-mode');
    var remark = val('cd-remark').trim();
    var amount = parseFloat(val('cd-amount')) || 0;
    if (mode === 'reject' && remark === '') { Notify.warning('请填写驳回原因'); return; }
    if (mode === 'review' && amount <= 0) { Notify.warning('核定赔付金额必须大于 0（如无需赔付请驳回）'); return; }

    // 初审即核定赔付金额，驳回为终态并通知用户，均需二次确认（规范 §4.1 状态变更类）
    Confirm.show({
        title: mode === 'review' ? '核定赔付金额确认' : '驳回理赔确认',
        // 驳回置为终态不可回退，用 L2 需勾选确认；初审后续仍可驳回，用 L1
        level: mode === 'reject' ? 'L2' : 'L1',
        message: mode === 'review'
            ? '将核定赔付金额 ¥' + amount.toFixed(2) + ' 并提交财务审核，后续赔付按该金额入账，请确认金额无误。'
            : '将驳回该理赔申请并通知用户，驳回后该单不可再进入审核流程。驳回原因：' + remark,
        confirmText: mode === 'review' ? '确认提交初审' : '确认驳回',
        danger: true,
        onConfirm: async function () {
            var btn = document.getElementById('cd-btn');
            btn.disabled = true;
            try {
                var res = await App.post(mode === 'review' ? '/admin/claims/review' : '/admin/claims/reject', {
                    id: CLAIM.id,
                    approved_amount: val('cd-amount') || 0,
                    remark: remark
                });
                App.handle(res);
                if (res.success) {
                    document.getElementById('dlg-claim-d').close();
                    if (mode === 'review') { CLAIM.approved = amount; }
                    claimApply(res.data ? res.data.status : undefined);
                }
            } finally { btn.disabled = false; }
        }
    });
}
function claimAct(url, message, opts) {
    opts = opts || {};
    Confirm.show({
        title: opts.title || '操作确认',
        // 等级由调用方决定（赔付等不可逆资金操作应传 L2），默认 L1
        level: opts.level || 'L1',
        message: message,
        confirmText: opts.confirmText || '确认',
        // 与 confirm.js 默认一致：默认按危险操作呈现，传 danger:false 可关闭
        danger: opts.danger !== false,
        onConfirm: async function () {
            var res = await App.post(url, { id: CLAIM.id });
            App.handle(res);
            if (res.success) { claimApply(res.data ? res.data.status : undefined); }
        }
    });
}
function claimBtn(label, cls, fn) {
    var b = document.createElement('button');
    b.className = 'btn ' + cls;
    b.textContent = label;
    b.onclick = fn;
    return b;
}
function claimRenderOps() {
    var ops = document.getElementById('claim-ops');
    if (!ops) { return; }
    ops.innerHTML = '';
    var s = CLAIM.status, any = false;
    if (s === 0 && CLAIM.perms.review) {
        ops.appendChild(claimBtn('受理', 'btn--primary', function () {
            claimAct('/admin/claims/accept', '受理该理赔单并进入处理中吗？');
        }));
        any = true;
    }
    if (s === 1 && CLAIM.perms.review) { ops.appendChild(claimBtn('初审通过', 'btn--primary', claimOpenReview)); any = true; }
    if (s === 2 && CLAIM.perms.finance) {
        ops.appendChild(claimBtn('财务审核通过', 'btn--primary', function () {
            claimAct('/admin/claims/finance-audit', '确认财务审核通过吗？通过后将提交管理员终审。', { confirmText: '确认通过' });
        }));
        any = true;
    }
    if (s === 5 && CLAIM.perms.admin) {
        ops.appendChild(claimBtn('终审通过', 'btn--primary', function () {
            claimAct('/admin/claims/admin-audit', '确认管理员终审通过吗？通过后可执行赔付。', { confirmText: '终审通过' });
        }));
        any = true;
    }
    if (s === 6 && CLAIM.perms.pay) {
        var money = CLAIM.approved === null ? '0.00' : parseFloat(CLAIM.approved).toFixed(2);
        ops.appendChild(claimBtn('执行赔付', 'btn--primary', function () {
            claimAct('/admin/claims/pay', '将按核定金额 ¥' + money + ' 给用户余额入账。该操作不可撤销，且入账后无法重试。', { confirmText: '确认赔付', title: '执行赔付确认', level: 'L2' });
        }));
        any = true;
    }
    if (CLAIM.perms.review && [0, 1, 2].indexOf(s) > -1) { ops.appendChild(claimBtn('驳回', 'btn--danger', claimOpenReject)); any = true; }
    if (!any) {
        var hint = document.createElement('span');
        hint.className = 'form-hint';
        hint.textContent = '当前状态无可用操作。';
        ops.appendChild(hint);
    }
}
function claimNow() {
    var d = new Date(), p = function (n) { return (n < 10 ? '0' : '') + n; };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
}
function tlDone(id, timeStr) {
    var row = document.getElementById(id);
    if (!row) { return; }
    var dot = row.children[0], label = row.children[1], time = row.children[2];
    dot.style.background = '#16a34a';
    dot.style.color = '#fff';
    dot.textContent = '✓';
    label.style.color = 'var(--ac-ink, #0f172a)';
    if (!time) {
        time = document.createElement('span');
        time.style.cssText = 'font-size:12px;color:#94a3b8;margin-left:auto';
        row.appendChild(time);
    }
    time.textContent = timeStr;
}
function claimApply(to) {
    if (to === undefined || to === null) { return; }
    CLAIM.status = to;
    var map = { 0: ['orange', '待受理'], 1: ['blue', '处理中'], 2: ['cyan', '初审通过'], 5: ['purple', '财务已审'], 6: ['violet', '管理员已审'], 3: ['red', '已驳回'], 4: ['green', '已赔付'] };
    var badge = document.getElementById('claim-status-badge');
    if (badge && map[to]) { badge.className = 'badge badge--' + map[to][0]; badge.textContent = map[to][1]; }
    var now = claimNow();
    if (to === 5 || to === 6 || to === 4) { tlDone('tl-finance', now); }
    if (to === 6 || to === 4) { tlDone('tl-admin', now); }
    if (to === 4) { tlDone('tl-pay', now); }
    if ([2, 3, 4].indexOf(to) > -1) {
        var handled = document.getElementById('claim-handled-row');
        if (handled) { handled.textContent = now; }
    }
    if (to === 2 && CLAIM.approved !== null) {
        var approved = document.getElementById('claim-approved-row');
        if (approved) { approved.textContent = '¥' + parseFloat(CLAIM.approved).toFixed(2); }
    }
    claimRenderOps();
}
</script>
