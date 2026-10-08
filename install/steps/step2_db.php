<?php declare(strict_types=1);
/**
 * @file install/steps/step2_db.php
 * @description 安装第二步：数据库配置（连接信息 + 表前缀；测试连接并创建数据库，建表在第五步统一执行）
 * @author AI
 * @version 0.6
 * @date 2026-10-08
 */

declare(strict_types=1);

install_session();
$saved = $_SESSION['install']['db'] ?? [];
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    install_verify_csrf();

    $host = trim((string) ($_POST['db_host'] ?? ''));
    $port = trim((string) ($_POST['db_port'] ?? ''));
    $name = trim((string) ($_POST['db_name'] ?? ''));
    $user = trim((string) ($_POST['db_user'] ?? ''));
    $pass = (string) ($_POST['db_pass'] ?? '');
    // 密码框对已保存密码只展示占位星号：保持未改动提交时沿用会话密码，避免星号被当作真实密码连库
    if ($pass === '********' && ($saved['pass'] ?? '') !== '') {
        $pass = (string) $saved['pass'];
    }

    // 表前缀：默认 wt，自动补下划线，保证不与表名粘连
    $prefix = trim((string) ($_POST['db_prefix'] ?? 'wt'));
    if ($prefix === '') {
        $prefix = 'wt';
    }
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,19}$/', $prefix)) {
        $prefix = '';
    } elseif (!str_ends_with($prefix, '_')) {
        $prefix .= '_';
    }

    if ($host === '' || $name === '' || $user === '') {
        $error = '请完整填写数据库地址、数据库名与用户名';
    } elseif (!preg_match('/^\d{2,5}$/', $port)) {
        $error = '端口格式不正确';
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        $error = '数据库名仅允许字母、数字与下划线';
    } elseif ($prefix === '') {
        $error = '表前缀需以字母开头，仅含字母、数字与下划线，不超过 20 位';
    } else {
        try {
            $server = install_db_connect($host, $port, $user, $pass);
            $version = (string) $server->query('SELECT VERSION()')->fetchColumn();
            if (version_compare($version, '5.7.0', '<')) {
                $error = "MySQL 版本过低（当前 {$version}），系统要求 5.7 及以上";
            } else {
                $server->exec(
                    "CREATE DATABASE IF NOT EXISTS `{$name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                );
                $_SESSION['install']['db'] = [
                    'host' => $host, 'port' => $port, 'name' => $name,
                    'user' => $user, 'pass' => $pass, 'prefix' => $prefix,
                ];
                install_redirect('3');
            }
        } catch (PDOException $e) {
            $error = install_db_error_message($e);
        }
    }
}

// 回显上一步填写过的非敏感配置（密码仅回显占位星号）
$vHost = htmlspecialchars($saved['host'] ?? '127.0.0.1');
$vPort = htmlspecialchars($saved['port'] ?? '3306');
$vName = htmlspecialchars($saved['name'] ?? 'wtzy');
$vUser = htmlspecialchars($saved['user'] ?? 'root');
$vPass = ($saved['pass'] ?? '') !== '' ? '********' : '';
$vPassHint = ($saved['pass'] ?? '') !== ''
    ? '<p class="form-hint">保持星号即沿用已保存密码，清空则改为空密码</p>'
    : '';
$vPrefix = htmlspecialchars(rtrim($saved['prefix'] ?? 'wt', '_'));

$csrfToken = install_csrf_token();
$errorHtml = $error !== '' ? '<p class="alert alert--error">' . htmlspecialchars($error) . '</p>' : '';

install_view('数据库配置', <<<HTML
<h2 class="card-title">数据库配置</h2>
<p class="card-sub">填入你的 MySQL 5.7 连接信息，提交时自动测试连接并创建数据库</p>
<form method="post" action="/install/?step=2" novalidate>
    <input type="hidden" name="_token" value="{$csrfToken}">
    <div class="form-field"><label class="form-label">数据库地址</label>
        <input class="form-input" type="text" name="db_host" value="{$vHost}" required></div>
    <div class="form-row">
        <div class="form-field"><label class="form-label">端口</label>
            <input class="form-input" type="text" name="db_port" value="{$vPort}" required></div>
        <div class="form-field form-field--grow"><label class="form-label">数据库名（不存在将自动创建）</label>
            <input class="form-input" type="text" name="db_name" value="{$vName}" required></div>
    </div>
    <div class="form-field"><label class="form-label">用户名</label>
        <input class="form-input" type="text" name="db_user" value="{$vUser}" required></div>
    <div class="form-field"><label class="form-label">密码</label>
        <input class="form-input" type="password" name="db_pass" value="{$vPass}" placeholder="本地开发常见为空密码">{$vPassHint}</div>
    <div class="form-field"><label class="form-label">数据表前缀</label>
        <input class="form-input" type="text" name="db_prefix" value="{$vPrefix}" placeholder="wt">
        <p class="form-hint">默认 wt；填 wt 后数据表名形如 wt_users、wt_packages，安装后不可更改</p></div>
    {$errorHtml}
    <div class="card-actions">
        <a class="btn btn--ghost" href="/install/?step=1">← 上一步</a>
        <button class="btn btn--primary" type="submit">测试连接并创建数据库</button>
    </div>
</form>
HTML, 2);
