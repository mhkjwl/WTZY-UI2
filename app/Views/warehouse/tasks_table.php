<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/tasks_table.php
 * @description 作业任务列表局部渲染
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var int    $status
 * @var int    $assignee
 * @var int    $type
 * @var string $keyword
 * @var array  $perms
 */
$qs = static fn(int $p): string => '/warehouse/tasks?' . http_build_query(array_filter([
    'status' => $status, 'assignee' => $assignee, 'type' => $type, 'kw' => $keyword, 'page' => $p,
], static fn($v): bool => $v !== '' && (int) $v !== -1 && (int) $v !== 0));
$statusTone = [0 => 'orange', 1 => 'blue', 2 => 'green', 3 => 'gray'];
?>
<div class="table-scroll">
<table class="table">
    <thead>
    <tr>
        <th>任务号</th><th>作业类型</th><th>目标单号</th><th>执行人</th><th>优先级</th>
        <th>状态</th><th>指派人</th><th class="nowrap">指派时间</th><th class="nowrap">操作</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="9"><div class="empty-state">暂无作业任务。点「指派任务」把入库、打包、出库等作业派给操作员。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php $st = (int) $r['status']; ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td class="mono"><?= View::e($r['task_no']) ?></td>
            <td><?= View::badge($types[(int) $r['task_type']] ?? '作业', 'indigo') ?></td>
            <td class="mono"><?= View::e($r['target_no']) ?></td>
            <td><?= View::e((string) ($r['assign_name'] ?? '—')) ?></td>
            <td><?= (int) $r['priority'] === 1 ? View::badge('加急', 'red') : View::badge('普通', 'gray') ?></td>
            <td>
                <?= View::badge($statuses[$st] ?? '—', $statusTone[$st] ?? 'gray') ?>
                <?php if ($st === 2 && !empty($r['finish_remark'])): ?><br><small><?= View::e((string) $r['finish_remark']) ?></small><?php endif; ?>
            </td>
            <td><?= View::e((string) ($r['assign_by_name'] ?? '—')) ?></td>
            <td class="nowrap"><?= View::e(substr((string) $r['assigned_at'], 0, 16)) ?></td>
            <td class="nowrap">
                <?php if (!empty($perms['wh.task.assign']) && in_array($st, [0, 1], true)): ?>
                    <button class="btn btn--ghost btn--sm"
                            onclick="admAction('/warehouse/tasks/cancel', {id: <?= (int) $r['id'] ?>}, '确定取消任务 <?= View::e($r['task_no']) ?> 吗？', {confirmText: '取消任务', danger: true})">取消</button>
                <?php else: ?>
                    <span class="msg-item__read">—</span>
                <?php endif; ?>
            </td>
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
