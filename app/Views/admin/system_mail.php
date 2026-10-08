<?php declare(strict_types=1);
/**
 * @file app/Views/admin/system_mail.php
 * @description 后台：邮箱发送设置（SMTP 配置与测试）
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

use App\Core\View;

/**
 * @var array  $config
 * @var bool   $hasPass
 * @var bool   $configured
 * @var array  $perms
 * @var string $csrf
 */
?>
<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--cyan">注册邮箱验证</span><span class="rule"></span>
    </h3>
    <div class="form-field">
        <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px;cursor:pointer">
            <input type="checkbox" id="ml-email-verify" <?= $register_email_verify === '1' ? 'checked' : '' ?>>
            开启邮箱验证注册
        </label>
        <p class="form-hint" style="margin-top:6px">
            开启后注册页要求填写邮箱并通过验证码校验（10 分钟有效、60 秒内不可重发、错误次数与邮箱/IP 小时级频率限制），邮箱全局唯一、一个邮箱只能绑定一个账号；关闭后注册页隐藏邮箱验证相关字段，用户直接注册。发送依赖下方邮件服务，请先完成 SMTP 配置。
        </p>
    </div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--blue">邮箱发送设置</span><span class="rule"></span>
        <span class="badge badge--<?= $configured ? 'green' : 'orange' ?>"><?= $configured ? '已配置' : '未配置' ?></span>
    </h3>
    <p class="form-hint" style="line-height:1.9">
        密码/授权码以 AES 加密后入库，界面上不回显明文。
    </p>

    <div class="form-row">
        <div class="form-field" style="max-width:420px">
            <label class="form-label">SMTP 服务器</label>
            <input class="form-input" id="ml-host" value="<?= View::e($config['mail_host']) ?>" placeholder="如 smtp.qq.com / smtp.exmail.qq.com">
        </div>
        <div class="form-field" style="max-width:180px">
            <label class="form-label">端口</label>
            <input class="form-input" type="number" min="1" max="65535" id="ml-port" value="<?= View::e($config['mail_port']) ?>">
        </div>
        <div class="form-field" style="max-width:200px">
            <label class="form-label">加密方式</label>
            <select class="form-input" id="ml-secure">
                <option value="ssl"  <?= $config['mail_secure'] === 'ssl' ? 'selected' : '' ?>>SSL（常见 465）</option>
                <option value="tls"  <?= $config['mail_secure'] === 'tls' ? 'selected' : '' ?>>STARTTLS（常见 587）</option>
                <option value="none" <?= $config['mail_secure'] === 'none' ? 'selected' : '' ?>>不加密（仅内网调试）</option>
            </select>
        </div>
    </div>

    <div class="form-row">
        <div class="form-field" style="max-width:320px">
            <label class="form-label">SMTP 账号</label>
            <input class="form-input" id="ml-user" value="<?= View::e($config['mail_user']) ?>" placeholder="通常与发件邮箱相同">
        </div>
        <div class="form-field" style="max-width:320px">
            <label class="form-label">密码 / 授权码</label>
            <input class="form-input" type="password" id="ml-pass" autocomplete="new-password"
                   placeholder="<?= $hasPass ? '已设置，留空表示不修改' : '如 QQ 邮箱的授权码' ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-field" style="max-width:320px">
            <label class="form-label">发件邮箱</label>
            <input class="form-input" id="ml-from" value="<?= View::e($config['mail_from']) ?>" placeholder="no-reply@example.com">
        </div>
        <div class="form-field" style="max-width:320px">
            <label class="form-label">发件人名称</label>
            <input class="form-input" id="ml-from-name" maxlength="50" value="<?= View::e($config['mail_from_name']) ?>" placeholder="留空则使用网站名称">
        </div>
    </div>

    <?php if (!empty($perms['system.manage'])): ?>
        <div class="adm-toolbar" style="margin-bottom:0">
            <button class="btn btn--primary" type="button" onclick="saveMail()">保存设置</button>
            <button class="btn btn--ghost" type="button" onclick="openTestMail()">发送测试邮件</button>
        </div>
    <?php endif; ?>
</section>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-testmail">
    <div class="adm-dialog__head">发送测试邮件</div>
    <div class="adm-dialog__body">
        <div class="form-field">
            <label class="form-label">收件邮箱</label>
            <input class="form-input" id="tm-to" type="email" placeholder="用于接收测试邮件">
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="sendTestMail()">发送</button>
    </div>
</dialog>

<script>
function collectMail() {
    return {
        mail_host: val('ml-host').trim(), mail_port: val('ml-port'), mail_secure: val('ml-secure'),
        mail_user: val('ml-user').trim(), mail_pass: val('ml-pass'),
        mail_from: val('ml-from').trim(), mail_from_name: val('ml-from-name').trim(),
        register_email_verify: document.getElementById('ml-email-verify').checked ? '1' : '0'
    };
}

function saveMail() {
    var data = collectMail();
    if (!data.mail_host) { Notify.warning('请填写 SMTP 服务器'); return; }
    if (!data.mail_from) { Notify.warning('请填写发件邮箱'); return; }
    if (data.mail_user && !data.mail_pass && <?= $hasPass ? 'false' : 'true' ?>) {
        Notify.warning('填写了 SMTP 账号就必须填写密码/授权码');
        return;
    }
    admConfirm('确定保存邮箱发送设置吗？保存后系统邮件将按新配置发送。', async function () {
        var res = await App.post('/admin/system/mail/save', data);
        App.handle(res, '邮箱设置已保存');
        if (!res.success) { return; }
        // 局部更新：状态徽标置为已配置、密码框提示改为「留空表示不修改」，不整页刷新
        var badge = document.querySelector('.section-head .badge--green, .section-head .badge--orange');
        if (badge) { badge.className = 'badge badge--green'; badge.textContent = '已配置'; }
        var pass = document.getElementById('ml-pass');
        if (pass) { pass.value = ''; pass.placeholder = '已设置，留空表示不修改'; }
    }, { title: '保存邮箱设置', confirmText: '确认保存' });
}

function openTestMail() {
    document.getElementById('tm-to').value = val('ml-from').trim();
    document.getElementById('dlg-testmail').showModal();
}

async function sendTestMail() {
    var to = val('tm-to').trim();
    if (!to) { Notify.warning('请填写收件邮箱'); return; }
    var btn = document.querySelector('#dlg-testmail .btn--primary');
    btn.disabled = true;
    btn.textContent = '发送中…';
    var res = await App.post('/admin/system/mail/test', { to: to });
    btn.disabled = false;
    btn.textContent = '发送';
    App.handle(res, '测试邮件已发送');
    if (res.success) { document.getElementById('dlg-testmail').close(); }
}
</script>
