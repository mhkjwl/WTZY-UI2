<?php declare(strict_types=1);
/**
 * @file app/Views/admin/staff_table.php
 * @description 员工列表局部渲染
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

use App\Core\View;

/**
 * @var array $rows
 * @var int $page
 * @var int $pages
 * @var string $keyword
 * @var string $base
 * @var array $perms
 */
$qs = static fn(int $p): string => '/admin/system/staff?' . http_build_query(array_filter(['kw' => $keyword, 'page' => $p], static fn($v): bool => $v !== ''));
?>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>账号</th><th>姓名</th><th>角色</th><th>端别</th><th>归属仓库</th><th>状态</th><th>最后登录</th><th class="nowrap">操作</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="8"><div class="empty-state">暂无员工。请创建管理端或仓库端员工账号，仓库端需指定归属仓库。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $s): ?>
        <?php
        // 管理员角色（admin/super_admin）的账号仅超级管理员可操作
        $targetRoleCode = (string) ($s['role_code'] ?? '');
        $isProtected = in_array($targetRoleCode, ['admin', 'super_admin'], true);
        $canOperate = !empty($perms['staff.manage']) && (!$isProtected || ($isSuper ?? false));
        ?>
        <tr data-id="<?= (int) $s['id'] ?>">
            <td class="mono"><b><?= View::e($s['username']) ?></b></td>
            <td><?= View::e($s['real_name']) ?></td>
            <td><span class="badge badge--purple"><?= View::e($s['role_name']) ?></span></td>
            <td><?= (int) $s['staff_type'] === 1 ? '管理端' : '仓库端' ?></td>
            <td><?= View::e($s['warehouse_name'] ?: '—') ?></td>
            <td><span class="badge badge--<?= (int) $s['status'] === 1 ? 'green' : 'red' ?>"><?= (int) $s['status'] === 1 ? '启用' : '禁用' ?></span></td>
            <td class="nowrap"><?= View::e($s['last_login_at'] ?: '从未登录') ?></td>
            <td class="nowrap">
                <?php if ($canOperate): ?>
                    <button class="btn btn--ghost btn--sm"
                            onclick="openStaffEdit(<?= (int) $s['id'] ?>, <?= View::e(json_encode(['username' => $s['username'], 'real_name' => $s['real_name'], 'phone' => (string) $s['phone'], 'role_id' => (int) $s['role_id'], 'warehouse_id' => (int) $s['warehouse_id'], 'staff_type' => (int) $s['staff_type']], JSON_UNESCAPED_UNICODE)) ?>)">编辑</button>
                    <button class="btn btn--ghost btn--sm" onclick="admAction('/admin/system/staff/toggle', {id: <?= (int) $s['id'] ?>}, '确定切换该员工启停状态吗？')"><?= (int) $s['status'] === 1 ? '禁用' : '启用' ?></button>
                <?php elseif ($isProtected): ?>
                    <small class="form-hint">管理员账号</small>
                <?php endif; ?>
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
        <span class="is-disabled">共 <?= (int) $total ?> 人</span>
    </nav>
<?php endif; ?>
