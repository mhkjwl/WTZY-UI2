<?php declare(strict_types=1);
/**
 * @file app/Views/member/notices_content.php
 * @description 网站公告列表局部渲染（检索/分页后仅替换本区域）
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
 */
$typeMap = [1 => ['普通', 'gray'], 2 => ['重要', 'red']];
?>
<div class="msg-list">
    <?php if ($rows === []): ?>
        <div class="empty-state">暂无网站公告。平台发布的公告会在这里展示。</div>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php $nt = $typeMap[(int) ($r['notice_type'] ?? 1)] ?? $typeMap[1]; ?>
        <a class="msg-item" href="/user/notices/<?= (int) $r['id'] ?>" style="cursor:pointer">
            <div class="msg-item__icon"><?= $nt[0] === '重要' ? '❗' : '📢' ?></div>
            <div class="msg-item__main">
                <div class="msg-item__head">
                    <span class="badge badge--<?= $nt[1] ?>"><?= View::e($nt[0]) ?></span>
                    <span class="msg-item__title"><?= View::e((string) $r['title']) ?></span>
                </div>
                <p class="msg-item__body"><?= View::e(mb_substr((string) $r['content'], 0, 80)) ?><?= mb_strlen((string) $r['content']) > 80 ? '…' : '' ?></p>
                <div class="msg-item__foot"><span class="msg-item__time"><?= View::e(substr((string) $r['updated_at'], 0, 16)) ?></span></div>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php if ($pages > 1): ?>
    <nav class="pager">
        <?php if ($page > 1): ?><a href="javascript:admTable('/user/notices?page=<?= $page - 1 ?>')">‹ 上一页</a><?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:admTable('/user/notices?page=<?= $i ?>')"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a href="javascript:admTable('/user/notices?page=<?= $page + 1 ?>')">下一页 ›</a><?php endif; ?>
        <span class="is-disabled">共 <?= (int) $total ?> 条</span>
    </nav>
<?php endif; ?>
