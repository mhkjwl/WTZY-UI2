<?php declare(strict_types=1);
/**
 * @file app/Views/user/register.php
 * @description 用户端注册页（注册成功自动登录并分配专属海外仓识别码）
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

use App\Core\View;
?>
<?php
ob_start();
?>
<?php
/**
 * @var bool $emailVerifyEnabled 开启后注册必须使用邮箱并完成验证码校验
 */
?>
    <form id="register-form" novalidate>
        <div class="mb-3">
            <label class="form-label" for="username">用户名</label>
            <input class="form-control" type="text" id="username" name="username" maxlength="20"
                   autocomplete="username" placeholder="4~20 位，字母开头" required>
            <p class="form-text text-muted mb-0">用于登录与找回密码，注册后不可更改</p>
        </div>
        <div class="mb-3">
            <label class="form-label" for="account"><?= $emailVerifyEnabled ? '邮箱' : '手机号 / 邮箱' ?></label>
            <input class="form-control" type="text" id="account" name="account" autocomplete="email" required
                   placeholder="<?= $emailVerifyEnabled ? '用于登录与接收验证通知' : '' ?>">
        </div>
        <?php if ($emailVerifyEnabled): ?>
        <div class="mb-3">
            <label class="form-label" for="email-code">邮箱验证码</label>
            <div class="ly-captcha">
                <input class="form-control" type="text" id="email-code" name="email_code" maxlength="6"
                       inputmode="numeric" placeholder="6 位数字" required>
                <button class="btn btn--ghost" type="button" id="send-code-btn" onclick="sendRegisterCode()">发送验证码</button>
            </div>
            <p class="form-text text-muted mb-0">验证码 10 分钟内有效；开启邮箱验证后必须完成校验才能注册。</p>
        </div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label" for="nickname">昵称 <span class="text-muted small">（选填）</span></label>
            <input class="form-control" type="text" id="nickname" name="nickname" maxlength="20">
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">密码</label>
            <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" required>
            <p class="form-text text-muted mb-0">至少 8 位，需同时包含字母和数字</p>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password_confirm">确认密码</label>
            <input class="form-control" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
        </div>
        <?= View::partial('user/captcha_field', ['scene' => 'register']) ?>
        <div class="d-grid">
            <button class="btn btn--primary" type="submit" id="register-btn">注 册</button>
        </div>
    </form>
    <div class="ly-auth__foot-links">
        <span>已有账号？</span>
        <a href="/user/login">直接登录</a>
    </div>
<?php
$body = (string) ob_get_clean();
echo View::partial('partials/auth_shell', [
    'mark'       => '📝',
    'title'      => '注册用户端账号',
    'sub'        => '注册后自动分配专属海外仓收货地址与识别码',
    'body'       => $body,
    'heroTag'    => '会员中心',
    'heroTitle'  => '开一个账号，开始转运',
    'heroDesc'   => '注册后立即获得专属入库识别码，海外电商下单时填在收件人里，包裹到仓自动归属到你的账户。',
    'heroPoints' => ['免手续费获取海外仓地址', '包裹到仓自动归属、实时通知', '合并包裹一次转运更省运费', '邀请好友可获得返利奖励'],
]);
?>

<script>
(function () {
    'use strict';
    var form = document.getElementById('register-form');
    var emailVerify = <?= $emailVerifyEnabled ? 'true' : 'false' ?>;

    // 邮箱验证码发送与 60 秒重发倒计时
    var sendBtn = document.getElementById('send-code-btn');
    var sendTimer = null;
    window.sendRegisterCode = function () {
        var account = document.getElementById('account').value.trim();
        if (!account || account.indexOf('@') === -1) { Notify.warning('请先填写正确的邮箱地址'); return; }
        sendBtn.disabled = true;
        App.post('/user/register/send-code', { account: account }).then(function (res) {
            if (!res.success) {
                Notify.error(res.message || '验证码发送失败');
                sendBtn.disabled = false;
                return;
            }
            Notify.success(res.message || '验证码已发送');
            var left = 60;
            sendBtn.textContent = left + ' 秒后重发';
            sendTimer = setInterval(function () {
                left--;
                if (left <= 0) {
                    clearInterval(sendTimer);
                    sendBtn.disabled = false;
                    sendBtn.textContent = '发送验证码';
                    return;
                }
                sendBtn.textContent = left + ' 秒后重发';
            }, 1000);
        }).catch(function () {
            Notify.error('网络异常，请稍后重试');
            sendBtn.disabled = false;
        });
    };

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var password = document.getElementById('password').value;
        var confirm = document.getElementById('password_confirm').value;
        if (password !== confirm) {
            Notify.warning('两次输入的密码不一致');
            return;
        }
        var payload = {
            username: document.getElementById('username').value.trim(),
            account: document.getElementById('account').value.trim(),
            nickname: document.getElementById('nickname').value.trim(),
            password: password,
            password_confirm: confirm,
            captcha: document.getElementById('captcha-register').value.trim()
        };
        if (emailVerify) { payload.email_code = document.getElementById('email-code').value.trim(); }
        var btn = document.getElementById('register-btn');
        btn.disabled = true;
        btn.textContent = '注册中…';
        App.post('/user/register', payload).then(function (res) {
            if (res.success && res.data && res.data.redirect) {
                Notify.success(res.message || '注册成功');
                location.href = res.data.redirect;
                return;
            }
            Notify.error(res.message || '注册失败');
            // 验证码一次性使用，失败后必须换一张再提交
            refreshCaptcha('register');
            btn.disabled = false;
            btn.textContent = '注 册';
        }).catch(function () {
            Notify.error('网络异常，请稍后重试');
            refreshCaptcha('register');
            btn.disabled = false;
            btn.textContent = '注 册';
        });
    });
})();
</script>