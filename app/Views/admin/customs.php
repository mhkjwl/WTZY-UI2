<?php declare(strict_types=1);
/**
 * @file app/Views/admin/customs.php
 * @description 清关管理页：报关明细列表 + 清关状态维护
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var string $keyword
 * @var int $page
 */
$initialUrl = '/admin/customs?' . http_build_query(array_filter(['kw' => $keyword, 'page' => $page], static fn($v): bool => $v !== ''));
?>
<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="cu-kw" placeholder="运单号 / 申报品名 / HS 编码" value="<?= View::e($keyword) ?>">
        <button class="btn btn--primary" onclick="admSearch('<?= View::e($base) ?>', {kw: val('cu-kw')})">检索</button>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('admin/customs_table', get_defined_vars()) ?>
    </div>
</section>
<script>
</script>
