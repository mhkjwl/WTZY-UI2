<?php declare(strict_types=1);
/**
 * @file app/Views/admin/system_staff.php
 * @description 员工管理页：列表 + 新建/编辑（含登录凭据）对话框
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

use App\Core\View;

/**
 * @var array $roles
 * @var array $warehouses
 * @var string $keyword
 * @var array $perms
 * @var string $csrf
 */
?>
<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="s-kw" placeholder="账号 / 姓名" value="<?= View::e($keyword) ?>">
        <button class="btn btn--primary" onclick="admSearch('<?= View::e($base) ?>', {kw: val('s-kw')})">检索</button>
        <?php if (!empty($perms['staff.manage'])): ?>
            <button class="btn btn--primary" style="margin-left:auto" onclick="document.getElementById('dlg-staff').showModal()">+ 新建员工</button>
        <?php endif; ?>
    </div>
    <div class="table-wrap" data-table-wrap data-url="/admin/system/staff?<?= http_build_query(array_filter(['kw' => $keyword], static fn($v): bool => $v !== '')) ?>">
        <?= View::partial('admin/staff_table', get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog" id="dlg-staff">
    <div class="adm-dialog__head">新建员工</div>
    <div class="adm-dialog__body">
        <div class="form-row">
            <div class="form-field"><label class="form-label">登录账号</label><input class="form-input" id="sf-username"></div>
            <div class="form-field"><label class="form-label">姓名</label><input class="form-input" id="sf-name"></div>
            <div class="form-field"><label class="form-label">手机号（可选）</label><input class="form-input" id="sf-phone"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">初始密码（≥8 位，大小写+数字）</label><input class="form-input" id="sf-pass"></div>
            <div class="form-field"><label class="form-label">角色</label>
                <select class="form-input" id="sf-role">
                    <?php foreach ($roles as $r): ?>
                        <?php if ($r['code'] === 'super_admin' || ($r['code'] === 'admin' && empty($isSuper))) { continue; } ?>
                        <option value="<?= (int) $r['id'] ?>"><?= View::e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-field"><label class="form-label">岗位端别</label>
                <select class="form-input" id="sf-type"><option value="1">管理端</option><option value="2">仓库端</option></select></div>
        </div>
        <div class="form-field"><label class="form-label">归属仓库（仓库端必选）</label>
            <select class="form-input" id="sf-wh"><option value="0">—</option>
                <?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['id'] ?>"><?= View::e($w['name']) ?></option><?php endforeach; ?>
            </select></div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveStaff()">创建</button>
    </div>
</dialog>

<dialog class="adm-dialog" id="dlg-staff-edit">
    <div class="adm-dialog__head">编辑员工 · <span id="se-user"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="se-id">
        <div class="form-row">
            <div class="form-field"><label class="form-label">登录账号</label><input class="form-input" id="se-username" maxlength="32" placeholder="4~32 位字母、数字或下划线" autocomplete="off"></div>
            <div class="form-field"><label class="form-label">姓名</label><input class="form-input" id="se-name" maxlength="30"></div>
            <div class="form-field"><label class="form-label">手机号（可选）</label><input class="form-input" id="se-phone" maxlength="20"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">角色</label>
                <select class="form-input" id="se-role">
                    <?php foreach ($roles as $r): ?>
                        <?php /* 超级管理员权限由代码层全放行，仅用于回显当前归属，不允许改派 */ ?>
                        <option value="<?= (int) $r['id'] ?>"<?= $r['code'] === 'super_admin' ? ' disabled' : '' ?>><?= View::e($r['name']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-field"><label class="form-label">岗位端别</label>
                <select class="form-input" id="se-type"><option value="1">管理端</option><option value="2">仓库端</option></select></div>
            <div class="form-field"><label class="form-label">归属仓库（仓库端必选）</label>
                <select class="form-input" id="se-wh"><option value="0">—</option>
                    <?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['id'] ?>"><?= View::e($w['name']) ?></option><?php endforeach; ?>
                </select></div>
        </div>

        <h4 class="section-head" style="margin-top:16px"><span class="badge badge--gray">登录密码</span><span class="rule"></span></h4>
        <div class="form-row">
            <div class="form-field"><label class="form-label">新密码</label>
                <input class="form-input" type="password" id="se-password" maxlength="64" placeholder="留空表示不修改" autocomplete="new-password"></div>
            <div class="form-field"><label class="form-label">确认新密码</label>
                <input class="form-input" type="password" id="se-password2" maxlength="64" placeholder="与左侧一致" autocomplete="new-password"></div>
        </div>
        <p class="form-hint">账号与密码留空表示保持原值；密码需 ≥8 位且同时包含大小写字母与数字。</p>
        <p class="form-hint">凭据变更后本人下次须使用新账号或新密码登录，请通过线下安全渠道告知。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveStaffEdit()">保存</button>
    </div>
</dialog>

<script>
function saveStaff() {
    var username = val('sf-username').trim();
    var realName = val('sf-name').trim();
    if (!username) { Notify.warning('请填写登录账号'); return; }
    if (!realName) { Notify.warning('请填写姓名'); return; }
    admConfirm('确定创建员工「' + realName + '」（账号 ' + username + '）吗？', async function () {
        var res = await App.post('/admin/system/staff/save', {
            username: username, real_name: realName, password: val('sf-pass'),
            role_id: val('sf-role'), staff_type: val('sf-type'), warehouse_id: val('sf-wh'), phone: val('sf-phone')
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-staff').close(); admRefresh(); }
    }, { title: '创建员工', confirmText: '确认创建' });
}
/** 弹窗打开时的登录账号原值，用于判断本次是否真的改动了凭据（决定确认等级） */
var seOriginUsername = '';
function openStaffEdit(id, data) {
    document.getElementById('se-id').value = id;
    document.getElementById('se-user').textContent = data ? data.real_name : '';
    document.getElementById('se-username').value = data ? (data.username || '') : '';
    seOriginUsername = data ? (data.username || '') : '';
    document.getElementById('se-name').value = data ? data.real_name : '';
    document.getElementById('se-phone').value = data ? (data.phone || '') : '';
    document.getElementById('se-role').value = data ? String(data.role_id) : '';
    document.getElementById('se-type').value = data ? String(data.staff_type) : '1';
    document.getElementById('se-wh').value = data ? String(data.warehouse_id) : '0';
    document.getElementById('se-password').value = '';
    document.getElementById('se-password2').value = '';
    document.getElementById('dlg-staff-edit').showModal();
}
function saveStaffEdit() {
    var realName = val('se-name').trim();
    var username = val('se-username').trim();
    var pwd = val('se-password');
    var pwd2 = val('se-password2');
    if (!realName) { Notify.warning('请填写姓名'); return; }
    if (!username) { Notify.warning('请填写登录账号'); return; }
    // 前端提示仅为降低无效请求，最终校验以服务端为准
    if (pwd && pwd !== pwd2) { Notify.warning('两次输入的密码不一致'); return; }
    var credentialChanged = username !== seOriginUsername || pwd !== '';
    var message = credentialChanged
        ? '确定保存对该员工的修改吗？登录账号或密码变更后，本人下次需使用新凭据登录。'
        : '确定保存员工「' + realName + '」的资料与角色调整吗？权限变更本人下次操作即生效。';
    admConfirm(message, async function () {
        var res = await App.post('/admin/system/staff/update', {
            id: val('se-id'), username: username, real_name: realName, phone: val('se-phone').trim(),
            role_id: val('se-role'), staff_type: val('se-type'), warehouse_id: val('se-wh'),
            password: pwd, password_confirm: pwd2
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-staff-edit').close(); admRefresh(); }
    }, { title: '保存员工信息', confirmText: '确认保存', level: credentialChanged ? 'L2' : 'L1' });
}
</script>
