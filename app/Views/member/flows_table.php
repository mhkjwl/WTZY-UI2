<?php declare(strict_types=1);
/**
 * @file app/Views/member/flows_table.php
 * @description 资金流水局部渲染
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
 * @var int   $flowType
 */
$qs = static fn(int $p): string => '/user/finance/flows?' . http_build_query(array_filter(['type' => $flowType, 'page' => $p], static fn($v): bool => $v !== '' && $v !== -1));
?>
<div class="table-scroll">
<table class="table">
    <thead>
    <tr><th>流水号</th><th class="nowrap">时间</th><th>类型</th><th>方向</th><th>金额</th><th>变动前 → 后</th><th>关联单号</th><th>说明</th></tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="8"><div class="empty-state">暂无资金流水。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php $isIncome = (int) $r['direction'] === 1; ?>
        <tr>
            <td class="mono"><?= View::e($r['flow_no']) ?></td>
            <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
            <td><span class="badge badge--<?= $isIncome ? 'green' : 'orange' ?>"><?= View::e(Status::FLOW_TYPE[(int) $r['flow_type']] ?? '其他') ?></span></td>
            <td><?= View::badge(Status::FLOW_DIRECTION[(int) $r['direction']] ?? '—', (int) $r['direction'] === 1 ? 'green' : 'red') ?></td>
            <td class="nowrap" style="color:<?= $isIncome ? '#16a34a' : '#dc2626' ?>;font-weight:600">
                <?= $isIncome ? '+' : '-' ?> ¥<?= number_format((float) $r['amount'], 2) ?>
            </td>
            <td class="nowrap">¥<?= number_format((float) $r['balance_before'], 2) ?> → ¥<?= number_format((float) $r['balance_after'], 2) ?></td>
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
