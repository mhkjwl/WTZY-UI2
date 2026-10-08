<?php declare(strict_types=1);
/**
 * @file app/Views/admin/partials/rate_table.php
 * @description 资费/服务行编辑表格（两张表结构相同，共用此片段）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var string $key    表单 name 前缀（同时作为表格 id）
 * @var string $badge  标题徽标样式
 * @var string $title  区块标题
 * @var string $hint   区块说明
 * @var array  $fields 字段定义：键 => [标签, 输入框宽度(px), 占位提示]
 * @var array  $rows   行数据
 */
?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--<?= View::e($badge) ?>"><?= View::e($title) ?></span><span class="rule"></span></h3>
    <p class="form-hint" style="margin-bottom:12px"><?= View::e($hint) ?></p>
    <div class="table-scroll">
        <table class="table" id="<?= View::e($key) ?>-table">
            <thead>
            <tr>
                <?php foreach ($fields as $meta): ?><th class="nowrap"><?= View::e($meta[0]) ?></th><?php endforeach; ?>
                <th class="nowrap">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($fields as $field => $meta): ?>
                        <td>
                            <input class="form-input" name="<?= View::e($key) ?>_<?= View::e($field) ?>[]" maxlength="60"
                                   value="<?= View::e((string) ($row[$field] ?? '')) ?>"
                                   placeholder="<?= View::e($meta[2]) ?>"
                                   style="min-width:<?= (int) $meta[1] ?>px">
                        </td>
                    <?php endforeach; ?>
                    <td class="nowrap">
                        <button class="btn btn--ghost btn--sm" type="button" onclick="delRateRow(this)">删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="adm-toolbar" style="margin-bottom:0">
        <button class="btn btn--ghost btn--sm" type="button" onclick="addRateRow('<?= View::e($key) ?>')">+ 添加一行</button>
    </div>
</section>
