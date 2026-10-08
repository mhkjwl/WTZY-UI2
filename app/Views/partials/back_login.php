<?php declare(strict_types=1);
/**
 * @file app/Views/partials/back_login.php
 * @description 后台类登录页（管理端 / 仓库端共用同一份结构与样式，仅端名与文案不同）
 * @author AI
 * @version 0.6.1
 * @date 2026-10-09
 */

use App\Core\View;

/**
 * @var string $formId      表单元素 ID（同页唯一）
 * @var string $loginUrl    登录接口地址
 * @var string $title       卡片标题
 * @var string $sub         卡片副标题
 * @var string $accountLabel 账号输入框标签
 * @var string $accountField 账号字段名（提交给接口的参数名）
 * @var string $mark        表单图标
 * @var string $captchaScene 验证码场景标识（管理端 / 仓库端各用独立槽位）
 * @var string $heroTag     左侧品牌标签
 * @var string $heroTitle   左侧主标题
 * @var string $heroDesc    左侧说明
 * @var array  $heroPoints  左侧特性列表
 */
$formId = $formId ?? 'login-form';
$loginUrl = $loginUrl ?? '/login';
$title = $title ?? '登录';
$sub = $sub ?? '';
$accountLabel = $accountLabel ?? '账号';
$accountField = $accountField ?? 'username';
$mark = $mark ?? '🌍';
$captchaScene = $captchaScene ?? 'login';

ob_start();
?>
<form id="<?= View::e($formId) ?>" novalidate>
    <div class="mb-3">
        <label class="form-label" for="account-input"><?= View::e($accountLabel) ?></label>
        <input class="form-control" type="text" id="account-input" name="<?= View::e($accountField) ?>"
               autocomplete="username" required>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">密码</label>
        <input class="form-control" type="password" id="password" name="password"
               autocomplete="current-password" required>
    </div>
    <?= View::partial('user/captcha_field', ['scene' => $captchaScene]) ?>
    <div class="d-grid">
        <button class="btn btn--primary" type="submit" id="login-btn">登 录</button>
    </div>
</form>

<script>
(function () {
    'use strict';
    document.getElementById('<?= View::e($formId) ?>').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('login-btn');
        var accountField = '<?= View::e($accountField) ?>';
        var captchaScene = '<?= View::e($captchaScene) ?>';
        var payload = {
            password: document.getElementById('password').value,
            captcha: document.getElementById('captcha-' + captchaScene).value.trim()
        };
        payload[accountField] = document.getElementById('account-input').value.trim();

        btn.disabled = true;
        btn.textContent = '登录中…';
        App.post('<?= View::e($loginUrl) ?>', payload).then(function (res) {
            if (res.success && res.data && res.data.redirect) {
                location.href = res.data.redirect;
                return;
            }
            Notify.error(res.message || '登录失败');
            // 验证码一次性使用，失败后必须换一张再提交
            refreshCaptcha(captchaScene);
            btn.disabled = false;
            btn.textContent = '登 录';
        }).catch(function () {
            Notify.error('网络异常，请稍后重试');
            refreshCaptcha(captchaScene);
            btn.disabled = false;
            btn.textContent = '登 录';
        });
    });
})();
</script>
<?php
$body = (string) ob_get_clean();

echo View::partial('partials/auth_shell', [
    'mark'       => $mark,
    'title'      => $title,
    'sub'        => $sub,
    'body'       => $body,
    'heroTag'    => $heroTag ?? '',
    'heroTitle'  => $heroTitle ?? '全球转运管理系统',
    'heroDesc'   => $heroDesc ?? '',
    'heroPoints' => $heroPoints ?? [],
]);