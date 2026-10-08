<?php declare(strict_types=1);
/**
 * @file install/functions.php
 * @description 安装模块公共函数（自包含，不依赖主应用代码）
 * @author AI
 * @version 0.6.1
 * @date 2026-10-08
 */

declare(strict_types=1);

if (!defined('INSTALL_ROOT')) {
    define('INSTALL_ROOT', __DIR__);
    define('APP_ROOT', dirname(INSTALL_ROOT));
    define('LOCK_FILE', INSTALL_ROOT . '/lock/installed.lock');
    define('ENV_FILE', APP_ROOT . '/.env');
    define('SCHEMA_FILE', INSTALL_ROOT . '/sql/schema.sql');
}

/**
 * 安装向导会话（与主应用共用会话名，安装完成后状态随锁文件生效自动失效）
 */
function install_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('WTZYSESS');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function install_csrf_token(): string
{
    install_session();
    if (empty($_SESSION['install_csrf'])) {
        $_SESSION['install_csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['install_csrf'];
}

function install_verify_csrf(): void
{
    $token = (string) ($_POST['_token'] ?? '');
    if ($token === '' || !hash_equals(install_csrf_token(), $token)) {
        http_response_code(403);
        install_view('安全校验失败', '<h2 class="card-title">安全校验失败</h2><p class="fix-hint">表单令牌无效或已过期，请返回上一步重新提交。</p><div class="card-actions"><a class="btn btn--ghost" href="javascript:history.back()">返回上一步</a></div>', 0);
    }
}

/**
 * 向导支持的默认时区（与后台仓库时区配置同一组取值）
 */
function install_supported_timezones(): array
{
    return [
        'Asia/Shanghai'       => '中国标准时间（北京时间）',
        'UTC'                 => 'UTC 世界协调时',
        'America/Los_Angeles' => '美西时间（洛杉矶）',
        'America/New_York'    => '美东时间（纽约）',
        'Europe/London'       => '英国时间（伦敦）',
        'Europe/Berlin'       => '中欧时间（柏林）',
    ];
}

/**
 * 向导期时区：系统配置提交后按所选时区生成时间戳，提交前按运行时默认值
 */
function install_timezone(): string
{
    $tz = (string) ($_SESSION['install']['config']['timezone'] ?? '');
    return array_key_exists($tz, install_supported_timezones()) ? $tz : 'Asia/Shanghai';
}

/**
 * 向导跳转统一使用绝对路径（不带 .php），避免服务器对物理不存在的 .php 路径直接 404
 */
function install_redirect(string $step): never
{
    header('Location: /install/?step=' . $step);
    exit;
}

/**
 * 读取主应用 common.php 中的版本号（正则提取，避免加载主应用代码）
 */
function install_app_version(): string
{
    static $version = null;
    if ($version !== null) {
        return $version;
    }
    $content = (string) file_get_contents(APP_ROOT . '/common.php');
    if (preg_match("/define\(\s*'APP_VERSION'\s*,\s*'([^']+)'\s*\)/", $content, $m) === 1) {
        return $version = $m[1];
    }
    return $version = 'unknown';
}

/**
 * 渲染向导页面（含步骤指示器）
 */
function install_view(string $title, string $contentHtml, int $currentStep): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    $version = install_app_version();
    $steps = ['环境检测', '数据库配置', '管理员设置', '系统配置', '执行安装', '完成'];
    $items = '';
    foreach ($steps as $i => $label) {
        $no = $i + 1;
        $cls = $no < $currentStep ? 'is-done' : ($no === $currentStep ? 'is-current' : '');
        $items .= "<li class=\"{$cls}\"><span class=\"step-dot\">" . ($no < $currentStep ? '✓' : $no) . "</span>{$label}</li>";
    }
    $csrf = install_csrf_token();
    echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title} · 安装向导</title>
<link rel="stylesheet" href="/install_assets/style.css">
</head>
<body>
<div class="install-shell">
    <header class="install-head">
        <h1>WTZY 全球转运 · 安装向导</h1>
        <span class="install-version">v{$version}</span>
    </header>
    <ol class="steps">{$items}</ol>
    <main class="install-card">
    <input type="hidden" id="install-csrf" value="{$csrf}">
{$contentHtml}
    </main>
    <footer class="install-foot">全程约 2~5 分钟，遇到问题可返回上一步修改</footer>
</div>
<script src="/install_assets/script.js"></script>
</body>
</html>
HTML;
    exit;
}

/**
 * 连接 MySQL（可选指定库名），5 秒超时
 */
function install_db_connect(string $host, string $port, string $user, string $pass, ?string $name = null): PDO
{
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4' . ($name !== null ? ';dbname=' . $name : '');
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
}

/**
 * 将 PDO 异常转换为脱敏的中文提示（规范 §8.4.3：不暴露密码等敏感细节，但保留错误码定位能力）
 * PDO 异常存在两种格式：连接类 SQLSTATE[HY000] [1045] ...；查询类 SQLSTATE[42S01]: 文本: 1050 文本
 */
function install_db_error_message(PDOException $e): string
{
    $msg = $e->getMessage();
    $sqlstate = (string) $e->getCode();

    // 驱动错误码提取：连接类取 [xxxx] 括号段（排除 SQLSTATE 自身），查询类取冒号后的四位数字
    $driverCode = '';
    if (preg_match('/\[(\d+)\]/', $msg, $m) === 1 && $m[1] !== $sqlstate) {
        $driverCode = $m[1];
    }
    if ($driverCode === '' && preg_match('/:\s*(\d{4})\s/', $msg, $m) === 1) {
        $driverCode = $m[1];
    }

    $known = [
        '1044' => '数据库用户名或密码错误（或该账号无访问权限）',
        '1045' => '数据库用户名或密码错误（或该账号无访问权限）',
        '2002' => '无法连接数据库服务器：请确认地址与端口、MySQL 服务是否在运行（host 填 localhost 时走 socket，可改试 127.0.0.1）',
        '2003' => '无法连接数据库服务器：请确认地址与端口、MySQL 服务是否在运行',
        '1040' => '数据库连接数已达上限，请稍后重试',
        '1049' => '数据库不存在且当前账号无权限创建，请先手动创建数据库',
        '1046' => '未选择数据库',
        '1050' => '目标数据表已存在且无法幂等创建，请清空该数据库后重试',
        '1062' => '数据重复写入：目标数据库残留了此前安装的数据，可清空该数据库后重试',
        '1064' => 'SQL 语句执行失败（语法或 MySQL 版本兼容问题），详情见 storage/logs/install_error.log',
        '1071' => '索引长度超出限制：请在 MySQL 配置中开启 innodb_large_prefix 后重启 MySQL 再重试',
        '1114' => '数据表已满，无法写入',
        '1146' => '数据表不存在：表结构未导入完成，或表前缀与配置不一致',
        '1142' => '数据库账号权限不足：需要 CREATE / INSERT / UPDATE / INDEX 权限',
        '1143' => '数据库账号权限不足：需要 CREATE / INSERT / UPDATE / INDEX 权限',
        '1227' => '数据库账号权限不足：需要 CREATE / INSERT / UPDATE / INDEX 权限',
    ];
    if (isset($known[$driverCode])) {
        return $known[$driverCode];
    }

    // 消息关键词兜底（不同 PDO 版本错误码位置存在差异）
    if (str_contains($msg, 'Access denied')) {
        return $known['1045'];
    }
    if (str_contains($msg, 'No such file or directory') || str_contains($msg, 'Connection refused') || str_contains($msg, 'Connection timed out')) {
        return $known['2002'];
    }
    if (str_contains($msg, 'Unknown database')) {
        return $known['1049'];
    }
    if (str_contains($msg, 'Duplicate entry')) {
        return $known['1062'];
    }
    if (str_contains($msg, 'Specified key was too long')) {
        return $known['1071'];
    }
    if (str_contains($msg, 'syntax')) {
        return $known['1064'];
    }
    if (str_contains($msg, 'command denied') || str_contains($msg, 'privilege')) {
        return $known['1142'];
    }

    $suffix = $driverCode !== '' ? '，错误码 ' . $driverCode : '';
    return '数据库操作失败（SQLSTATE ' . $sqlstate . $suffix . '），详情见 storage/logs/install_error.log';
}

/**
 * 安装异常落盘（仅记录异常消息与阶段，不含任何密码字段），供排障使用
 */
function install_log_error(string $stage, string $detail): void
{
    $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $stage, $detail);
    @file_put_contents(APP_ROOT . '/storage/logs/install_error.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * 幂等导入建表脚本：CREATE TABLE 加 IF NOT EXISTS、INSERT 加 IGNORE，支持断点续装；
 * 脚本内 wtzy_ 为占位前缀，导入时统一替换为向导配置的实际前缀
 */
function install_import_schema(PDO $pdo, string $prefix): array
{
    $sql = (string) file_get_contents(SCHEMA_FILE);
    $lines = array_filter(
        explode("\n", $sql),
        static fn(string $line): bool => trim($line) !== '' && !preg_match('/^\s*--/', $line)
    );

    $statements = [];
    $buffer = '';
    foreach ($lines as $line) {
        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($line), ';')) {
            $statements[] = trim($buffer);
            $buffer = '';
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    $executed = 0;
    foreach ($statements as $index => $statement) {
        if ($statement === '') {
            continue;
        }
        $statement = (string) preg_replace('/CREATE TABLE\s+`/', 'CREATE TABLE IF NOT EXISTS `', $statement);
        $statement = (string) preg_replace('/INSERT INTO\s+`/', 'INSERT IGNORE INTO `', $statement);
        $statement = (string) preg_replace('/INSERT INTO\s+(wtzy_)/', 'INSERT IGNORE INTO $1', $statement);
        $statement = str_replace('wtzy_', $prefix, $statement);
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            // 原始异常落盘供排障；对外提示定位到第 N 条语句
            install_log_error('import', sprintf("第 %d 条语句失败\nSQL: %s\n异常: %s", $index + 1, $statement, $e->getMessage()));
            $snippet = mb_substr((string) preg_replace('/\s+/', ' ', $statement), 0, 60);
            throw new RuntimeException(
                '建表脚本导入失败（第 ' . ($index + 1) . ' 条语句：' . $snippet . '…）：' . install_db_error_message($e)
            );
        }
        $executed++;
    }
    return ['executed' => $executed];
}

function install_write_env(array $cfg): bool
{
    $key = 'base64:' . base64_encode(random_bytes(32));
    $csrfSecret = bin2hex(random_bytes(16));
    $content = <<<ENV
# .env —— 由安装向导自动生成，禁止提交到版本库
APP_ENV=production
APP_DEBUG=false
APP_URL={$cfg['url']}

DB_HOST={$cfg['host']}
DB_PORT={$cfg['port']}
DB_NAME={$cfg['name']}
DB_USER={$cfg['user']}
DB_PASS={$cfg['pass']}
DB_PREFIX={$cfg['prefix']}
DB_CHARSET=utf8mb4

APP_KEY={$key}
CSRF_TOKEN_SECRET={$csrfSecret}

APP_DEFAULT_LANG=zh-CN
APP_DEFAULT_CURRENCY=CNY
APP_DEFAULT_TIMEZONE={$cfg['timezone']}

# 邮件发送（找回密码验证码依赖此项；未填写则邮件相关功能自动停用）
# MAIL_HOST=smtp.example.com
# MAIL_PORT=465
# MAIL_SECURE=ssl
# MAIL_USER=
# MAIL_PASS=
# MAIL_FROM=
# MAIL_FROM_NAME=WTZY 全球转运

ENV;
    return file_put_contents(ENV_FILE, $content, LOCK_EX) !== false;
}

function install_write_lock(array $info): bool
{
    $export = var_export($info, true);
    $content = "<?php\n// 安装锁：存在即视为已安装（规范 §8.4.6）\nreturn {$export};\n";
    return file_put_contents(LOCK_FILE, $content, LOCK_EX) !== false;
}
