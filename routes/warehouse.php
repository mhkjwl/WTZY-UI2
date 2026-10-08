<?php declare(strict_types=1);
/**
 * @file routes/warehouse.php
 * @description 仓库端路由（操作员 / 主管 / 打包员 / 出库员；作业动作按 wh.* 权限点门卫）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Controllers\Warehouse\AbnormalController;
use App\Controllers\Warehouse\AuthController;
use App\Controllers\Warehouse\HomeController;
use App\Controllers\Warehouse\InboundController;
use App\Controllers\Warehouse\OutboundController;
use App\Controllers\Warehouse\PackingController;
use App\Controllers\Warehouse\StorageController;
use App\Controllers\Warehouse\TaskController;
use App\Core\Router;

return static function (Router $router): void {
    // 认证
    $router->get('/warehouse/login', [AuthController::class, 'showLogin']);
    $router->post('/warehouse/login', [AuthController::class, 'login']);
    $router->post('/warehouse/logout', [AuthController::class, 'logout']);

    // 作业台 + 待办数量（前端刷新徽标用）
    $router->get('/warehouse', [HomeController::class, 'index']);
    $router->get('/warehouse/todos', [HomeController::class, 'todos']);

    // 收货入库
    $router->get('/warehouse/inbound', [InboundController::class, 'index']);
    $router->post('/warehouse/inbound/lookup', [InboundController::class, 'lookup']);
    $router->post('/warehouse/inbound/save', [InboundController::class, 'save']);
    $router->post('/warehouse/inbound/unattributed', [InboundController::class, 'unattributed']);
    $router->post('/warehouse/inbound/manual', [InboundController::class, 'manual']);
    $router->post('/warehouse/inbound/upload', [InboundController::class, 'upload']);

    // 仓储管理
    $router->get('/warehouse/storage', [StorageController::class, 'index']);
    $router->get('/warehouse/storage/locations', [StorageController::class, 'locations']);
    $router->get('/warehouse/storage/package/{id}', [StorageController::class, 'packageDetail']);
    $router->post('/warehouse/storage/shelve', [StorageController::class, 'shelve']);
    $router->post('/warehouse/storage/unshelve', [StorageController::class, 'unshelve']);
    $router->post('/warehouse/storage/attribute', [StorageController::class, 'attribute']);
    $router->post('/warehouse/storage/upload', [StorageController::class, 'upload']);

    // 打包称重（固定路径须注册在 {id} 之前）
    $router->get('/warehouse/packing', [PackingController::class, 'index']);
    $router->get('/warehouse/packing/status', [PackingController::class, 'status']);
    $router->post('/warehouse/packing/save', [PackingController::class, 'save']);
    $router->post('/warehouse/packing/upload', [PackingController::class, 'upload']);
    $router->get('/warehouse/packing/{id}', [PackingController::class, 'detail']);

    // 出库交接
    $router->get('/warehouse/outbound', [OutboundController::class, 'index']);
    $router->post('/warehouse/outbound/save', [OutboundController::class, 'save']);

    // 异常处理
    $router->get('/warehouse/abnormal', [AbnormalController::class, 'index']);
    $router->post('/warehouse/abnormal/save', [AbnormalController::class, 'save']);
    $router->post('/warehouse/abnormal/register-by-no', [AbnormalController::class, 'registerByNo']);
    $router->post('/warehouse/abnormal/upload', [AbnormalController::class, 'upload']);
    $router->post('/warehouse/abnormal/handle', [AbnormalController::class, 'handle']);

    // 任务分配
    $router->get('/warehouse/tasks', [TaskController::class, 'index']);
    $router->post('/warehouse/tasks/save', [TaskController::class, 'save']);
    $router->post('/warehouse/tasks/cancel', [TaskController::class, 'cancel']);
};
