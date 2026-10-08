<?php declare(strict_types=1);
/**
 * @file app/Views/member/claims_table.php
 * @description 理赔列表局部渲染
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 * @var int   $status
 * @var array $types
 * @var array $statusMap
 */
$qs = static fn(int $p): string => '/user/claims?' . http_build_query(array_filter(['status' => $status, 'page' => $p], static fn($v): bool => $v !== '' && $v !== -1));
?>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>理赔单号</th><th>类型</th><th>关联运单</th><th>申请金额</th><th>核定金额</th><th>状态</th><th class="nowrap">提交时间</th><th class="nowrap">操作</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="8"><div class="empty-state">暂无理赔记录。包裹破损、丢失、缺件等情况可在此提交理赔申请。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="mono"><?= View::e($r['claim_no']) ?></td>
            <td><span class="badge badge--gray"><?= View::e($types[(int) $r['claim_type']] ?? '其他') ?></span></td>
            <td class="mono"><?= View::e((string) ($r['waybill_no'] ?? '—')) ?></td>
            <td class="nowrap">¥<?= number_format((float) $r['amount'], 2) ?></td>
            <td class="nowrap"><?= $r['approved_amount'] !== null ? '¥' . number_format((float) $r['approved_amount'], 2) : '—' ?></td>
            <td><span class="badge badge--<?= Status::claimTone((int) $r['status']) ?>"><?= View::e($statusMap[(int) $r['status']] ?? '未知') ?></span></td>
            <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
            <td class="nowrap"><a class="btn btn--ghost btn--sm" href="/user/claims/<?= (int) $r['id'] ?>">查看详情</a></td>
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
