<?php declare(strict_types=1);
/**
 * @file install/steps/step1_env.php
 * @description 安装第一步：环境检测（PHP 版本、扩展、目录权限）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

declare(strict_types=1);

$checks = [
    ['PHP 版本 ≥ 8.5', PHP_VERSION, version_compare(PHP_VERSION, '8.5.0', '>=')],
    ['PDO MySQL 扩展', extension_loaded('pdo_mysql') ? '已安装' : '未安装', extension_loaded('pdo_mysql')],
    ['JSON 扩展', extension_loaded('json') ? '已安装' : '未安装', extension_loaded('json')],
    ['MBString 扩展', extension_loaded('mbstring') ? '已安装' : '未安装', extension_loaded('mbstring')],
    ['OpenSSL 扩展', extension_loaded('openssl') ? '已安装' : '未安装', extension_loaded('openssl')],
    // 图片上传按文件头校验类型：优先 fileinfo，未启用时用标准库 getimagesize 兜底
    ['图片类型检测', class_exists('finfo') ? 'fileinfo 已启用' : (function_exists('getimagesize') ? '回退 getimagesize' : '均不可用'), class_exists('finfo') || function_exists('getimagesize')],
    ['根目录可写（生成 .env）', is_writable(APP_ROOT) ? '可写' : '不可写', is_writable(APP_ROOT)],
    ['install/lock 可写（安装锁）', is_writable(INSTALL_ROOT . '/lock') ? '可写' : '不可写', is_writable(INSTALL_ROOT . '/lock')],
    ['storage/logs 可写（运行日志）', is_writable(APP_ROOT . '/storage/logs') ? '可写' : '不可写', is_writable(APP_ROOT . '/storage/logs')],
    ['public/uploads 可写（用户上传）', is_writable(APP_ROOT . '/public/uploads') ? '可写' : '不可写', is_writable(APP_ROOT . '/public/uploads')],
];

$allPass = true;
$rows = '';
foreach ($checks as [$name, $current, $pass]) {
    $allPass = $allPass && $pass;
    $rows .= '<tr><td>' . htmlspecialchars($name) . '</td><td>' . htmlspecialchars($current) . '</td><td>'
        . ($pass ? '<span class="badge badge--ok">通过</span>' : '<span class="badge badge--fail">不通过</span>') . '</td></tr>';
}

$next = $allPass
    ? '<a class="btn btn--primary" href="/install/?step=2">下一步：数据库配置 →</a>'
    : '<p class="fix-hint">请先修复以上不通过项（如为目录权限问题，请授予 Web 运行账号写入权限）后刷新本页。</p>';

install_view('环境检测', <<<HTML
<h2 class="card-title">环境检测</h2>
<table class="check-table">
    <thead><tr><th>检查项</th><th>当前环境</th><th>结果</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>
<div class="card-actions">{$next}</div>
HTML, 1);
