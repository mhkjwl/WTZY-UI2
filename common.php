<?php declare(strict_types=1);
/**
 * @file common.php
 * @description 公共配置文件（初始化自动创建）
 * @author AI
 * @version 0.6.1
 * @date 2026-10-08
 */

// ============================================================
// 版本信息
// ============================================================
defined('APP_NAME')    || define('APP_NAME', 'WTZY');
defined('APP_VERSION') || define('APP_VERSION', '0.6.1');
// 运行环境取自 .env（本文件先于 Env 类加载，故就地读取）；无法读取时按生产环境处理，
// 避免线上把 PHP 错误细节暴露给访问者
defined('APP_ENV')     || define('APP_ENV', wtzy_env_mode());

function wtzy_env_mode(): string
{
    static $mode = null;
    if ($mode !== null) {
        return $mode;
    }
    $mode = 'production';
    $envFile = __DIR__ . '/.env';
    if (is_file($envFile)) {
        $content = (string) @file_get_contents($envFile);
        if (preg_match('/^\s*APP_ENV\s*=\s*([A-Za-z]+)/m', $content, $m) === 1) {
            $mode = strtolower($m[1]);
        }
    }
    return $mode;
}

// ============================================================
// 安全头（基础防护）
// ============================================================
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ============================================================
// 错误报告（开发环境显示，生产环境关闭）
// ============================================================
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
}

// ============================================================
// 时区（与 APP_ENV 同理：本文件先于 Env 类加载，故就地读取 .env；
// 未配置或取值无效时按平台默认时区，避免无效值影响日期函数）
// ============================================================
date_default_timezone_set(wtzy_default_timezone());

function wtzy_default_timezone(): string
{
    static $timezone = null;
    if ($timezone !== null) {
        return $timezone;
    }
    $timezone = 'Asia/Shanghai';
    $envFile = __DIR__ . '/.env';
    if (is_file($envFile)) {
        $content = (string) @file_get_contents($envFile);
        if (preg_match('/^\s*APP_DEFAULT_TIMEZONE\s*=\s*(\S+)/m', $content, $m) === 1) {
            $candidate = trim($m[1], "\"'");
            $identifiers = timezone_identifiers_list();
            if (is_array($identifiers) && in_array($candidate, $identifiers, true)) {
                $timezone = $candidate;
            }
        }
    }
    return $timezone;
}

// ============================================================
// 字符集
// ============================================================
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
