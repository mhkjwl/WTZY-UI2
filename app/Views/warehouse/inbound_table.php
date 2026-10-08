<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/inbound_table.php
 * @description 待收货列表局部渲染（检索/分页后仅替换本区域）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

use App\Core\View;
use App\Models\UserModel;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 */
$qs = static fn(int $p): string => '/warehouse/inbound?' . http_build_query(array_filter(['kw' => $keyword, 'page' => $p]));
?>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>包裹编号</th><th>快递单号</th><th>归属用户</th><th>商品</th><th>预报时间</th><th class="nowrap">操作</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="6"><div class="empty-state">暂无待收货包裹。用户预报后此处会出现待收货记录。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td class="mono"><?= View::e($r['package_no']) ?></td>
            <td class="mono"><?= View::e($r['tracking_no'] ?: '—') ?></td>
            <td><?= View::e($r['user_no'] ?: '—') ?> <small><?= View::e(UserModel::displayName($r)) ?></small></td>
            <td><?= View::e($r['item_name']) ?> ×<?= (int) $r['item_qty'] ?></td>
            <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
            <td class="nowrap">
                <button class="btn btn--primary btn--sm"
                        onclick='fillScan(<?= json_encode((string) ($r['tracking_no'] ?: $r['package_no']), JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>收货</button>
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
<script>
/** 列表点「收货」：填入扫码框并直接查询，减少一次手工输入 */
function fillScan(value) {
    var input = document.getElementById('wh-scan-input');
    input.value = value;
    doLookup();
    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>
