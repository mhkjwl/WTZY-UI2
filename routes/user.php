<?php declare(strict_types=1);
/**
 * @file routes/user.php
 * @description 用户端路由：认证入口 + 会员中心（后台同款外壳）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-15
 */

use App\Controllers\Member\AddressController;
use App\Controllers\Member\ClaimController;
use App\Controllers\Member\DashboardController;
use App\Controllers\Member\FinanceController;
use App\Controllers\Member\ForecastController;
use App\Controllers\Member\MemberController;
use App\Controllers\Member\MessageController;
use App\Controllers\Member\PackageController;
use App\Controllers\Member\ProfileController;
use App\Controllers\Member\TicketController;
use App\Controllers\Member\TrackingController;
use App\Controllers\Member\WaybillController;
use App\Controllers\User\AuthController;
use App\Core\Router;

return static function (Router $router): void {
    // 认证（官网外壳）
    $router->get('/user/login', [AuthController::class, 'showLogin']);
    $router->get('/user/register', [AuthController::class, 'showRegister']);
    $router->get('/user/forgot', [AuthController::class, 'showForgot']);
    $router->post('/user/forgot/send', [AuthController::class, 'sendResetCode']);
    $router->post('/user/forgot/reset', [AuthController::class, 'resetPassword']);
    $router->post('/user/register', [AuthController::class, 'register']);
    $router->post('/user/register/send-code', [AuthController::class, 'sendRegisterCode']);
    $router->post('/user/login', [AuthController::class, 'login']);
    $router->post('/user/logout', [AuthController::class, 'logout']);

    // 会员中心
    $router->get('/user', [DashboardController::class, 'index']);
    // 侧边栏待办数量（前端刷新徽标用）
    $router->get('/user/todos', [DashboardController::class, 'todos']);
    $router->get('/user/messages', [MessageController::class, 'index']);
    $router->get('/user/notices', [MessageController::class, 'notices']);
    $router->get('/user/notices/{id}', [MessageController::class, 'noticeDetail']);
    $router->post('/user/messages/read', [MessageController::class, 'read']);
    $router->post('/user/messages/read-all', [MessageController::class, 'readAll']);
    $router->get('/user/addresses', [AddressController::class, 'warehouses']);
    $router->get('/user/shipping', [AddressController::class, 'shipping']);
    $router->get('/user/shipping/list', [AddressController::class, 'shippingList']);
    $router->post('/user/shipping/save', [AddressController::class, 'saveShipping']);
    $router->post('/user/shipping/delete', [AddressController::class, 'deleteShipping']);

    // 包裹预报
    $router->get('/user/forecast', [ForecastController::class, 'index']);
    $router->post('/user/forecast/save', [ForecastController::class, 'save']);
    $router->post('/user/forecast/upload', [ForecastController::class, 'upload']);

    // 我的包裹（固定路径须注册在 {id} 之前）
    $router->get('/user/packages', [PackageController::class, 'index']);
    $router->get('/user/packages/abnormal', [PackageController::class, 'abnormal']);
    $router->post('/user/packages/claim', [PackageController::class, 'claim']);
    $router->post('/user/packages/disposal', [PackageController::class, 'disposal']);
    $router->post('/user/packages/disposal/cancel', [PackageController::class, 'cancelDisposal']);
    $router->get('/user/packages/{id}', [PackageController::class, 'detail']);

    // 转运订单（固定路径须注册在 {id} 之前）
    $router->get('/user/waybills', [WaybillController::class, 'index']);
    $router->get('/user/waybills/create', [WaybillController::class, 'create']);
    $router->post('/user/waybills/estimate', [WaybillController::class, 'estimate']);
    $router->post('/user/waybills/save', [WaybillController::class, 'save']);
    $router->post('/user/waybills/pay', [WaybillController::class, 'pay']);
    $router->post('/user/waybills/cancel', [WaybillController::class, 'cancel']);
    $router->post('/user/waybills/sign', [WaybillController::class, 'sign']);
    $router->get('/user/waybills/{id}', [WaybillController::class, 'detail']);

    // 物流跟踪
    $router->get('/user/tracking', [TrackingController::class, 'index']);

    // 财务中心
    $router->get('/user/finance', [FinanceController::class, 'index']);
    $router->get('/user/finance/recharge', [FinanceController::class, 'recharge']);
    $router->post('/user/finance/recharge/create', [FinanceController::class, 'createRecharge']);
    $router->post('/user/finance/recharge/pay', [FinanceController::class, 'payOrder']);
    $router->post('/user/finance/recharge/status', [FinanceController::class, 'payStatus']);
    $router->post('/user/finance/recharge/cancel', [FinanceController::class, 'cancelRecharge']);
    $router->post('/user/finance/recharge/paid', [FinanceController::class, 'markPaid']);
    $router->get('/user/finance/flows', [FinanceController::class, 'flows']);
    $router->get('/user/finance/invoices', [FinanceController::class, 'invoices']);
    $router->post('/user/finance/invoices/create', [FinanceController::class, 'createInvoice']);

    // 会员与营销
    $router->get('/user/member', [MemberController::class, 'index']);
    $router->get('/user/member/coupons', [MemberController::class, 'coupons']);
    $router->get('/user/member/coupons/mine', [MemberController::class, 'couponList']);
    $router->post('/user/member/coupons/receive', [MemberController::class, 'receiveCoupon']);
    $router->get('/user/member/points', [MemberController::class, 'points']);
    $router->get('/user/member/invite', [MemberController::class, 'invite']);

    // 客服工单（固定路径须注册在 {id} 之前）
    $router->get('/user/tickets', [TicketController::class, 'index']);
    $router->get('/user/tickets/create', [TicketController::class, 'create']);
    $router->post('/user/tickets/save', [TicketController::class, 'save']);
    $router->post('/user/tickets/reply', [TicketController::class, 'reply']);
    $router->post('/user/tickets/close', [TicketController::class, 'close']);
    $router->get('/user/tickets/{id}/thread', [TicketController::class, 'thread']);
    $router->get('/user/tickets/{id}', [TicketController::class, 'detail']);

    // 理赔申请
    $router->get('/user/claims', [ClaimController::class, 'index']);
    $router->get('/user/claims/create', [ClaimController::class, 'create']);
    $router->post('/user/claims/save', [ClaimController::class, 'save']);
    $router->get('/user/claims/{id}', [ClaimController::class, 'detail']);

    $router->get('/user/profile', [ProfileController::class, 'index']);
    $router->post('/user/profile/save', [ProfileController::class, 'saveProfile']);
    $router->post('/user/profile/password', [ProfileController::class, 'password']);
    $router->post('/user/profile/send-bind-code', [ProfileController::class, 'sendBindCode']);
    $router->post('/user/profile/bind-email', [ProfileController::class, 'bindEmail']);
    $router->get('/user/kyc', [ProfileController::class, 'kyc']);
    $router->post('/user/kyc/upload', [ProfileController::class, 'uploadKycPhoto']);
    $router->post('/user/kyc/submit', [ProfileController::class, 'submitKyc']);
};
