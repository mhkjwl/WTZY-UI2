<?php declare(strict_types=1);
/**
 * @file app/Views/admin/services_table.php
 * @description 增值服务列表局部渲染（保存/启停/删除后仅替换本区域）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $rows
 * @var array $modes
 * @var array $perms
 */
?>
    <div class="table-scroll">
        <table class="table">
            <thead>
            <tr><th>排序</th><th>服务名称</th><th>服务说明</th><th>计价方式</th><th>单价</th><th>被引用</th><th>状态</th><th class="nowrap">操作</th></tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="8"><div class="empty-state">还没有增值服务。新建后用户在下单页的「增值服务」区即可勾选，停用的服务不会出现在下单页。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $s): ?>
                <tr data-id="<?= (int) $s['id'] ?>">
                    <td class="nowrap"><?= (int) $s['sort'] ?></td>
                    <td><b><?= View::e($s['name']) ?></b></td>
                    <td><?= $s['description'] !== null && $s['description'] !== '' ? View::e($s['description']) : '—' ?></td>
                    <td><span class="badge badge--cyan"><?= View::e($modes[(int) $s['price_mode']] ?? '未知') ?></span></td>
                    <td class="nowrap">¥<?= number_format((float) $s['price'], 2) ?></td>
                    <td class="nowrap"><?= (int) $s['used'] > 0 ? (int) $s['used'] . ' 条明细' : '未使用' ?></td>
                    <td><span class="badge badge--<?= (int) $s['status'] === 1 ? 'green' : 'gray' ?>"><?= (int) $s['status'] === 1 ? '启用' : '停用' ?></span></td>
                    <td class="nowrap">
                        <?php if (!empty($perms['channel.manage'])): ?>
                            <button class="btn btn--ghost btn--sm" data-row="<?= View::e((string) json_encode($s, JSON_UNESCAPED_UNICODE)) ?>"
                                    onclick="openService(this)">编辑</button>
                            <button class="btn btn--ghost btn--sm" onclick="toggleService(<?= (int) $s['id'] ?>, <?= (int) $s['status'] ?>)"><?= (int) $s['status'] === 1 ? '停用' : '启用' ?></button>
                            <button class="btn btn--ghost btn--sm" onclick="deleteService(<?= (int) $s['id'] ?>, this)">删除</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="form-hint">「被引用」是历史运单勾选该服务的明细条数；已被引用的服务只能停用不能删除，改价只影响之后的新订单。</p>
