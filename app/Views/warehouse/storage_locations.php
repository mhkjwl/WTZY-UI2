<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/storage_locations.php
 * @description 仓储管理局部：库位占用一览（类型/状态筛选 + 数字翻页，只替换本区块）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-16
 */

use App\Core\View;

/**
 * @var array  $locationList
 * @var int    $locTotal
 * @var int    $locPage
 * @var int    $locPages
 * @var int    $locTypeFilter
 * @var int    $locStatusFilter
 * @var string $locBase
 * @var array  $locTypes
 * @var array  $locStatus
 */
$filtered = $locTypeFilter !== 0 || $locStatusFilter >= 0;
?>
<?php if ($locationList === []): ?>
    <div class="empty-state">
        <?= $filtered ? '没有符合筛选条件的库位。' : '本仓尚未配置库位。库位在「管理后台 → 仓库管理 → 库位管理」中维护。' ?>
    </div>
<?php else: ?>
    <div class="adm-toolbar" style="margin-bottom:12px">
        <select class="form-input" id="loc-type" onchange="locSearch({ltype: this.value, lstatus: val('loc-status')})">
            <option value="0">全部类型</option>
            <?php foreach ($locTypes as $code => $label): ?>
                <option value="<?= (int) $code ?>" <?= $locTypeFilter === (int) $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-input" id="loc-status" onchange="locSearch({ltype: val('loc-type'), lstatus: this.value})">
            <option value="-1">全部状态</option>
            <?php foreach ($locStatus as $code => $label): ?>
                <option value="<?= (int) $code ?>" <?= $locStatusFilter === (int) $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="form-hint">点击行可查看库位详情</span>
    </div>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>库位编码</th><th>类型</th><th>状态</th><th>当前包裹</th><th>商品</th><th class="nowrap">更新时间</th><th class="nowrap">操作</th></tr></thead>
            <tbody id="loc-tbody">
            <?php foreach ($locationList as $l): ?>
                <tr data-type="<?= (int) $l['type'] ?>" data-status="<?= (int) $l['status'] ?>"
                    data-package-id="<?= (int) ($l['package_id'] ?? 0) ?>"
                    style="cursor:pointer" onclick="showLocDetail(this)">
                    <td class="mono"><?= View::e($l['code']) ?></td>
                    <td><?= View::badge($locTypes[(int) $l['type']] ?? '库位', 'indigo') ?></td>
                    <td><?= View::badge($locStatus[(int) $l['status']] ?? '—', (int) $l['status'] === 2 ? 'orange' : ((int) $l['status'] === 1 ? 'green' : 'gray')) ?></td>
                    <td class="mono"><?= View::e((string) ($l['package_no'] ?? '') ?: '—') ?></td>
                    <td><?= View::e((string) ($l['item_name'] ?? '') ?: '—') ?></td>
                    <td class="nowrap"><?= View::e(substr((string) $l['updated_at'], 0, 16)) ?></td>
                    <td class="nowrap">
                        <a href="javascript:void(0)" class="btn btn--ghost btn--sm"
                           onclick="event.stopPropagation();showLocDetail(this)">详情</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($locPages > 1): ?>
        <nav class="pager">
            <?php if ($locPage > 1): ?>
                <a href="javascript:locSearch({ltype: <?= (int) $locTypeFilter ?>, lstatus: <?= (int) $locStatusFilter ?>, page: <?= (int) $locPage - 1 ?>})">‹ 上一页</a>
            <?php endif; ?>
            <?php for ($i = max(1, $locPage - 2); $i <= min($locPages, $locPage + 2); $i++): ?>
                <a class="<?= $i === $locPage ? 'is-current' : '' ?>"
                   href="javascript:locSearch({ltype: <?= (int) $locTypeFilter ?>, lstatus: <?= (int) $locStatusFilter ?>, page: <?= (int) $i ?>})"><?= (int) $i ?></a>
            <?php endfor; ?>
            <?php if ($locPage < $locPages): ?>
                <a href="javascript:locSearch({ltype: <?= (int) $locTypeFilter ?>, lstatus: <?= (int) $locStatusFilter ?>, page: <?= (int) $locPage + 1 ?>})">下一页 ›</a>
            <?php endif; ?>
            <span class="is-disabled">共 <?= (int) $locTotal ?> 个</span>
        </nav>
    <?php endif; ?>
<?php endif; ?>
