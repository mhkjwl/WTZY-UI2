<?php declare(strict_types=1);
/**
 * @file routes/pay.php
 * @description 支付网关回调路由（公开访问，凭网关 RSA 签名鉴权，不走登录态与 CSRF）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Controllers\Pay\NotifyController;
use App\Core\Router;

return static function (Router $router): void {
    // 异步通知：网关服务器回调，需返回 success
    // GET 为易支付协议要求（其通知以 GET 传参，见 EpayService::notifyParams），属规范 §5.5.5
    // 「第三方回调」协议层面例外；POST 并列为兼容写法。入账安全性由 RSA 验签 + 商户号 + 金额
    // 三重校验与幂等翻转保证，伪造请求无法造成入账。
    $router->get('/pay/notify/epay', [NotifyController::class, 'epayNotify']);
    $router->post('/pay/notify/epay', [NotifyController::class, 'epayNotify']);
    // 页面跳转通知：用户付款后浏览器回跳（只读展示，不写库）
    $router->get('/pay/return/epay', [NotifyController::class, 'epayReturn']);
};
