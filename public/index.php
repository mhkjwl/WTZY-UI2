<?php
/**
 * @file public/index.php
 * @description 统一入口：内置服务器静态直通 + PHP 版本门卫 + 应用引导加载
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 *
 * 注意：本文件语法保持 PHP 7.x 可解析（低版本环境友好降级为提示页，而非裸报错），
 * 全部应用代码在 bootstrap.php（要求 PHP 8.5+）。
 * 本地调试：php -S 127.0.0.1:8099 -t dev/public dev/public/index.php
 */

// 内置服务器：命中真实存在的静态文件时交回内置服务器，其余请求走前端控制器
if (PHP_SAPI === 'cli-server') {
    $requestPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $candidate = realpath(__DIR__ . $requestPath);
    if ($candidate !== false && is_file($candidate)
        && strpos($candidate, __DIR__ . DIRECTORY_SEPARATOR) === 0) {
        return false;
    }
}

// PHP 版本门卫：低于 8.5 时渲染友好提示页（本段及以下之前不加载任何应用代码）
if (version_compare(PHP_VERSION, '8.5.0', '<')) {
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(500);
    $requiredVersion = '8.5.0';
    $currentVersion = PHP_VERSION;
    echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PHP 版本不满足要求</title>
<style>
body { font-family: "PingFang SC", "Microsoft YaHei", system-ui, sans-serif; background: #f5f7fb;
       color: #1f2937; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
.card { max-width: 560px; background: #fff; border: 1px solid #e5e9f2; border-radius: 12px;
        padding: 36px 32px; box-shadow: 0 1px 3px rgba(15,23,42,.08); }
.icon { font-size: 40px; }
h1 { font-size: 20px; margin: 12px 0 16px; }
p { font-size: 14px; line-height: 1.8; color: #6b7280; margin: 6px 0; }
p b { color: #dc2626; }
p .ok { color: #2563eb; }
code { background: #eef1f6; border-radius: 4px; padding: 2px 6px; font-size: 13px; }
.tip { margin-top: 16px; padding: 10px 14px; background: rgba(245,158,11,.1); border-radius: 8px;
       font-size: 13px; color: #b45309; }
</style>
</head>
<body>
<div class="card">
    <div class="icon">⚠️</div>
    <h1>PHP 版本不满足要求</h1>
    <p>本系统要求 <span class="ok">PHP {$requiredVersion} 及以上</span>，当前服务器版本为 <b>{$currentVersion}</b>，无法运行。</p>
    <p>请在主机管理面板将本站点 PHP 版本切换为 8.5 及以上（宝塔面板：网站 → 站点设置 → PHP 版本），切换后刷新本页。</p>
    <p class="tip">切换版本后若仍显示本页，请重启 PHP-FPM / Web 服务；如主机最高仅支持 7.x，需更换支持 PHP 8.5 的运行环境。</p>
</div>
</body>
</html>
HTML;
    exit(1);
}

define('WTZY_ENTRY', __DIR__);
require __DIR__ . '/bootstrap.php';
