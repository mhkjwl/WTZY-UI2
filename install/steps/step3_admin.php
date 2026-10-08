<?php declare(strict_types=1);
/**
 * @file install/steps/step3_admin.php
 * @description 安装第三步：管理员账户设置（密码哈希后入会话，明文不落盘）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

declare(strict_types=1);

install_session();
if (empty($_SESSION['install']['db'])) {
    install_redirect('2');
}

$error = '';
$prev = $_SESSION['install']['admin'] ?? [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    install_verify_csrf();

    $username = trim((string) ($_POST['admin_user'] ?? ''));
    $realName = trim((string) ($_POST['admin_name'] ?? ''));
    $password = (string) ($_POST['admin_pass'] ?? '');
    $confirm  = (string) ($_POST['admin_pass_confirm'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_]{4,32}$/', $username)) {
        $error = '用户名需 4~32 位，仅允许字母、数字与下划线';
    } elseif ($realName === '') {
        $error = '请填写管理员姓名';
    } elseif (strlen($password) < 8
        || preg_match('/[A-Z]/', $password) !== 1
        || preg_match('/[a-z]/', $password) !== 1
        || preg_match('/\d/', $password) !== 1
        || preg_match('/[^A-Za-z0-9]/', $password) !== 1) {
        $error = '管理员密码需至少 8 位，且同时包含大小写字母、数字和特殊字符';
    } elseif ($password !== $confirm) {
        $error = '两次输入的密码不一致';
    } else {
        $_SESSION['install']['admin'] = [
            'username' => $username,
            'real_name' => $realName,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
        ];
        install_redirect('4');
    }
}

$csrfToken = install_csrf_token();
$vUser = htmlspecialchars($error !== '' ? ($username ?? '') : ($prev['username'] ?? ''));
$vName = htmlspecialchars($error !== '' ? ($realName ?? '') : ($prev['real_name'] ?? ''));
$errorHtml = $error !== '' ? '<p class="alert alert--error">' . htmlspecialchars($error) . '</p>' : '';

install_view('管理员设置', <<<HTML
<h2 class="card-title">管理员账户设置</h2>
<p class="card-sub">该账号用于登录管理后台，拥有最高权限，请妥善保管</p>
<form method="post" action="/install/?step=3" novalidate>
    <input type="hidden" name="_token" value="{$csrfToken}">
    <div class="form-field"><label class="form-label">登录用户名</label>
        <input class="form-input" type="text" name="admin_user" value="{$vUser}" required></div>
    <div class="form-field"><label class="form-label">姓名（用于操作日志展示）</label>
        <input class="form-input" type="text" name="admin_name" value="{$vName}" required></div>
    <div class="form-field"><label class="form-label">密码</label>
        <input class="form-input" type="password" name="admin_pass" autocomplete="new-password" required>
        <p class="form-hint">至少 8 位，需同时包含大小写字母、数字和特殊字符（如 Admin@1234）</p></div>
    <div class="form-field"><label class="form-label">确认密码</label>
        <input class="form-input" type="password" name="admin_pass_confirm" autocomplete="new-password" required></div>
    {$errorHtml}
    <div class="card-actions">
        <a class="btn btn--ghost" href="/install/?step=2">← 上一步</a>
        <button class="btn btn--primary" type="submit">下一步：系统配置 →</button>
    </div>
</form>
HTML, 3);
