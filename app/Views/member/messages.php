<?php declare(strict_types=1);
/**
 * @file app/Views/member/messages.php
 * @description 会员中心消息中心页（仅本人定向消息）：概览统计 + 局部列表
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

use App\Core\View;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 * @var int   $unread
 */
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">📨</div>
        <div><div class="astat__value"><?= (int) $total ?></div><div class="astat__label">全部消息</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">🔔</div>
        <div><div class="astat__value" id="unread-count"><?= (int) $unread ?></div><div class="astat__label">未读消息</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">📄</div>
        <div><div class="astat__value"><?= (int) $pages ?></div><div class="astat__label">总页数</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">消息列表</span><span class="rule"></span>
        <a class="btn btn--ghost" href="/user/notices" style="margin-left:auto">📢 查看网站公告</a>
        <?php if ((int) $unread > 0): ?>
            <button class="btn btn--primary" onclick="markAllRead(this)">一键全部已读</button>
        <?php endif; ?>
    </h3>
    <?= View::partial('member/messages_content', get_defined_vars()) ?>
</section>

<script>
async function markRead(id, btn) {
    btn.disabled = true;
    var res = await App.post('/user/messages/read', { id: id });
    if (res.success) {
        var item = document.querySelector('.msg-item[data-id="' + id + '"]');
        if (item) {
            item.classList.remove('is-unread');
            var dot = item.querySelector('.msg-item__dot');
            if (dot) { dot.remove(); }
            btn.replaceWith(Object.assign(document.createElement('span'), { className: 'msg-item__read', textContent: '已读' }));
        }
        var counter = document.getElementById('unread-count');
        if (counter && res.data && typeof res.data.unread === 'number') {
            counter.textContent = res.data.unread;
        }
        Notify.success('已标记为已读');
    } else {
        btn.disabled = false;
        Notify.error(res.message || '操作失败');
    }
}

function markAllRead(btn) {
    // 批量修改数据需二次确认（规范 §4.1）；已读不可撤销，故提示不可恢复
    Confirm.show({
        title: '全部标记已读',
        level: 'L1',
        message: '将把全部未读消息一次性标记为已读，该操作不可撤销。',
        confirmText: '全部标记已读',
        danger: false,
        onConfirm: async function () {
            btn.disabled = true;
            var res = await App.post('/user/messages/read-all');
            if (res.success) {
                document.querySelectorAll('.msg-item.is-unread').forEach(function (item) {
                    item.classList.remove('is-unread');
                    var dot = item.querySelector('.msg-item__dot');
                    if (dot) { dot.remove(); }
                    var b = item.querySelector('button');
                    if (b) { b.replaceWith(Object.assign(document.createElement('span'), { className: 'msg-item__read', textContent: '已读' })); }
                });
                var counter = document.getElementById('unread-count');
                if (counter && res.data && typeof res.data.unread === 'number') { counter.textContent = res.data.unread; }
                Notify.success(res.message || '已全部标记为已读');
            } else {
                Notify.error(res.message || '操作失败');
                btn.disabled = false;
            }
        }
    });
}
</script>
