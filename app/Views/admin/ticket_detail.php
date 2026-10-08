<?php declare(strict_types=1);
/**
 * @file app/Views/admin/ticket_detail.php
 * @description 后台工单详情：工单与用户信息 + 完整对话记录（区分用户/客服）+ 受理/回复/关闭
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\UserModel;

/**
 * @var array $ticket
 * @var array $replies
 * @var array $categories
 * @var array $statusMap
 * @var array $perms
 */
$status = (int) $ticket['status'];
$closed = $status === 3;
$statusTone = Status::ticketTone($status);
$lastFromUser = $replies !== [] && (int) end($replies)['reply_side'] === 1;
?>
<div class="adm-toolbar">
    <a class="btn btn--ghost btn--sm" href="/admin/tickets">‹ 返回列表</a>
    <span class="badge badge--<?= $statusTone ?>"><?= View::e($statusMap[$status] ?? '未知') ?></span>
    <span class="badge badge--purple"><?= View::e($categories[(int) $ticket['category']] ?? '其他') ?></span>
    <?php if (!$closed && $lastFromUser): ?><span class="badge badge--red">等待客服回复</span><?php endif; ?>
    <?php if (!$closed && (int) $ticket['assign_admin_id'] === 0 && !empty($perms['ticket.handle'])): ?>
        <button class="btn btn--primary btn--sm" style="margin-left:auto"
                onclick="admAction('/admin/tickets/assign', {id: <?= (int) $ticket['id'] ?>}, '认领该工单由你处理？', {confirmText: '受理工单'})">受理工单</button>
    <?php elseif (!$closed && !empty($perms['ticket.handle'])): ?>
        <button class="btn btn--ghost btn--sm" style="margin-left:auto" onclick="closeTicket()">关闭工单</button>
    <?php endif; ?>
</div>

<div class="site-grid">
    <section class="panel">
        <h3 class="section-head"><span class="badge badge--blue">工单信息</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>工单号</span><b class="v-code mono"><?= View::e($ticket['ticket_no']) ?></b></div>
            <div class="hero-address__row"><span>标题</span><b><?= View::e($ticket['title']) ?></b></div>
            <div class="hero-address__row"><span>关联运单</span><b class="v-code mono"><?= View::e((string) ($ticket['waybill_no'] ?? '') ?: '—') ?></b></div>
            <div class="hero-address__row"><span>关联包裹</span><b class="v-code mono"><?= View::e((string) ($ticket['package_no'] ?? '') ?: '—') ?></b></div>
            <div class="hero-address__row"><span>受理客服</span>
                <b><?= View::e((string) ($ticket['assign_name'] ?? '') ?: '待受理') ?></b></div>
            <div class="hero-address__row"><span>提交时间</span><b><?= View::e(substr((string) $ticket['created_at'], 0, 16)) ?></b></div>
            <div class="hero-address__row"><span>最后回复</span>
                <b><?= View::e($ticket['last_reply_at'] ? substr((string) $ticket['last_reply_at'], 0, 16) : '暂无') ?></b></div>
            <div class="hero-address__row"><span>关闭时间</span>
                <b><?= View::e($ticket['closed_at'] ? substr((string) $ticket['closed_at'], 0, 16) : '未关闭') ?></b></div>
        </div>
    </section>

    <section class="panel">
        <h3 class="section-head"><span class="badge badge--violet">提交用户</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>用户编号</span><b class="v-code mono"><?= View::e((string) ($ticket['user_no'] ?? '—')) ?></b></div>
            <div class="hero-address__row"><span>用户名</span><b><?= View::e(UserModel::displayName($ticket)) ?></b></div>
            <div class="hero-address__row"><span>手机号</span><b><?= View::e((string) ($ticket['phone'] ?? '') ?: '未绑定') ?></b></div>
            <div class="hero-address__row"><span>邮箱</span><b><?= View::e((string) ($ticket['email'] ?? '') ?: '未绑定') ?></b></div>
        </div>
        <a class="btn btn--ghost btn--sm" href="/admin/users?kw=<?= urlencode((string) $ticket['user_no']) ?>">查看该用户</a>
        <?php if (!empty($ticket['package_no'])): ?>
            <a class="btn btn--ghost btn--sm" href="/admin/packages/<?= (int) $ticket['package_id'] ?>">查看关联包裹</a>
        <?php endif; ?>
    </section>
</div>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--emerald">对话记录</span><span class="rule"></span>
        <span class="badge badge--gray">共 <?= count($replies) ?> 条</span>
    </h3>
    <div id="ticket-thread">
        <?= View::partial('partials/ticket_thread', ['replies' => $replies, 'viewer' => 'staff']) ?>
    </div>

    <?php if ($closed): ?>
        <div class="empty-state" style="margin-top:14px">工单已关闭，用户无法继续回复。</div>
    <?php elseif (!empty($perms['ticket.handle'])): ?>
        <div class="form-field" style="margin-top:16px">
            <label class="form-label">回复用户</label>
            <textarea class="form-input" id="tk-reply" rows="4" maxlength="2000" placeholder="回复内容会即时出现在用户的工单对话中"></textarea>
        </div>
        <button class="btn btn--primary" type="button" onclick="submitReply()">发送回复</button>
    <?php else: ?>
        <div class="empty-state" style="margin-top:14px">你只有查看权限，回复工单需要「工单处理」权限。</div>
    <?php endif; ?>
</section>

<script>
async function refreshThread() {
    var el = document.getElementById('ticket-thread');
    if (!el) { return; }
    try {
        var res = await fetch('/admin/tickets/<?= (int) $ticket['id'] ?>/thread', { headers: { 'X-Requested-With': 'fetch' } });
        // 校验响应有效性，避免会话过期/出错时把登录页或错误页塞进对话区
        if (!res.ok || res.redirected) {
            Notify.error('登录状态已失效，请重新登录');
            return;
        }
        el.innerHTML = await res.text();
    } catch (err) {
        Notify.error('网络异常，对话记录未能刷新');
    }
}

function submitReply() {
    var content = val('tk-reply').trim();
    if (content.length < 1) { Notify.warning('请填写回复内容'); return; }
    admConfirm('确定发送该回复吗？用户会在自己的工单对话中看到。', async function () {
        var res = await App.post('/admin/tickets/reply', { id: <?= (int) $ticket['id'] ?>, content: content });
        App.handle(res, '回复已发送');
        if (res.success) {
            document.getElementById('tk-reply').value = '';
            refreshThread();
        }
    }, { title: '发送回复', confirmText: '确认发送' });
}

function closeTicket() {
    admConfirm('确定关闭该工单吗？关闭后用户不可继续回复。', async function () {
        var res = await App.post('/admin/tickets/close', { id: <?= (int) $ticket['id'] ?> });
        App.handle(res, '工单已关闭');
        if (res.success) {
            var badge = document.querySelector('.adm-toolbar .badge');
            if (badge) { badge.className = 'badge badge--gray'; badge.textContent = '已关闭'; }
            document.querySelectorAll('[onclick^="closeTicket"]').forEach(function (b) { b.remove(); });
        }
    }, { title: '关闭工单', confirmText: '确认关闭', danger: true });
}
</script>
