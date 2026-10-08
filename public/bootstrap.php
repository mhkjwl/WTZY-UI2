<?php declare(strict_types=1);
/**
 * @file public/bootstrap.php
 * @description 应用引导：安装守卫、环境装载、会话初始化、异常兜底、四端路由分发（要求 PHP 8.5+）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 *
 * 本文件含 PHP 8.x 语法，只能由 index.php 的版本门卫校验后加载；
 * 直接访问本文件（不经入口）将被拒绝。
 */

if (!defined('WTZY_ENTRY')) {
    exit('非法访问');
}

define('BASE_PATH', dirname(WTZY_ENTRY));

// ── 一键安装守卫（规范 §8.4）────────────────────────────
$installLockFile = BASE_PATH . '/install/lock/installed.lock';
$uriPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_file($installLockFile)) {
    // 未安装：除安装向导外的所有请求一律导入安装流程
    //（向导样式脚本在 public/install_assets/，由 Web 服务器直接服务，不经此处）
    if (!preg_match('#^/install(/|$)#', $uriPath)) {
        header('Location: /install/');
        exit;
    }
    require BASE_PATH . '/install/index.php';
    exit;
}
if (preg_match('#^/install(/|$)#', $uriPath) === 1) {
    // 已安装：锁文件生效，拒绝重复访问安装入口
    header('Location: /');
    exit('系统已安装。如需重新安装，请删除 install/lock/installed.lock 文件。');
}

require BASE_PATH . '/common.php';
require BASE_PATH . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;

Autoloader::register();
Env::load(BASE_PATH . '/.env');
Config::get('app.name');

// 会话 Cookie：HttpOnly + SameSite=Lax，生产环境 HTTPS 下应由 .env 开启 secure
session_name('WTZYSESS');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => str_starts_with((string) Config::get('app.url'), 'https://'),
]);
session_start();

// 全局异常兜底：接口返回 JSON 信封，页面渲染 500 视图；生产环境不泄露异常细节
set_exception_handler(static function (Throwable $e): void {
    http_response_code(500);
    $debug = (bool) Config::get('app.debug', false);
    if (Request::wantsJson()) {
        Response::json([
            'success' => false,
            'message' => $debug ? '服务器异常: ' . $e->getMessage() : '服务器开小差了，请稍后重试',
            'data'    => null,
        ], 500);
    }
    try {
        View::render('errors/500', ['title' => '系统错误', 'debug' => $debug, 'error' => $e->getMessage()], null);
    } catch (Throwable) {
        echo '500 Internal Server Error';
    }
    exit;
});

$router = new Router();
$router->onNotFound(static function (): void {
    http_response_code(404);
    if (Request::wantsJson()) {
        Response::json(['success' => false, 'message' => '资源不存在', 'data' => null], 404);
    }
    View::render('errors/404', ['title' => '页面不存在'], null);
});

// 全站公共路由（首页）
$webRoutes = require BASE_PATH . '/routes/web.php';
$webRoutes($router);

// 按路径首段装载对应端的路由：/user /warehouse /admin /api /pay
$endRouteMap = [
    'user'      => 'user',
    'warehouse' => 'warehouse',
    'admin'     => 'admin',
    'api'       => 'openapi',
    'pay'       => 'pay',
];
$firstSegment = explode('/', trim(Request::path(), '/'))[0] ?? '';
if (isset($endRouteMap[$firstSegment])) {
    $endRoutes = require BASE_PATH . '/routes/' . $endRouteMap[$firstSegment] . '.php';
    $endRoutes($router);
}

$router->dispatch(Request::method(), Request::path());
