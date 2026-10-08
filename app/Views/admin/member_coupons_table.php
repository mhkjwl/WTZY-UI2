<?php declare(strict_types=1);
/**
 * @file app/Views/admin/member_coupons_table.php
 * @description 优惠券列表局部渲染（新建/编辑/启停/发放后仅替换本区域）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array $rows
 * @var array $types
 * @var array $perms
 */
?>
    <div class="table-scroll">
        <table class="table">
            <thead>
            <tr><th>券码</th><th>名称</th><th>类型</th><th>面额</th><th>门槛</th><th>有效期</th><th>发行/已发</th><th>状态</th><th class="nowrap">操作</th></tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="9"><div class="empty-state">还没有优惠券。先创建券种，再按用户或会员等级发放。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $c): ?>
                <tr data-id="<?= (int) $c['id'] ?>">
                    <td class="mono"><?= View::e($c['code']) ?></td>
                    <td><?= View::e($c['name']) ?></td>
                    <td><span class="badge badge--gray"><?= View::e($types[(int) $c['type']] ?? '其他') ?></span></td>
                    <td class="nowrap">
                        <?= (int) $c['type'] === 2 ? number_format((float) $c['value'] * 10, 1) . ' 折' : '¥' . number_format((float) $c['value'], 2) ?>
                    </td>
                    <td class="nowrap">满 ¥<?= number_format((float) $c['min_amount'], 2) ?></td>
                    <td class="nowrap">
                        <?= View::e($c['start_at'] ? substr((string) $c['start_at'], 0, 10) : '不限') ?>
                        ~
                        <?= View::e($c['end_at'] ? substr((string) $c['end_at'], 0, 10) : '不限') ?>
                    </td>
                    <td class="nowrap">
                        <?= (int) $c['total_qty'] === 0 ? '不限量' : (int) $c['total_qty'] ?> / <?= (int) $c['granted'] ?>
                    </td>
                    <td><span class="badge badge--<?= (int) $c['status'] === 1 ? 'green' : 'gray' ?>"><?= (int) $c['status'] === 1 ? '启用' : '停用' ?></span></td>
                    <td class="nowrap">
                        <?php if (!empty($perms['member.manage'])): ?>
                            <button class="btn btn--ghost btn--sm"
                                    onclick='openCoupon(<?= json_encode($c, JSON_UNESCAPED_UNICODE) ?>)'>编辑</button>
                            <button class="btn btn--ghost btn--sm" onclick="toggleCoupon(<?= (int) $c['id'] ?>)"><?= (int) $c['status'] === 1 ? '停用' : '启用' ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
