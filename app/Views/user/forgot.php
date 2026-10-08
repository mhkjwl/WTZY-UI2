<?php declare(strict_types=1);
/**
 * @file app/Views/user/forgot.php
 * @description 用户端找回密码：校验用户名与邮箱 → 邮箱验证码 → 重置密码
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

use App\Core\View;
?>
<?php
ob_start();
?>
    <div id="step-verify">
        <div class="mb-3">
            <label class="form-label" for="fp-username">用户名</label>
            <input class="form-control" type="text" id="fp-username" maxlength="20" autocomplete="username" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="fp-email">注册邮箱</label>
            <input class="form-control" type="email" id="fp-email" maxlength="128" autocomplete="email" required>
        </div>
        <?= View::partial('user/captcha_field', ['scene' => 'reset']) ?>
        <div class="d-grid">
            <button class="btn btn--primary" type="button" id="fp-send-btn" onclick="sendResetCode()">发送验证码</button>
        </div>
    </div>

    <div id="step-reset" hidden>
        <p class="form-text text-muted">
            验证码已发送至 <b id="fp-email-echo"></b>，10 分钟内有效。
        </p>
        <div class="mb-3">
            <label class="form-label" for="fp-code">邮箱验证码</label>
            <div class="ly-captcha">
                <input class="form-control" type="text" id="fp-code" maxlength="6" inputmode="numeric"
                       autocomplete="one-time-code" placeholder="6 位数字">
                <button class="btn btn--ghost" type="button" id="fp-resend-btn" onclick="sendResetCode()">重新发送</button>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="fp-password">新密码</label>
            <input class="form-control" type="password" id="fp-password" autocomplete="new-password" required>
            <p class="form-text text-muted mb-0">至少 8 位，需同时包含字母和数字</p>
        </div>
        <div class="mb-3">
            <label class="form-label" for="fp-password-confirm">确认新密码</label>
            <input class="form-control" type="password" id="fp-password-confirm" autocomplete="new-password" required>
        </div>
        <div class="d-grid">
            <button class="btn btn--primary" type="button" id="fp-reset-btn" onclick="submitReset()">重置密码</button>
        </div>
    </div>

    <div class="ly-auth__foot-links">
        <a href="/user/login">返回登录</a>
    </div>
<?php
$body = (string) ob_get_clean();
echo View::partial('partials/auth_shell', [
    'mark'       => '🔑',
    'title'      => '找回密码',
    'sub'        => '使用注册时的用户名与邮箱验证身份',
    'body'       => $body,
    'heroTag'    => '会员中心',
    'heroTitle'  => '安全找回你的账号',
    'heroDesc'   => '通过注册邮箱接收验证码即可重置密码；如邮箱已不可用，请联系客服核实身份后处理。',
    'heroPoints' => ['邮箱验证码验证身份', '重置后旧密码立即失效', '操作全程加密传输'],
]);
?>

<script>
var RESEND_WAIT = 60;
var resendTimer = null;

function fpValue(id) { return document.getElementById(id).value.trim(); }

function startResendCountdown() {
    var btn = document.getElementById('fp-resend-btn');
    if (!btn) { return; }
    var left = RESEND_WAIT;
    btn.disabled = true;
    btn.textContent = left + ' 秒后可重发';
    if (resendTimer) { clearInterval(resendTimer); }
    resendTimer = setInterval(function () {
        left--;
        if (left <= 0) {
            clearInterval(resendTimer);
            resendTimer = null;
            btn.disabled = false;
            btn.textContent = '重新发送';
            return;
        }
        btn.textContent = left + ' 秒后可重发';
    }, 1000);
}

async function sendResetCode() {
    var username = fpValue('fp-username');
    var email = fpValue('fp-email');
    var captcha = fpValue('captcha-reset');
    if (!username) { Notify.warning('请填写用户名'); return; }
    if (!email) { Notify.warning('请填写注册邮箱'); return; }
    if (!captcha) { Notify.warning('请填写图形验证码'); return; }

    var btn = document.getElementById('fp-send-btn');
    btn.disabled = true;
    btn.textContent = '发送中…';
    var res = await App.post('/user/forgot/send', { username: username, email: email, captcha: captcha });
    btn.disabled = false;
    btn.textContent = '发送验证码';

    // 图形验证码一次性使用，无论成败都换一张
    refreshCaptcha('reset');

    if (!res.success) {
        Notify.error(res.message || '发送失败');
        return;
    }
    Notify.success(res.message || '验证码已发送');
    document.getElementById('fp-email-echo').textContent = email;
    document.getElementById('step-verify').hidden = true;
    document.getElementById('step-reset').hidden = false;
    document.getElementById('fp-code').focus();
    startResendCountdown();
}

async function submitReset() {
    var code = fpValue('fp-code');
    var password = document.getElementById('fp-password').value;
    var confirm = document.getElementById('fp-password-confirm').value;
    if (!code) { Notify.warning('请填写邮箱验证码'); return; }
    if (password !== confirm) { Notify.warning('两次输入的新密码不一致'); return; }

    var btn = document.getElementById('fp-reset-btn');
    btn.disabled = true;
    btn.textContent = '提交中…';
    var res = await App.post('/user/forgot/reset', {
        username: fpValue('fp-username'),
        email: fpValue('fp-email'),
        code: code,
        password: password,
        password_confirm: confirm
    });
    if (res.success) {
        Notify.success(res.message || '密码已重置');
        setTimeout(function () { location.href = '/user/login'; }, 800);
        return;
    }
    Notify.error(res.message || '重置失败');
    btn.disabled = false;
    btn.textContent = '重置密码';
}
</script>