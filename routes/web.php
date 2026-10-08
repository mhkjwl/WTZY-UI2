<?php declare(strict_types=1);
/**
 * @file routes/web.php
 * @description 全站公共路由（首页门户）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

use App\Controllers\Web\CaptchaController;
use App\Controllers\Web\HomeController;
use App\Controllers\Web\RateController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    // 转运资费（公开页，内容来自后台「转运资费」）
    $router->get('/rates', [RateController::class, 'index']);
    $router->get('/captcha', [CaptchaController::class, 'image']);
};
