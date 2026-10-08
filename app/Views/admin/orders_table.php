<?php declare(strict_types=1);
/**
 * @file app/Views/admin/orders_table.php
 * @description 运单列表局部渲染
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var string $keyword
 * @var int $status
 * @var string $base
 * @var array $perms
 */
$qs = static fn(int $p): string => '/admin/orders?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $p],
    static fn($v): bool => $v !== '' && $v !== -1
));
$billTypes = [1 => '原箱', 2 => '合箱', 3 => '分箱'];
?>
<div class="table-scroll">
<table class="table">
    <thead>
    <tr><th>运单号</th><th>方式</th><th>件数</th><th>收件人</th><th>目的地</th><th>承运商</th>
        <th>金额</th><th>状态</th><th>清关</th><th>创建时间</th><th class="nowrap">操作</th></tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="11"><div class="empty-state">暂无运单。用户提交转运订单后在此展示。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td class="mono"><b><?= View::e($r['waybill_no']) ?></b></td>
            <td><?= View::e($billTypes[(int) $r['bill_type']] ?? '—') ?></td>
            <td><?= (int) $r['package_count'] ?></td>
            <td><?= View::e($r['receiver_name']) ?></td>
            <td><?= View::e($r['dest_country']) ?></td>
            <td><?= View::e($r['carrier'] ?: '—') ?></td>
            <td class="nowrap">¥<?= number_format((float) $r['total_amount'], 2) ?>
                <?php if ((float) ($r['due_amount'] ?? 0) > 0): ?><br><small style="color:var(--c-red)">待补 ¥<?= number_format((float) $r['due_amount'], 2) ?></small><?php endif; ?></td>
            <td><span class="badge badge--<?= Status::waybillTone((int) $r['status']) ?>"><?= View::e(Status::waybill((int) $r['status'])) ?></span></td>
            <td><span class="badge badge--<?= Status::clearTone((int) $r['clear_status']) ?>"><?= View::e(Status::clear((int) $r['clear_status'])) ?></span></td>
            <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 10)) ?></td>
            <td class="nowrap">
                <a class="btn btn--ghost btn--sm" href="/admin/orders/<?= (int) $r['id'] ?>">详情</a>
                <?php if (!empty($perms['order.manage']) && (int) $r['status'] <= 2): ?>
                    <button class="btn btn--ghost btn--sm" onclick="openReason(<?= (int) $r['id'] ?>, 'cancel')">取消</button>
                <?php endif; ?>
                <?php if (!empty($perms['order.manage']) && (int) $r['status'] >= 3 && (int) $r['status'] <= 8): ?>
                    <button class="btn btn--ghost btn--sm" onclick="openReason(<?= (int) $r['id'] ?>, 'intercept')">拦截</button>
                <?php endif; ?>
                <?php if (!empty($perms['order.manage']) && (int) $r['status'] >= 5 && (int) $r['status'] <= 8): ?>
                    <button class="btn btn--primary btn--sm" onclick="openTrack(<?= (int) $r['id'] ?>, <?= (int) $r['status'] ?>, '<?= View::e($r['waybill_no']) ?>')">轨迹</button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($pages > 1): ?>
    <nav class="pager">
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:admTable('<?= View::e($qs($i)) ?>')"><?= $i ?></a>
        <?php endfor; ?>
        <span class="is-disabled">共 <?= (int) $total ?> 条</span>
    </nav>
<?php endif; ?>
