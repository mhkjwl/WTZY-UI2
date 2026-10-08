<?php declare(strict_types=1);
/**
 * @file install/steps/step5_install.php
 * @description 安装第五步：执行安装（确认页 + 一键执行：导入建表 → 管理员 → 站点配置 → .env → 锁）
 * @author AI
 * @version 0.6
 * @date 2026-10-08
 */

declare(strict_types=1);

install_session();
foreach (['db', 'admin', 'config'] as $required) {
    if (empty($_SESSION['install'][$required])) {
        install_redirect('1');
    }
}

$db = $_SESSION['install']['db'];
$admin = $_SESSION['install']['admin'];
$config = $_SESSION['install']['config'];
$prefix = (string) ($db['prefix'] ?? 'wt_');
$csrfToken = install_csrf_token();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    install_verify_csrf();

    try {
        $pdo = install_db_connect($db['host'], $db['port'], $db['user'], $db['pass']);
        $pdo->exec(
            "CREATE DATABASE IF NOT EXISTS `{$db['name']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        $pdo->exec("USE `{$db['name']}`");

        // 重装保护：users 表已存在且有用户数据时拒绝执行，防止覆盖生产数据；
        // 全新空库时表尚不存在，直接视为全新安装继续建表
        $tableCheck = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :db AND table_name = :tbl'
        );
        $tableCheck->execute([':db' => $db['name'], ':tbl' => $prefix . 'users']);
        if ((int) $tableCheck->fetchColumn() === 1) {
            $userCount = (int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}users`")->fetchColumn();
            if ($userCount > 0) {
                install_view('安装中止', '<h2 class="card-title">安装中止</h2>'
                    . '<p class="fix-hint">目标数据库已存在用户数据。为防止覆盖生产数据，安装已停止；如确需重装，请先清空该数据库。</p>'
                    . '<div class="card-actions"><a class="btn btn--ghost" href="/install/?step=2">更换数据库</a></div>', 5);
            }
        }

        install_import_schema($pdo, $prefix);

        // 创建超级管理员（角色由种子数据保证存在）
        $roleStmt = $pdo->query("SELECT id FROM `{$prefix}roles` WHERE code = 'super_admin' LIMIT 1");
        $roleId = $roleStmt->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException('角色种子数据缺失，无法创建管理员');
        }
        // 管理员写入先查后写：安装中断后重试时，同账号执行更新而非重复插入，
        // 避免唯一索引冲突中断整个安装流程（软删除历史占用同名账号时一并恢复可用）
        $findAdmin = $pdo->prepare("SELECT id FROM `{$prefix}admins` WHERE username = :username LIMIT 1");
        $findAdmin->execute([':username' => $admin['username']]);
        $existingAdminId = $findAdmin->fetchColumn();
        if ($existingAdminId === false) {
            $stmt = $pdo->prepare(
                "INSERT INTO `{$prefix}admins` (username, password_hash, real_name, role_id, staff_type, status)
                 VALUES (:username, :hash, :real_name, :role_id, 1, 1)"
            );
            $stmt->execute([
                ':username' => $admin['username'],
                ':hash' => $admin['hash'],
                ':real_name' => $admin['real_name'],
                ':role_id' => (int) $roleId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE `{$prefix}admins`
                 SET password_hash = :hash, real_name = :real_name, role_id = :role_id,
                     staff_type = 1, status = 1, deleted_at = NULL
                 WHERE id = :id"
            );
            $stmt->execute([
                ':hash' => $admin['hash'],
                ':real_name' => $admin['real_name'],
                ':role_id' => (int) $roleId,
                ':id' => (int) $existingAdminId,
            ]);
        }

        // 站点名称写入系统配置
        $stmt = $pdo->prepare("UPDATE `{$prefix}settings` SET setting_value = :v WHERE setting_key = 'site_name'");
        $stmt->execute([':v' => $config['site_name']]);

        // 生成运行环境配置（含随机密钥）与安装锁
        if (!install_write_env([
            'url' => $config['url'],
            'host' => $db['host'],
            'port' => $db['port'],
            'name' => $db['name'],
            'user' => $db['user'],
            'pass' => $db['pass'],
            'prefix' => $prefix,
            'timezone' => $config['timezone'],
        ])) {
            throw new RuntimeException('写入 .env 配置文件失败，请检查根目录写入权限');
        }
        install_write_lock([
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => install_app_version(),
            'db_name' => $db['name'],
            'db_prefix' => $prefix,
            'admin_user' => $admin['username'],
            'site_name' => $config['site_name'],
            'php_version' => PHP_VERSION,
            'mysql_version' => (string) $pdo->query('SELECT VERSION()')->fetchColumn(),
        ]);

        // 清理向导临时状态（含数据库密码与会话中的管理员哈希）
        unset($_SESSION['install']);
        // 内联渲染完成页：此时锁文件已生成，跳转会被已安装守卫拦截
        require INSTALL_ROOT . '/steps/step6_done.php';
        exit;
    } catch (PDOException $e) {
        install_log_error('step5', $e->getMessage());
        $message = install_db_error_message($e);
    } catch (RuntimeException $e) {
        install_log_error('step5', $e->getMessage());
        $message = $e->getMessage();
    }
    $logHint = '<p class="form-hint" style="margin-top:8px">完整异常已记录到 storage/logs/install_error.log；反复失败请先解决上述原因，仍无法解决时提供该日志内容。</p>';
    $errorHtml = '<p class="alert alert--error">' . htmlspecialchars($message ?? '安装失败') . '</p>' . $logHint;
} else {
    $errorHtml = '';
}

$summary = <<<HTML
<tr><td>数据库</td><td>{$db['name']} @ {$db['host']}:{$db['port']}</td></tr>
<tr><td>数据表前缀</td><td>{$prefix}</td></tr>
<tr><td>站点名称</td><td>{$config['site_name']}</td></tr>
<tr><td>站点域名</td><td>{$config['url']}</td></tr>
<tr><td>默认时区</td><td>{$config['timezone']}</td></tr>
<tr><td>管理员账号</td><td>{$admin['username']}</td></tr>
HTML;

install_view('执行安装', <<<HTML
<h2 class="card-title">确认安装信息</h2>
<p class="card-sub">请核对以下信息，点击「开始安装」后将自动完成建表、初始化与配置生成</p>
<table class="check-table"><tbody>{$summary}</tbody></table>
{$errorHtml}
<form method="post" action="/install/?step=5">
    <input type="hidden" name="_token" value="{$csrfToken}">
    <div class="card-actions">
        <a class="btn btn--ghost" href="/install/?step=4">← 上一步</a>
        <button class="btn btn--danger btn--confirm" type="submit" data-confirm="开始安装后将执行建表与初始化，确定继续吗？">开始安装</button>
    </div>
</form>
HTML, 5);
