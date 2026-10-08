<?php declare(strict_types=1);
/**
 * @file app/Views/admin/packages_table.php
 * @description 后台包裹列表局部渲染（检索/分页后仅替换本区域）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-15
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
 * @var int    $status
 * @var string $base
 * @var array  $pendingDisposals package_id => 进行中的处置申请
 */
$qs = static fn(int $p): string => '/admin/packages?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $p],
    static fn($v): bool => $v !== '' && $v !== -1
));
?>
<div class="table-scroll">
<table class="table">
    <thead>
    <tr>
        <th>包裹编号</th><th>所属用户</th><th>商品</th><th>仓库</th><th>重量</th>
        <th>状态</th><th class="nowrap">到仓时间</th><th class="nowrap">操作</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="8"><div class="empty-state">暂无匹配包裹。可试试按用户编号或识别码检索该用户的全部包裹。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php
        $s = (int) $r['status'];
        $pending = $pendingDisposals[(int) $r['id']] ?? null;
        ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td class="mono"><a href="/admin/packages/<?= (int) $r['id'] ?>"><?= View::e($r['package_no']) ?></a></td>
            <td>
                <b><?= View::e((string) ($r['user_no'] ?? '—')) ?></b>
                <br><small><?= View::e(UserModel::displayName($r)) ?></small>
            </td>
            <td><?= View::e($r['item_name']) ?> ×<?= (int) $r['item_qty'] ?><br>
                <small class="mono"><?= View::e($r['tracking_no'] ?: '—') ?></small></td>
            <td><?= View::e((string) ($r['warehouse_name'] ?? '—')) ?></td>
            <td class="nowrap"><?= $r['weight'] !== null ? number_format((float) $r['weight'], 3) . ' kg' : '—' ?></td>
            <td>
                <span class="badge badge--<?= Status::packageTone($s) ?>"><?= View::e(Status::package($s)) ?></span>
                <?php if ($pending !== null): ?>
                    <br><span class="badge badge--<?= Status::abnormalTone((int) $pending['abnormal_type']) ?>"><?= Status::ABNORMAL_TYPE[(int) $pending['abnormal_type']] ?? '处置申请' ?> · 审核中</span>
                <?php elseif ((int) $r['is_abnormal'] === 1): ?>
                    <br><small>异常件</small>
                <?php endif; ?>
            </td>
            <td class="nowrap"><?= View::e($r['inbound_at'] ? substr((string) $r['inbound_at'], 0, 16) : '未到仓') ?></td>
            <td class="nowrap">
                <a class="btn btn--ghost btn--sm" href="/admin/packages/<?= (int) $r['id'] ?>">详情</a>
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
