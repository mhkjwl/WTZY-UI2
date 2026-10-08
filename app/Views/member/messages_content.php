<?php declare(strict_types=1);
/**
 * @file app/Views/member/messages_content.php
 * @description 会员中心消息列表局部内容（检索/分页后仅替换本区域）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 */

$friendlyTime = static function (string $value): string {
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }
    $today = strtotime('today');
    if ($ts >= $today) {
        return '今天 ' . date('H:i', $ts);
    }
    if ($ts >= $today - 86400) {
        return '昨天 ' . date('H:i', $ts);
    }
    return date('m-d H:i', $ts);
};
$typeMeta = [1 => '📩', 2 => '📱', 3 => '✉️', 4 => '🔔'];
?>
<div class="table-wrap" data-table-wrap data-url="/user/messages?page=<?= (int) $page ?>">
    <ul class="msg-list">
        <?php if ($rows === []): ?>
            <li><div class="empty-state">暂无消息。全站公告发布后与系统通知都会送达这里。</div></li>
        <?php endif; ?>
        <?php foreach ($rows as $m): ?>
            <?php
            $isBroadcast = $m['user_id'] === null;
            $isUnread = !$isBroadcast && (int) $m['is_read'] === 0;
            ?>
            <li class="msg-item<?= $isUnread ? ' is-unread' : '' ?>" data-id="<?= (int) $m['id'] ?>">
                <div class="msg-item__icon"><?= $isBroadcast ? '📢' : ($typeMeta[(int) $m['msg_type']] ?? '📩') ?></div>
                <div class="msg-item__main">
                    <div class="msg-item__head">
                        <span class="msg-item__title"><?= View::e($m['title']) ?></span>
                        <?php if ($isBroadcast): ?>
                            <span class="badge badge--blue">全站公告</span>
                        <?php endif; ?>
                        <?php if ($isUnread): ?><span class="msg-item__dot" title="未读"></span><?php endif; ?>
                    </div>
                    <p class="msg-item__body"><?= nl2br(View::e($m['content'])) ?></p>
                    <div class="msg-item__foot">
                        <span class="msg-item__time"><?= View::e($friendlyTime((string) $m['updated_at'])) ?></span>
                        <?php if ($isUnread): ?>
                            <button class="btn btn--ghost btn--sm" onclick="markRead(<?= (int) $m['id'] ?>, this)">标记已读</button>
                        <?php elseif (!$isBroadcast): ?>
                            <span class="msg-item__read">已读</span>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($pages > 1): ?>
        <nav class="pager">
            <?php if ($page > 1): ?><a href="javascript:admTable('/user/messages?page=<?= $page - 1 ?>')">‹ 上一页</a><?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:admTable('/user/messages?page=<?= $i ?>')"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a href="javascript:admTable('/user/messages?page=<?= $page + 1 ?>')">下一页 ›</a><?php endif; ?>
            <span class="is-disabled">共 <?= (int) $total ?> 条</span>
        </nav>
    <?php endif; ?>
</div>
