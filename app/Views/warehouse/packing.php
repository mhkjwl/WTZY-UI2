<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/packing.php
 * @description 打包称重：运单列表（含已打包），进详情拣货核对 → 录入实重 → 重算运费
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

use App\Core\View;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 * @var array  $stats
 * @var array  $perms
 */
?>
<section class="stat-grid">
    <div class="stat-card stat-card--amber"><span class="stat-card__value"><?= (int) ($stats['pending'] ?? 0) ?></span><span class="stat-card__label">待打包</span></div>
    <div class="stat-card stat-card--blue"><span class="stat-card__value"><?= (int) ($stats['packed'] ?? 0) ?></span><span class="stat-card__label">已打包</span></div>
    <div class="stat-card stat-card--red"><span class="stat-card__value"><?= (int) ($stats['due'] ?? 0) ?></span><span class="stat-card__label">待补款</span></div>
    <div class="stat-card stat-card--green"><span class="stat-card__value"><?= (int) ($stats['today'] ?? 0) ?></span><span class="stat-card__label">今日完成</span></div>
</section>
<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--amber">打包称重</span><span class="rule"></span>
        <span class="badge badge--gray">共 <?= (int) $total ?> 单</span>
    </h3>
    <p class="form-hint" style="margin-bottom:14px">录入实际重量后按「实重与体积重取大」重算运费，差额自动多退少补并转「待出库」；用户余额不足以补差的运单会挂起为「待补款」，补缴到账前不可出库。</p>
    <div class="adm-toolbar">
        <input class="form-input" id="pk-kw" placeholder="检索：运单号 / 收件人 / 用户编号" value="<?= View::e($keyword) ?>">
        <button class="btn btn--primary" onclick="admSearch('/warehouse/packing', {kw: val('pk-kw')})">检索</button>
    </div>
    <div class="table-wrap" data-table-wrap data-url="/warehouse/packing?kw=<?= urlencode($keyword) ?>">
        <?= View::partial('warehouse/packing_table', get_defined_vars()) ?>
    </div>
</section>

<script>
document.getElementById('pk-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { admSearch('/warehouse/packing', {kw: this.value}); }
});
</script>
