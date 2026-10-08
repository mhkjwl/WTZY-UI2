<?php declare(strict_types=1);
/**
 * @file app/Views/member/waybills_table.php
 * @description 转运订单列表局部渲染
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 * @var int    $status
 * @var float  $balance
 */
$qs = static fn(int $p): string => '/user/waybills?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $p],
    static fn($v): bool => $v !== '' && $v !== -1
));
?>
<div class="table-scroll">
<table class="table">
    <thead>
    <tr>
        <th>运单号</th><th>方式</th><th>收件人</th><th>渠道</th><th>金额</th>
        <th>状态</th><th>清关</th><th class="nowrap">创建时间</th><th class="nowrap">操作</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="9"><div class="empty-state">还没有转运订单。包裹认领后可在「新建转运」提交。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php $payable = (int) $r['status'] === 2; ?>
        <?php $due = (float) ($r['due_amount'] ?? 0); ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td class="mono"><a href="/user/waybills/<?= (int) $r['id'] ?>"><?= View::e($r['waybill_no']) ?></a></td>
            <td><?= [1 => '原箱', 2 => '合箱', 3 => '分箱'][(int) $r['bill_type']] ?? '—' ?><br><small><?= (int) $r['package_count'] ?> 件</small></td>
            <td><?= View::e($r['receiver_name']) ?><br><small><?= View::e((string) $r['dest_country']) ?></small></td>
            <td><?= View::e((string) ($r['channel_name'] ?? '—')) ?></td>
            <td class="nowrap"><?= View::e((string) $r['currency']) ?> <?= number_format((float) $r['total_amount'], 2) ?>
                <?php if ($due > 0): ?><br><small style="color:var(--c-red)">待补 ¥<?= number_format($due, 2) ?></small><?php endif; ?></td>
            <td><span class="badge badge--<?= Status::waybillTone((int) $r['status']) ?>"><?= View::e($due > 0 ? '待补款' : Status::waybill((int) $r['status'])) ?></span></td>
            <td><span class="badge badge--<?= Status::clearTone((int) $r['clear_status']) ?>"><?= View::e(Status::clear((int) $r['clear_status'])) ?></span></td>
            <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
            <td class="nowrap">
                <?php if ($payable): ?>
                    <button class="btn btn--primary btn--sm"
                            onclick="payWaybill(<?= (int) $r['id'] ?>, '<?= View::e($r['waybill_no']) ?>', <?= $due > 0 ? $due : (float) $r['total_amount'] ?>, <?= (float) $balance ?>)"><?= $due > 0 ? '补缴差额' : '支付' ?></button>
                <?php endif; ?>
                <a class="btn btn--ghost btn--sm" href="/user/waybills/<?= (int) $r['id'] ?>">详情</a>
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
