<?php declare(strict_types=1);
/**
 * @file app/Views/member/packages_table.php
 * @description 我的包裹列表局部渲染
 * @author AI
 * @version 0.6.1
 * @date 2026-09-15
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
 * @var array  $pendingDisposals package_id => 进行中的处置申请
 */
$qs = static fn(int $p): string => '/user/packages?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $p],
    static fn($v): bool => $v !== '' && $v !== -1
));
?>
<div class="table-scroll">
<table class="table">
    <thead>
    <tr>
        <th>包裹编号</th><th>商品</th><th>仓库</th><th>重量</th><th>状态</th>
        <th class="nowrap">到仓时间</th><th class="nowrap">操作</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="7"><div class="empty-state">暂无包裹。先在这里预报，包裹到仓后即可查看与认领。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php
        $s = (int) $r['status'];
        $canClaim = in_array($s, [2, 3], true);
        $canTransfer = $s === 4;
        $canDispose = $s >= 4 && $s <= 9;
        $pending = $pendingDisposals[(int) $r['id']] ?? null;
        if ($pending !== null) {
            // 有进行中的申请时，处置入口变为取消，不可再叠加新申请
            $canDispose = false;
        }
        ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td class="mono"><a href="/user/packages/<?= (int) $r['id'] ?>"><?= View::e($r['package_no']) ?></a></td>
            <td><?= View::e($r['item_name']) ?> ×<?= (int) $r['item_qty'] ?><br>
                <small class="mono"><?= View::e($r['tracking_no'] ?: '—') ?></small></td>
            <td><?= View::e((string) ($r['warehouse_name'] ?? '—')) ?></td>
            <td class="nowrap"><?= $r['weight'] !== null ? number_format((float) $r['weight'], 3) . ' kg' : '—' ?></td>
            <td>
                <span class="badge badge--<?= Status::packageTone($s) ?>"><?= View::e(Status::package($s)) ?></span>
                <?php if ($pending !== null): ?>
                    <span class="badge badge--<?= Status::abnormalTone((int) $pending['abnormal_type']) ?>"><?= Status::ABNORMAL_TYPE[(int) $pending['abnormal_type']] ?? '处置申请中' ?> · 审核中</span>
                <?php elseif ((int) $r['is_abnormal'] === 1): ?>
                    <span class="badge badge--orange">异常</span>
                <?php endif; ?>
            </td>
            <td class="nowrap"><?= View::e($r['inbound_at'] ? substr((string) $r['inbound_at'], 0, 16) : '—') ?></td>
            <td class="nowrap">
                <?php if ($canTransfer): ?>
                    <a class="btn btn--primary btn--sm" href="/user/waybills/create?package=<?= (int) $r['id'] ?>">提交转运</a>
                <?php elseif ($canClaim): ?>
                    <button class="btn btn--primary btn--sm"
                            onclick="admAction('/user/packages/claim', {id: <?= (int) $r['id'] ?>}, '确认认领该包裹？认领后可用于提交转运。', {confirmText: '确认认领'})">认领</button>
                <?php endif; ?>
                <?php if ($pending !== null): ?>
                    <button class="btn btn--ghost btn--sm" data-pending
                            onclick="cancelDisposal(<?= (int) $r['id'] ?>, '<?= View::e(Status::ABNORMAL_TYPE[(int) $pending['abnormal_type']] ?? '处置') ?>')">取消申请</button>
                <?php elseif ($canDispose): ?>
                    <button class="btn btn--ghost btn--sm" data-dispose
                            onclick="openDisposal(<?= (int) $r['id'] ?>, '<?= View::e($r['package_no']) ?>')">退运/销毁</button>
                <?php endif; ?>
                <a class="btn btn--ghost btn--sm" href="/user/packages/<?= (int) $r['id'] ?>">详情</a>
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
