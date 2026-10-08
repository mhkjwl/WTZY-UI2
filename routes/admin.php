<?php declare(strict_types=1);
/**
 * @file routes/admin.php
 * @description 管理端路由（运营/客服/财务/管理员；各模块写操作均挂独立权限点）
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\ChannelController;
use App\Controllers\Admin\ClaimController;
use App\Controllers\Admin\CustomsController;
use App\Controllers\Admin\FinanceController;
use App\Controllers\Admin\HomeController;
use App\Controllers\Admin\MarketingController;
use App\Controllers\Admin\MemberController;
use App\Controllers\Admin\OrderController;
use App\Controllers\Admin\PackageController;
use App\Controllers\Admin\RateController;
use App\Controllers\Admin\ReportController;
use App\Controllers\Admin\ServiceController;
use App\Controllers\Admin\SystemController;
use App\Controllers\Admin\TicketController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\WarehouseController;
use App\Core\Router;

return static function (Router $router): void {
    // 认证
    $router->get('/admin/login', [AuthController::class, 'showLogin']);
    $router->post('/admin/login', [AuthController::class, 'login']);
    $router->post('/admin/logout', [AuthController::class, 'logout']);

    // 控制台
    $router->get('/admin', [HomeController::class, 'index']);
    // 侧边栏待办数量（前端刷新徽标用）
    $router->get('/admin/todos', [HomeController::class, 'todos']);
    $router->get('/admin/nav', [HomeController::class, 'nav']);

    // 用户管理
    $router->get('/admin/users', [UserController::class, 'index']);
    $router->get('/admin/users/kyc', [UserController::class, 'kyc']);
    $router->post('/admin/users/kyc/review', [UserController::class, 'reviewKyc']);
    $router->get('/admin/users/{id}', [UserController::class, 'detail']);
    $router->post('/admin/users/save', [UserController::class, 'save']);
    $router->post('/admin/users/audit', [UserController::class, 'audit']);
    $router->post('/admin/users/risk', [UserController::class, 'risk']);
    $router->post('/admin/users/balance', [UserController::class, 'balance']);

    // 包裹管理（与会员中心「我的包裹」同源，供客服检索与答复）
    $router->get('/admin/packages', [PackageController::class, 'index']);
    $router->get('/admin/packages/{id}', [PackageController::class, 'detail']);

    // 订单运单
    $router->get('/admin/orders', [OrderController::class, 'index']);
    $router->get('/admin/orders/{id}', [OrderController::class, 'detail']);
    $router->post('/admin/orders/cancel', [OrderController::class, 'cancel']);
    $router->post('/admin/orders/intercept', [OrderController::class, 'intercept']);
    $router->post('/admin/orders/advance', [OrderController::class, 'advance']);

    // 仓库管理
    $router->get('/admin/warehouse', [WarehouseController::class, 'index']);
    $router->get('/admin/warehouse/reports', [WarehouseController::class, 'reports']);
    $router->post('/admin/warehouse/save', [WarehouseController::class, 'save']);
    $router->post('/admin/warehouse/toggle', [WarehouseController::class, 'toggle']);
    $router->post('/admin/warehouse/location', [WarehouseController::class, 'addLocation']);
    $router->post('/admin/warehouse/location/batch', [WarehouseController::class, 'batchLocation']);
    $router->post('/admin/warehouse/location/update', [WarehouseController::class, 'updateLocation']);
    $router->post('/admin/warehouse/location/toggle', [WarehouseController::class, 'toggleLocation']);

    // 物流渠道
    // 转运资费（前台资费页的内容来源）
    $router->get('/admin/rates', [RateController::class, 'index']);
    $router->post('/admin/rates/save', [RateController::class, 'save']);
    // 增值服务（用户下单页可勾选服务的字典维护）
    $router->get('/admin/services', [ServiceController::class, 'index']);
    $router->get('/admin/services/list', [ServiceController::class, 'list']);
    $router->post('/admin/services/save', [ServiceController::class, 'save']);
    $router->post('/admin/services/toggle', [ServiceController::class, 'toggle']);
    $router->post('/admin/services/delete', [ServiceController::class, 'delete']);
    $router->post('/admin/services/rules', [ServiceController::class, 'saveRules']);
    $router->get('/admin/channels', [ChannelController::class, 'index']);
    $router->get('/admin/channels/{id}', [ChannelController::class, 'detail']);
    $router->post('/admin/channels/save', [ChannelController::class, 'save']);
    $router->post('/admin/channels/toggle', [ChannelController::class, 'toggle']);
    $router->post('/admin/channels/zone', [ChannelController::class, 'addZone']);
    $router->post('/admin/channels/surcharge', [ChannelController::class, 'saveSurcharge']);
    $router->post('/admin/channels/price', [ChannelController::class, 'savePrice']);

    // 清关管理
    $router->get('/admin/customs', [CustomsController::class, 'index']);
    $router->post('/admin/customs/clear-status', [CustomsController::class, 'updateClear']);

    // 财务中心
    $router->get('/admin/finance', [FinanceController::class, 'index']);
    $router->post('/admin/finance/recharge/confirm', [FinanceController::class, 'confirmRecharge']);
    $router->post('/admin/finance/recharge/close', [FinanceController::class, 'closeRecharge']);
    $router->post('/admin/finance/recharge/sync', [FinanceController::class, 'syncRecharge']);
    $router->post('/admin/finance/recharge/refund', [FinanceController::class, 'refundRecharge']);
    $router->post('/admin/finance/rate', [FinanceController::class, 'saveRate']);

    // 充值设置
    $router->get('/admin/finance/recharge-settings', [FinanceController::class, 'rechargeSettings']);
    $router->post('/admin/finance/recharge-settings/save', [FinanceController::class, 'saveRechargeSettings']);
    // 发票管理（finance.view 查看 / finance.manage 开票·驳回·开关）
    $router->get('/admin/finance/invoices', [FinanceController::class, 'invoices']);
    $router->post('/admin/finance/invoices/handle', [FinanceController::class, 'handleInvoice']);
    $router->post('/admin/finance/invoices/toggle', [FinanceController::class, 'toggleInvoice']);

    // 客服工单（固定路径须注册在 {id} 之前）
    $router->get('/admin/tickets', [TicketController::class, 'index']);
    $router->get('/admin/tickets/{id}/thread', [TicketController::class, 'thread']);
    $router->get('/admin/tickets/{id}', [TicketController::class, 'detail']);
    $router->post('/admin/tickets/assign', [TicketController::class, 'assign']);
    $router->post('/admin/tickets/reply', [TicketController::class, 'reply']);
    $router->post('/admin/tickets/close', [TicketController::class, 'close']);

    // 理赔处理（复用售后权限域：ticket.view / ticket.handle）
    // 理赔处理（多级审核：claim.view 查看 / claim.review 初审 / claim.finance_audit 财务 / claim.admin_audit 终审 / claim.pay 赔付）
    $router->get('/admin/claims', [ClaimController::class, 'index']);
    $router->get('/admin/claims/{id}', [ClaimController::class, 'detail']);
    $router->post('/admin/claims/accept', [ClaimController::class, 'accept']);
    $router->post('/admin/claims/review', [ClaimController::class, 'review']);
    $router->post('/admin/claims/finance-audit', [ClaimController::class, 'financeAudit']);
    $router->post('/admin/claims/admin-audit', [ClaimController::class, 'adminAudit']);
    $router->post('/admin/claims/reject', [ClaimController::class, 'reject']);
    $router->post('/admin/claims/pay', [ClaimController::class, 'pay']);

    // 系统设置
    $router->get('/admin/system/site', [SystemController::class, 'site']);
    $router->post('/admin/system/site/save', [SystemController::class, 'saveSite']);
    $router->post('/admin/system/site/upload', [SystemController::class, 'uploadBrand']);
    $router->get('/admin/system/mail', [SystemController::class, 'mail']);
    $router->post('/admin/system/mail/save', [SystemController::class, 'saveMail']);
    $router->post('/admin/system/mail/test', [SystemController::class, 'testMail']);
    $router->get('/admin/system/tasks', [SystemController::class, 'tasks']);
    $router->post('/admin/system/tasks/run', [SystemController::class, 'runTasks']);
    $router->post('/admin/system/tasks/token', [SystemController::class, 'resetTaskToken']);
    $router->get('/admin/system/staff', [SystemController::class, 'staff']);
    $router->post('/admin/system/staff/save', [SystemController::class, 'saveStaff']);
    $router->post('/admin/system/staff/update', [SystemController::class, 'updateStaff']);
    $router->post('/admin/system/staff/toggle', [SystemController::class, 'toggleStaff']);
    $router->get('/admin/system/roles', [SystemController::class, 'roles']);
    $router->post('/admin/system/roles/matrix', [SystemController::class, 'saveMatrix']);

    // 会员与营销
    $router->get('/admin/member/levels', [MemberController::class, 'levels']);
    $router->post('/admin/member/levels/save', [MemberController::class, 'saveLevel']);
    $router->get('/admin/member/coupons', [MemberController::class, 'coupons']);
    $router->get('/admin/member/coupons/list', [MemberController::class, 'couponList']);
    $router->post('/admin/member/coupons/save', [MemberController::class, 'saveCoupon']);
    $router->post('/admin/member/coupons/toggle', [MemberController::class, 'toggleCoupon']);
    $router->post('/admin/member/coupons/grant', [MemberController::class, 'grantCoupon']);
    $router->get('/admin/member/invite', [MemberController::class, 'invite']);
    $router->post('/admin/member/invite/save', [MemberController::class, 'saveInvite']);
    $router->post('/admin/member/invite/pay', [MemberController::class, 'payInviteRewards']);

    // 数据报表
    $router->get('/admin/reports', [ReportController::class, 'index']);

    // 营销与内容
    $router->get('/admin/marketing', [MarketingController::class, 'index']);
    $router->post('/admin/marketing/notice', [MarketingController::class, 'createNotice']);
    $router->post('/admin/marketing/notice/update', [MarketingController::class, 'updateNotice']);
    $router->post('/admin/marketing/notice/delete', [MarketingController::class, 'deleteNotice']);
    $router->post('/admin/marketing/notice/resend', [MarketingController::class, 'resendNotice']);
    $router->post('/admin/marketing/content', [MarketingController::class, 'saveContent']);
};
