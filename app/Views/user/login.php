<?php declare(strict_types=1);
/**
 * @file app/Views/user/login.php
 * @description 用户端登录页
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

use App\Core\View;

/**
 * @var string|null $notice 账号被后台停用导致会话失效时，由登录守卫带出的原因提示
 */
$notice = $notice ?? null;
?>
<?php
ob_start();
?>
    <?php if ($notice !== null && $notice !== ''): ?>
        <div class="alert alert-warning" role="alert"><?= View::e($notice) ?></div>
    <?php endif; ?>
    <form id="login-form" novalidate>
        <div class="mb-3">
            <label class="form-label" for="account">手机号 / 邮箱</label>
            <input class="form-control" type="text" id="account" name="account" autocomplete="username" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">密码</label>
            <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <?= View::partial('user/captcha_field', ['scene' => 'login']) ?>
        <div class="text-end mb-2">
            <a class="small" href="/user/forgot">忘记密码？</a>
        </div>
        <div class="d-grid">
            <button class="btn btn--primary" type="submit" id="login-btn">登 录</button>
        </div>
    </form>
    <div class="ly-auth__foot-links">
        <span>还没有账号？</span>
        <a href="/user/register">立即注册</a>
    </div>
<?php
$body = (string) ob_get_clean();
echo View::partial('partials/auth_shell', [
    'mark'       => '🌍',
    'title'      => '用户端登录',
    'sub'        => '使用注册时的手机号或邮箱',
    'body'       => $body,
    'heroTag'    => '会员中心',
    'heroTitle'  => '全球转运 · 一站代收代运',
    'heroDesc'   => '在海外电商下单后寄往我们的海外仓，合箱转运回国，全程可查。',
    'heroPoints' => ['专属海外仓收货地址与识别码', '包裹到仓实时通知与认领', '合箱转运、运费在线试算', '余额充值、发票申请与理赔'],
]);
?>

<script>
(function () {
    'use strict';
    var form = document.getElementById('login-form');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('login-btn');
        btn.disabled = true;
        btn.textContent = '登录中…';
        App.post('/user/login', {
            account: document.getElementById('account').value.trim(),
            password: document.getElementById('password').value,
            captcha: document.getElementById('captcha-login').value.trim()
        }).then(function (res) {
            if (res.success && res.data && res.data.redirect) {
                location.href = res.data.redirect;
                return;
            }
            Notify.error(res.message || '登录失败');
            // 验证码一次性使用，失败后必须换一张再提交
            refreshCaptcha('login');
            btn.disabled = false;
            btn.textContent = '登 录';
        }).catch(function () {
            Notify.error('网络异常，请稍后重试');
            refreshCaptcha('login');
            btn.disabled = false;
            btn.textContent = '登 录';
        });
    });
})();
</script>