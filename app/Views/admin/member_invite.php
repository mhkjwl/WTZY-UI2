<?php declare(strict_types=1);
/**
 * @file app/Views/admin/member_invite.php
 * @description 后台：邀请返利设置
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $config
 * @var int   $invited
 * @var int   $rewards
 * @var int   $pending
 * @var array $topInviter
 * @var array $perms
 * @var string $csrf
 */
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">👥</div>
        <div><div class="astat__value"><?= (int) $invited ?></div><div class="astat__label">被邀请注册用户</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">🎁</div>
        <div><div class="astat__value"><?= (int) $rewards ?></div><div class="astat__label">返利记录</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) $pending ?></div><div class="astat__label">待发放</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">🏆</div>
        <div><div class="astat__value"><?= count($topInviter) ?></div><div class="astat__label">邀请排行</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--violet">邀请返利设置</span><span class="rule"></span></h3>
    <div class="form-row">
        <div class="form-field" style="max-width:220px">
            <label class="form-label">邀请功能</label>
            <select class="form-input" id="iv-enabled">
                <option value="1" <?= $config['invite_enabled'] === '1' ? 'selected' : '' ?>>开启（用户可邀请好友）</option>
                <option value="0" <?= $config['invite_enabled'] === '0' ? 'selected' : '' ?>>关闭</option>
            </select></div>
        <div class="form-field" style="max-width:220px">
            <label class="form-label">奖励形式</label>
            <select class="form-input" id="iv-type">
                <option value="1" <?= $config['invite_reward_type'] === '1' ? 'selected' : '' ?>>积分</option>
                <option value="2" <?= $config['invite_reward_type'] === '2' ? 'selected' : '' ?>>余额（元）</option>
            </select></div>
        <div class="form-field" style="max-width:240px">
            <label class="form-label">邀请人奖励</label>
            <input class="form-input" type="number" min="0" id="iv-inviter" value="<?= (int) $config['invite_reward_inviter'] ?>">
            <p class="form-hint">好友注册成功后，奖励给邀请人</p>
        </div>
        <div class="form-field" style="max-width:240px">
            <label class="form-label">被邀请人奖励</label>
            <input class="form-input" type="number" min="0" id="iv-invitee" value="<?= (int) $config['invite_reward_invitee'] ?>">
            <p class="form-hint">新用户注册后立即获得</p>
        </div>
    </div>
    <div class="adm-toolbar" style="margin-bottom:0">
        <?php if (!empty($perms['member.manage'])): ?>
            <button class="btn btn--primary" type="button" onclick="saveInvite()">保存设置</button>
            <button class="btn btn--ghost" type="button" onclick="payRewards()">发放全部待发放返利（<?= (int) $pending ?>）</button>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">邀请排行</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>排名</th><th>用户编号</th><th>昵称</th><th>成功邀请</th></tr></thead>
            <tbody>
            <?php if ($topInviter === []): ?>
                <tr><td colspan="4"><div class="empty-state">暂无邀请记录。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($topInviter as $i => $row): ?>
                <tr>
                    <td><span class="badge badge--<?= $i === 0 ? 'orange' : 'gray' ?>">#<?= $i + 1 ?></span></td>
                    <td class="mono"><?= View::e((string) $row['user_no']) ?></td>
                    <td><?= View::e((string) $row['nickname']) ?></td>
                    <td class="nowrap"><?= (int) $row['c'] ?> 人</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
function saveInvite() {
    if (val('iv-enabled') === '1' && Number(val('iv-inviter')) === 0 && Number(val('iv-invitee')) === 0) {
        Notify.warning('开启邀请功能时，至少给一方设置大于 0 的奖励');
        return;
    }
    admConfirm('确定保存邀请返利设置吗？', async function () {
        var res = await App.post('/admin/member/invite/save', {
            invite_enabled: val('iv-enabled'), invite_reward_type: val('iv-type'),
            invite_reward_inviter: val('iv-inviter'), invite_reward_invitee: val('iv-invitee')
        });
        App.handle(res, '邀请设置已保存');
    }, { title: '保存邀请设置', confirmText: '确认保存' });
}

function payRewards() {
    admConfirm('确定发放全部待发放的邀请返利吗？积分/余额将立即入账，且该批量操作不可撤回。', async function () {
        var res = await App.post('/admin/member/invite/pay', {});
        App.handle(res, '返利已发放');
        if (!res.success) { return; }
        // 局部更新：待发放清零、按钮置灰，不整页刷新
        var paid = (res.data && res.data.paid) ? res.data.paid : 0;
        var stats = document.querySelectorAll('.astat__value');
        if (stats[2] && Number(stats[2].textContent) > 0) {
            stats[2].textContent = String(Math.max(0, Number(stats[2].textContent) - paid));
        }
        var btn = document.querySelector('[onclick^="payRewards"]');
        if (btn) { btn.disabled = true; btn.textContent = '返利已发放'; }
    }, { title: '发放邀请返利', confirmText: '确认发放', danger: true, level: 'L2' });
}
</script>
