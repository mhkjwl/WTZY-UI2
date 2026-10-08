<?php declare(strict_types=1);
/**
 * @file app/Views/member/points_table.php
 * @description 积分流水局部渲染
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 */
$qs = static fn(int $p): string => '/user/member/points?page=' . $p;
?>
<div class="table-scroll">
<table class="table">
    <thead><tr><th class="nowrap">时间</th><th>业务类型</th><th>变动</th><th>变动后余额</th><th>关联单号</th><th>说明</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="6"><div class="empty-state">暂无积分流水。下单、邀请好友等操作都会记录在这里。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php $gain = (int) $r['change_amount'] > 0; ?>
        <tr>
            <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
            <td><span class="badge badge--<?= $gain ? 'green' : 'orange' ?>"><?= View::e(Status::POINTS_TYPE[(int) $r['biz_type']] ?? '其他') ?></span></td>
            <td class="nowrap" style="color:<?= $gain ? '#16a34a' : '#dc2626' ?>;font-weight:600">
                <?= $gain ? '+' : '' ?><?= (int) $r['change_amount'] ?>
            </td>
            <td class="nowrap"><?= (int) $r['balance_after'] ?></td>
            <td class="mono"><?= View::e((string) ($r['biz_no'] ?? '—')) ?></td>
            <td><?= View::e((string) ($r['remark'] ?? '—')) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($pages > 1): ?>
    <nav class="pager">
        <?php if ($page > 1): ?><a href="javascript:admTable('<?= View::e($qs($page - 1)) ?>')">‹ 上一页</a><?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:admTable('<?= View::e($qs($i)) ?>')"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a href="javascript:admTable('<?= View::e($qs($page + 1)) ?>')">下一页 ›</a><?php endif; ?>
        <span class="is-disabled">共 <?= (int) $total ?> 条</span>
    </nav>
<?php endif; ?>
