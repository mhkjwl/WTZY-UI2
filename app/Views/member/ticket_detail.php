<?php declare(strict_types=1);
/**
 * @file app/Views/member/ticket_detail.php
 * @description 会员中心：工单详情与对话
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $ticket
 * @var array $replies
 * @var array $categories
 * @var array $statusMap
 */
$closed = (int) $ticket['status'] === 3;
?>
<div class="adm-toolbar">
    <a class="btn btn--ghost btn--sm" href="/user/tickets">‹ 返回列表</a>
    <span class="badge badge--<?= Status::ticketTone((int) $ticket['status']) ?>"><?= View::e($statusMap[(int) $ticket['status']] ?? '未知') ?></span>
    <span class="badge badge--gray"><?= View::e($categories[(int) $ticket['category']] ?? '其他') ?></span>
    <?php if (!$closed): ?>
        <button class="btn btn--ghost btn--sm" style="margin-left:auto" onclick="closeTicket()">关闭工单</button>
    <?php endif; ?>
</div>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">工单信息</span><span class="rule"></span></h3>
    <div class="hero-address">
        <div class="hero-address__row"><span>工单号</span><b class="v-code mono"><?= View::e($ticket['ticket_no']) ?></b></div>
        <div class="hero-address__row"><span>标题</span><b><?= View::e($ticket['title']) ?></b></div>
        <div class="hero-address__row"><span>关联运单</span><b class="v-code mono"><?= View::e((string) ($ticket['waybill_no'] ?? '—')) ?></b></div>
        <div class="hero-address__row"><span>处理客服</span><b><?= View::e((string) ($ticket['admin_name'] ?? '待分配')) ?></b></div>
        <div class="hero-address__row"><span>提交时间</span><b><?= View::e(substr((string) $ticket['created_at'], 0, 16)) ?></b></div>
        <div class="hero-address__row"><span>最后回复</span><b><?= View::e($ticket['last_reply_at'] ? substr((string) $ticket['last_reply_at'], 0, 16) : '暂无') ?></b></div>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--violet">对话记录</span><span class="rule"></span></h3>
    <div id="ticket-thread">
        <?= View::partial('partials/ticket_thread', ['replies' => $replies, 'viewer' => 'user']) ?>
    </div>

    <?php if ($closed): ?>
        <div class="empty-state" style="margin-top:14px">工单已关闭。如问题仍未解决，请<a href="/user/tickets/create">新建工单</a>。</div>
    <?php else: ?>
        <div class="form-field" style="margin-top:16px">
            <label class="form-label">补充说明</label>
            <textarea class="form-input" id="tk-reply" rows="4" maxlength="2000" placeholder="补充情况或追问，客服会尽快回复"></textarea>
        </div>
        <button class="btn btn--primary" type="button" onclick="submitReply()">发送回复</button>
    <?php endif; ?>
</section>

<script>
async function refreshThread() {
    var el = document.getElementById('ticket-thread');
    if (!el) { return; }
    try {
        var res = await fetch('/user/tickets/<?= (int) $ticket['id'] ?>/thread', { headers: { 'X-Requested-With': 'fetch' } });
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
    if (content.length < 2) { Notify.warning('请填写回复内容'); return; }
    admConfirm('确定提交该回复吗？', async function () {
        var res = await App.post('/user/tickets/reply', { id: <?= (int) $ticket['id'] ?>, content: content });
        App.handle(res, '回复已提交');
        if (res.success) {
            document.getElementById('tk-reply').value = '';
            refreshThread();
        }
    }, { title: '提交回复', confirmText: '确认提交' });
}

function closeTicket() {
    admConfirm('确定关闭该工单吗？关闭后不可继续回复。', async function () {
        var res = await App.post('/user/tickets/close', { id: <?= (int) $ticket['id'] ?> });
        App.handle(res, '工单已关闭');
        if (res.success) {
            var badge = document.querySelector('.adm-toolbar .badge');
            if (badge) { badge.className = 'badge badge--gray'; badge.textContent = '已关闭'; }
            document.querySelectorAll('[onclick^="closeTicket"]').forEach(function (b) { b.remove(); });
        }
    }, { title: '关闭工单', confirmText: '确认关闭', danger: true });
}
</script>
