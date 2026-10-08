<?php declare(strict_types=1);
/**
 * @file app/Views/admin/warehouse_content.php
 * @description 仓库管理局部内容：仓库表 + 库位表（增改/启停/新增库位后整区刷新）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $warehouses
 * @var array $locations
 * @var int $locTotal
 * @var int $locPage
 * @var int $locPages
 * @var int $wid
 * @var array $perms
 */
$locTypes = [1 => '入库暂存', 2 => '常规存储', 3 => '打包区', 4 => '出库暂存', 5 => '异常件区'];
$locStatuses = [0 => '停用', 1 => '空闲', 2 => '占用'];
$locStatusTone = [0 => 'gray', 1 => 'green', 2 => 'orange'];
?>
<h3 class="section-head" style="justify-content:flex-start"><span class="badge badge--blue">仓库列表</span><span class="rule"></span>
    <?php if (!empty($perms['warehouse.config'])): ?>
        <button class="btn btn--primary" onclick="openWh(0)">+ 新建仓库</button>
    <?php endif; ?>
</h3>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>编号</th><th>名称</th><th>代码</th><th>地址</th><th>时区</th><th>免费仓储</th><th>库位占用</th><th>状态</th><th class="nowrap">操作</th></tr></thead>
    <tbody>
    <?php if ($warehouses === []): ?>
        <tr><td colspan="9"><div class="empty-state">还没有仓库，点击右上角新建第一个海外仓。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($warehouses as $w): ?>
        <tr>
            <td class="mono"><?= View::e($w['warehouse_no']) ?></td>
            <td><b><?= View::e($w['name']) ?></b></td>
            <td><span class="badge badge--blue"><?= View::e($w['code']) ?></span></td>
            <td class="nowrap"><?= View::e($w['country']) ?> · <?= View::e($w['city']) ?> <?= View::e($w['address']) ?></td>
            <td class="nowrap"><?= View::e($w['timezone']) ?></td>
            <td><?= (int) $w['free_storage_days'] ?> 天</td>
            <td><?= (int) $w['occupied'] ?> / <?= (int) $w['location_total'] ?></td>
            <td><span class="badge badge--<?= (int) $w['status'] === 1 ? 'green' : 'gray' ?>"><?= (int) $w['status'] === 1 ? '启用' : '停用' ?></span></td>
            <td class="nowrap">
                <button class="btn btn--ghost btn--sm" onclick='openWh(<?= (int) $w['id'] ?>, <?= json_encode($w, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>编辑</button>
                <?php if (!empty($perms['warehouse.config'])): ?>
                    <button class="btn btn--ghost btn--sm" onclick="admAction('/admin/warehouse/toggle', {id: <?= (int) $w['id'] ?>}, '确定切换该仓库启停状态吗？')"><?= (int) $w['status'] === 1 ? '停用' : '启用' ?></button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<h3 class="section-head" style="justify-content:flex-start;margin-top:24px"><span class="badge badge--violet">库位</span><span class="rule"></span>
    <select class="form-input" id="loc-wid" style="min-width:150px" onchange="admSearch('<?= View::e($base) ?>', {wid: this.value, lpage: 1})">
        <option value="0">全部仓库</option>
        <?php foreach ($warehouses as $w): ?>
            <option value="<?= (int) $w['id'] ?>" <?= $wid === (int) $w['id'] ? 'selected' : '' ?>><?= View::e($w['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn--primary" onclick="openLocAdd()">+ 新增库位</button>
</h3>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>仓库</th><th>库位编码</th><th>类型</th><th>状态</th><th>占用包裹</th><th>更新时间</th><th class="nowrap">操作</th></tr></thead>
    <tbody>
    <?php if ($locations === []): ?>
        <tr><td colspan="7"><div class="empty-state">暂无库位。仓库启用后需先规划库位（区-巷道-货架-层位），PDA 上架才能扫码入库。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($locations as $l): ?>
        <?php $ls = (int) $l['status']; ?>
        <tr data-loc="<?= (int) $l['id'] ?>">
            <td><?= View::e($l['warehouse_name']) ?></td>
            <td class="mono"><b><?= View::e($l['code']) ?></b></td>
            <td><?= View::e($locTypes[(int) $l['type']] ?? '未知') ?></td>
            <td><span class="badge badge--<?= $locStatusTone[$ls] ?? 'gray' ?>"><?= View::e($locStatuses[$ls] ?? '未知') ?></span></td>
            <td><?= $l['package_no'] !== null && $l['package_no'] !== '' ? '<span class="mono">' . View::e((string) $l['package_no']) . '</span>' : '—' ?></td>
            <td class="nowrap"><?= View::e($l['updated_at']) ?></td>
            <td class="nowrap">
                <?php if (!empty($perms['warehouse.config'])): ?>
                    <button class="btn btn--ghost btn--sm" onclick='openLocEdit(<?= json_encode($l, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>编辑</button>
                <?php endif; ?>
                <button class="btn btn--ghost btn--sm" onclick='openLocDetail(<?= json_encode($l, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>详情</button>
                <?php if (!empty($perms['warehouse.config'])): ?>
                    <?php if ($ls === 2): ?>
                        <span class="form-hint">占用中</span>
                    <?php else: ?>
                        <button class="btn btn--ghost btn--sm" onclick='toggleLoc(<?= (int) $l['id'] ?>, <?= $ls ?>, <?= json_encode((string) $l['code'], JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'><?= $ls === 1 ? '停用' : '启用' ?></button>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($locPages > 1): ?>
    <nav class="pager">
        <?php for ($i = max(1, $locPage - 2); $i <= min($locPages, $locPage + 2); $i++): ?>
            <a class="<?= $i === $locPage ? 'is-current' : '' ?>" href="javascript:admSearch('<?= View::e($base) ?>', {wid: <?= (int) $wid ?>, lpage: <?= $i ?>})"><?= $i ?></a>
        <?php endfor; ?>
        <span class="is-disabled">共 <?= (int) $locTotal ?> 个</span>
    </nav>
<?php endif; ?>
