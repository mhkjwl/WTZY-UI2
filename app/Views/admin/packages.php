<?php declare(strict_types=1);
/**
 * @file app/Views/admin/packages.php
 * @description 后台包裹管理页：统计 + 检索工具栏 + 局部表
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
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
 * @var array  $counts
 * @var string $base
 */
$inStock = 0;
foreach ($counts as $code => $cnt) {
    if ((int) $code >= 2 && (int) $code <= 9) {
        $inStock += (int) $cnt;
    }
}
$initialUrl = '/admin/packages?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $page],
    static fn($v): bool => $v !== '' && $v !== -1
));
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">📦</div>
        <div><div class="astat__value"><?= array_sum(array_map('intval', $counts)) ?></div><div class="astat__label">全部包裹</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">🏬</div>
        <div><div class="astat__value"><?= (int) $inStock ?></div><div class="astat__label">在库</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">🔔</div>
        <div><div class="astat__value"><?= (int) ($counts[3] ?? 0) ?></div><div class="astat__label">待认领</div></div></div>
    <div class="astat astat--red"><div class="astat__icon">⚠️</div>
        <div><div class="astat__value"><?= (int) ($counts[15] ?? 0) ?></div><div class="astat__label">异常件</div></div></div>
</section>

<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="p-kw" placeholder="包裹/预报/快递单号 · 用户编号 · 识别码 · 用户名" value="<?= View::e($keyword) ?>">
        <select class="form-input" id="p-status">
            <option value="-1">全部状态</option>
            <?php foreach (Status::PACKAGE as $code => $label): ?>
                <option value="<?= $code ?>" <?= $status === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--primary" onclick="admSearch('<?= View::e($base) ?>', {kw: val('p-kw'), status: val('p-status')})">检索</button>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('admin/packages_table', get_defined_vars()) ?>
    </div>
</section>

<script>
document.getElementById('p-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { admSearch('<?= View::e($base) ?>', {kw: this.value, status: val('p-status')}); }
});
</script>
