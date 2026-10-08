<?php declare(strict_types=1);
/**
 * @file app/Views/admin/customs_table.php
 * @description 报关明细局部渲染
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $rows
 * @var int $page
 * @var int $pages
 * @var string $keyword
 * @var string $base
 * @var array $perms
 */
$qs = static fn(int $p): string => '/admin/customs?' . http_build_query(array_filter(['kw' => $keyword, 'page' => $p], static fn($v): bool => $v !== ''));
?>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>运单号</th><th>目的国</th><th>运单状态</th><th>清关状态</th><th>申报品名（中/英）</th><th>HS 编码</th><th>数量</th><th>申报单价</th><th>品牌</th><th class="nowrap">清关操作</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="10"><div class="empty-state">暂无报关明细。用户提交转运订单并填写清关信息后，这里将汇总全部申报数据。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="mono"><b><?= View::e($r['waybill_no']) ?></b></td>
            <td><?= View::e($r['dest_country']) ?></td>
            <td><span class="badge badge--<?= Status::waybillTone((int) $r['waybill_status']) ?>"><?= View::e(Status::waybill((int) $r['waybill_status'])) ?></span></td>
            <td><span class="badge badge--<?= Status::clearTone((int) $r['clear_status']) ?>"><?= View::e(Status::clear((int) $r['clear_status'])) ?></span></td>
            <td><?= View::e($r['name_cn']) ?><br><small><?= View::e($r['name_en']) ?></small></td>
            <td class="mono"><?= View::e($r['hs_code'] ?: '—') ?></td>
            <td><?= (int) $r['qty'] ?> <?= View::e($r['unit']) ?></td>
            <td class="nowrap"><?= View::e((string) $r['unit_price']) ?> <?= View::e($r['currency']) ?></td>
            <td><?= View::e($r['brand'] ?: '—') ?></td>
            <td class="nowrap">
                <select class="form-input" style="min-width:110px" onchange="setClear(<?= (int) $r['waybill_id'] ?>, this.value)">
                    <?php foreach (Status::CLEAR as $code => $label): ?>
                        <option value="<?= $code ?>" <?= (int) $r['clear_status'] === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
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
<script>
function setClear(waybillId, value) {
    admAction('/admin/customs/clear-status', { waybill_id: waybillId, clear_status: value },
        '确认更新该运单的清关状态吗？', { title: '清关状态变更' });
}
</script>
