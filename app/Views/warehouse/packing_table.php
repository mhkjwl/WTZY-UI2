<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/packing_table.php
 * @description 待打包运单列表局部渲染
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\UserModel;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 */
$qs = static fn(int $p): string => '/warehouse/packing?' . http_build_query(array_filter(['kw' => $keyword, 'page' => $p]));
?>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>运单号</th><th>用户</th><th>转运方式</th><th>包裹数</th><th>目的国</th><th>物流渠道</th><th>状态</th><th class="nowrap">提交时间</th><th class="nowrap">操作</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="9"><div class="empty-state">暂无待处理运单。用户提交转运订单后会出现在这里。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td class="mono"><a href="/warehouse/packing/<?= (int) $r['id'] ?>"><?= View::e($r['waybill_no']) ?></a></td>
            <td><?= View::e((string) $r['user_no']) ?> <small><?= View::e(UserModel::displayName($r)) ?></small></td>
            <td><?= View::badge([1 => '原箱转运', 2 => '合箱转运', 3 => '分箱转运'][(int) $r['bill_type']] ?? '—', 'indigo') ?></td>
            <td><?= View::badge((int) $r['package_count'] . ' 件', 'cyan') ?></td>
            <td class="mono"><?= View::e((string) $r['dest_country']) ?></td>
            <td><?= View::e((string) ($r['channel_name'] ?? '—')) ?></td>
            <td>
                <?php if ((int) $r['status'] === 2 && (float) $r['due_amount'] > 0): ?>
                    <?= View::badge('待补款 ¥' . number_format((float) $r['due_amount'], 2), 'orange') ?>
                <?php else: ?>
                    <?= View::badge(Status::waybill((int) $r['status']), Status::waybillTone((int) $r['status'])) ?>
                <?php endif; ?>
            </td>
            <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
            <td class="nowrap">
                <?php if ((int) $r['status'] === 3): ?>
                    <a class="btn btn--primary btn--sm" href="/warehouse/packing/<?= (int) $r['id'] ?>">打包作业</a>
                <?php else: ?>
                    <a class="btn btn--ghost btn--sm" href="/warehouse/packing/<?= (int) $r['id'] ?>">详情</a>
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
