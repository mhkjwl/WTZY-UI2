<?php declare(strict_types=1);
/**
 * @file app/Views/partials/ticket_thread.php
 * @description 工单对话记录（用户端与后台共用，按回复方区分头像、标签与配色）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 *
 * 两端用同一份模板，保证后台与用户端看到的对话类型、顺序、标识完全一致。
 */

use App\Core\View;

/**
 * @var array  $replies 回复列表（reply_side：1=用户 2=客服）
 * @var string $viewer  查看方：user=用户端（本人标为「我」） staff=后台（客服本人标为「我」）
 */
$viewer = $viewer ?? 'user';
$sides = [
    1 => ['icon' => '👤', 'label' => '用户', 'badge' => 'gray',  'tone' => 'user'],
    2 => ['icon' => '🎧', 'label' => '客服', 'badge' => 'blue',  'tone' => 'staff'],
];
?>
<?php if ($replies === []): ?>
    <div class="empty-state">暂无对话记录。</div>
<?php else: ?>
    <ul class="msg-list msg-list--thread">
        <?php foreach ($replies as $r): ?>
            <?php
            $side = (int) $r['reply_side'];
            $meta = $sides[$side] ?? $sides[1];
            $isMine = ($viewer === 'user' && $side === 1) || ($viewer === 'staff' && $side === 2);
            $who = $side === 2 && !empty($r['admin_name']) ? (string) $r['admin_name'] : $meta['label'];
            ?>
            <li class="msg-item msg-item--<?= $meta['tone'] ?><?= $isMine ? ' is-mine' : '' ?>">
                <div class="msg-item__icon"><?= $isMine ? '🙋' : $meta['icon'] ?></div>
                <div class="msg-item__main">
                    <div class="msg-item__head">
                        <span class="msg-item__title"><?= View::e($isMine ? '我' : $who) ?></span>
                        <span class="badge badge--<?= $meta['badge'] ?>"><?= View::e($meta['label']) ?></span>
                    </div>
                    <p class="msg-item__body"><?= nl2br(View::e($r['content'])) ?></p>
                    <div class="msg-item__foot">
                        <span class="msg-item__time"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></span>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
