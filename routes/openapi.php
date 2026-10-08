<?php declare(strict_types=1);
/**
 * @file routes/openapi.php
 * @description 开放 API 路由（物流商 / 清关行 / 支付渠道 / 电商平台，URL 前缀 /api）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */

use App\Controllers\OpenApi\PingController;
use App\Controllers\OpenApi\TaskController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/api/ping', [PingController::class, 'ping']);
    // 自动任务远程触发：外部定时任务访问 /api/tasks/run?token=xxx 执行（token 由后台生成）
    $router->get('/api/tasks/run', [TaskController::class, 'run']);
};
