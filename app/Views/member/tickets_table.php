<?php declare(strict_types=1);
/**
 * @file app/Views/member/tickets_table.php
 * @description 工单列表局部渲染
 * @author AI
 * @version 0.6.2
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
 * @var array $categories
 * @var array $statusMap
 */
$qs = static fn(int $p): string => '/user/tickets?' . http_build_query(array_filter(['status' => $status, 'page' => $p], static fn($v): bool => $v !== '' && $v !== -1));
?>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>工单号</th><th>类型</th><th>标题</th><th>关联运单</th><th>客服</th><th>状态</th><th class="nowrap">提交时间</th><th class="nowrap">操作</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="8"><div class="empty-state">暂无工单。遇到包裹异常、费用疑问等问题可提交工单，客服会尽快跟进。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="mono"><?= View::e($r['ticket_no']) ?></td>
            <td><span class="badge badge--gray"><?= View::e($categories[(int) $r['category']] ?? '其他') ?></span></td>
            <td><?= View::e($r['title']) ?></td>
            <td class="mono"><?= View::e((string) ($r['waybill_no'] ?? '—')) ?></td>
            <td><?= View::e((string) ($r['admin_name'] ?? '待分配')) ?></td>
            <td><span class="badge badge--<?= Status::ticketTone((int) $r['status']) ?>"><?= View::e($statusMap[(int) $r['status']] ?? '未知') ?></span></td>
            <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
            <td class="nowrap"><a class="btn btn--ghost btn--sm" href="/user/tickets/<?= (int) $r['id'] ?>">查看对话</a></td>
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
