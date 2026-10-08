<?php declare(strict_types=1);
/**
 * @file app/Views/member/invite.php
 * @description 会员中心：邀请返利（邀请码、邀请记录、返利明细）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var string $inviteCode
 * @var array  $summary
 * @var array  $invitees
 * @var int    $inviteeTotal
 * @var int    $page
 * @var int    $pages
 * @var array  $rewards
 * @var int    $rewardTotal
 */
?>
<section class="welcome-banner">
    <div>
        <h2>邀请好友，一起省运费</h2>
        <p>好友通过您的邀请码注册后，双方均可获得积分奖励；好友完成转运后还有额外返利。</p>
    </div>
    <div class="welcome-banner__meta">
        <span class="badge--glass">🎁 已邀请 <?= (int) $inviteeTotal ?> 人</span>
        <span class="badge--glass">⭐ 返利 <?= (int) $summary['paid_points'] ?> 分</span>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--violet">我的邀请码</span><span class="rule"></span></h3>
    <div class="captcha-row">
        <input class="form-input mono" id="invite-code" value="<?= View::e($inviteCode) ?>" readonly style="font-size:20px;letter-spacing:3px;font-weight:700;color:#4f46e5">
        <button class="btn btn--primary" type="button" onclick="copyInvite()">复制邀请码</button>
        <button class="btn btn--ghost" type="button" onclick="copyInviteLink()">复制邀请链接</button>
    </div>
    <p class="form-hint">邀请码即您的用户编号。</p>
    <input type="hidden" id="invite-link" value="">
</section>

<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">👥</div>
        <div><div class="astat__value"><?= (int) $inviteeTotal ?></div><div class="astat__label">已邀请用户</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">⭐</div>
        <div><div class="astat__value"><?= (int) $summary['paid_points'] ?></div><div class="astat__label">已发放积分</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">💰</div>
        <div><div class="astat__value">¥<?= number_format((float) $summary['paid_amount'], 2) ?></div><div class="astat__label">已发放返利</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) $summary['pending_count'] ?></div><div class="astat__label">待发放</div></div></div>
</section>

<div id="ivt-region">
    <?= View::partial('member/invite_region', get_defined_vars()) ?>
</div>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--emerald">返利明细</span><span class="rule"></span>
        <span class="badge badge--gray">最近 <?= count($rewards) ?> 条 / 共 <?= (int) $rewardTotal ?> 条</span>
    </h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th class="nowrap">时间</th><th>被邀请人</th><th>奖励形式</th><th>奖励内容</th><th>状态</th></tr></thead>
            <tbody>
            <?php if ($rewards === []): ?>
                <tr><td colspan="5"><div class="empty-state">暂无返利记录。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($rewards as $r): ?>
                <tr>
                    <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
                    <td class="mono"><?= View::e((string) $r['invitee_no']) ?></td>
                    <td><?= (int) $r['reward_type'] === 2 ? '余额' : '积分' ?></td>
                    <td class="nowrap"><?= (int) $r['reward_type'] === 2 ? '¥' . number_format((float) $r['amount'], 2) : (int) $r['points'] . ' 分' ?></td>
                    <td><span class="badge badge--<?= (int) $r['status'] === 1 ? 'green' : 'orange' ?>"><?= (int) $r['status'] === 1 ? '已发放' : '待发放' ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
/**
 * 翻页：仅替换邀请记录区域，不整页刷新（规范 §2.5.2 分页）
 */
async function ivtReload(page, push) {
    var region = document.getElementById('ivt-region');
    if (!region) { return; }
    region.style.opacity = '0.55';
    try {
        var res = await fetch('/user/member/invite?page=' + page, { headers: { 'X-Requested-With': 'fetch' } });
        if (!res.ok || res.redirected) {
            Notify.error('登录状态已失效，请重新登录');
            return;
        }
        region.innerHTML = await res.text();
        if (push !== false) { history.pushState({}, '', '/user/member/invite?page=' + page); }
    } catch (err) {
        Notify.error('网络异常，列表未能刷新');
    } finally {
        region.style.opacity = '1';
    }
}

function ivtPage(n) { ivtReload(n); }

window.addEventListener('popstate', function () {
    var n = parseInt(new URLSearchParams(location.search).get('page'), 10) || 1;
    ivtReload(n, false);
});

function copyInvite() {
    var code = document.getElementById('invite-code');
    code.select();
    try {
        navigator.clipboard.writeText(code.value).then(function () {
            Notify.success('邀请码已复制');
        }).catch(function () {
            Notify.warning('已选中邀请码，请按 Ctrl+C 复制');
        });
    } catch (err) {
        Notify.warning('已选中邀请码，请按 Ctrl+C 复制');
    }
}

function copyInviteLink() {
    var link = location.origin + '/user/register?invite=' + encodeURIComponent(document.getElementById('invite-code').value);
    navigator.clipboard.writeText(link).then(function () {
        Notify.success('邀请链接已复制');
    }).catch(function () {
        Notify.warning('复制失败，请手动复制邀请码');
    });
}
</script>
