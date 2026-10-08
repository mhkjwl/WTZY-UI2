<?php declare(strict_types=1);
/**
 * @file app/Views/admin/roles_content.php
 * @description 角色权限矩阵局部内容（勾选后按角色整体保存）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array $roles
 * @var array $permissions
 * @var array $matrix
 * @var array $perms
 */
?>
<?php
// 角色 ID → 名称，供保存前的确认弹窗逐角色列明改动内容
$roleNames = [];
foreach ($roles as $r) {
    $roleNames[(string) $r['id']] = (string) $r['name'];
}
// 按钮级权限点 → 所属页面权限点，保存前校验是否漏授页面权限（漏授时页面上不会出现任何入口）
$buttonPerms = [];
foreach ($permissions as $rows) {
    foreach ($rows as $p) {
        if (!empty($p['page_perm_id'])) {
            $buttonPerms[(string) $p['id']] = [
                'name'      => (string) $p['name'],
                'pageId'    => (string) $p['page_perm_id'],
                'pageName'  => (string) $p['page_perm_name'],
            ];
        }
    }
}
?>
<div class="table-scroll">
<table class="table"
       data-role-names="<?= View::e(json_encode($roleNames, JSON_UNESCAPED_UNICODE)) ?>"
       data-button-perms="<?= View::e(json_encode($buttonPerms, JSON_UNESCAPED_UNICODE)) ?>">
    <thead>
    <tr>
        <th>模块 / 权限点</th>
        <?php foreach ($roles as $r): ?>
            <th class="nowrap"><?= View::e($r['name']) ?><br>
                <?php if ($r['code'] === 'super_admin'): ?>
                    <small>代码层全放行</small>
                <?php else: ?>
                    <small>已授权 <?= (int) ($r['perm_count'] ?? 0) ?> 项</small>
                <?php endif; ?>
            </th>
        <?php endforeach; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($permissions as $group => $perms): ?>
        <tr><td colspan="<?= count($roles) + 1 ?>" style="background:#f8fafc"><b><?= View::e($group) ?></b></td></tr>
        <?php foreach ($perms as $p): ?>
            <tr>
                <td><span class="mono"><?= View::e($p['perm_code']) ?></span> · <?= View::e($p['name']) ?>
                    <?php if (!empty($p['page_perm_name'])): ?>
                        <br><small>按钮级权限 · 需同时授予「<?= View::e($p['page_perm_name']) ?>」才会出现操作入口</small>
                    <?php endif; ?>
                </td>
                <?php foreach ($roles as $r): ?>
                    <?php if ($r['code'] === 'super_admin'): ?>
                        <td style="text-align:center"><span class="badge badge--green">全部</span></td>
                    <?php else: ?>
                        <?php $checked = in_array((int) $p['id'], $matrix[$r['id']] ?? [], true); ?>
                        <td style="text-align:center">
                            <input type="checkbox" class="perm-box" data-role="<?= (int) $r['id'] ?>"
                                   data-initial="<?= $checked ? '1' : '0' ?>"
                                   value="<?= (int) $p['id'] ?>"
                                   <?= $checked ? 'checked' : '' ?>>
                        </td>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="form-hint">只提交本次有改动的角色；相关员工下次操作即按新权限生效。</p>
<button class="btn btn--primary" onclick="saveMatrix()">保存权限矩阵</button>
