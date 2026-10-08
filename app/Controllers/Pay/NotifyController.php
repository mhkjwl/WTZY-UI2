<?php declare(strict_types=1);
/**
 * @file app/Controllers/Pay/NotifyController.php
 * @description 支付网关回调：异步通知入账（notify_url）与页面跳转通知（return_url）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 *
 * 本控制器不经登录态，凭网关 RSA 签名鉴权：验签失败、状态非成功、金额不符一律拒绝入账。
 */

namespace App\Controllers\Pay;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\View;
use App\Enums\Status;
use App\Models\RechargeOrderModel;
use App\Services\EpayService;
use App\Services\RechargeService;
use Throwable;

final class NotifyController extends Controller
{
    /** 网关要求收到通知后返回该字符串表示已接收 */
    private const ACK = 'success';

    /**
     * 异步通知：验签 → 校验交易状态与金额 → 入账，必须返回 success 才会停止重推
     */
    public function epayNotify(): never
    {
        $params = EpayService::notifyParams();
        if ($params === []) {
            // 无参访问多为扫描器，直接拒绝且不留日志，避免污染支付日志
            echo 'fail';
            exit;
        }
        if (!EpayService::verify($params)) {
            $this->reply('fail', '回调验签失败', $params);
        }
        if ((string) ($params['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
            $this->reply('fail', '交易状态非成功：' . (string) ($params['trade_status'] ?? ''), $params);
        }
        $config = EpayService::config();
        if ($config['pid'] !== '' && (string) ($params['pid'] ?? '') !== $config['pid']) {
            $this->reply('fail', '商户号不匹配', $params);
        }

        $order = RechargeOrderModel::findByOrderNo((string) ($params['out_trade_no'] ?? ''));
        if ($order === null) {
            $this->reply('fail', '充值单不存在：' . (string) ($params['out_trade_no'] ?? ''), $params);
        }
        // 金额必须与订单一致，避免改小金额骗过入账
        if (abs((float) ($params['money'] ?? 0) - (float) $order['amount']) > 0.001) {
            $this->reply('fail', '回调金额与订单不一致', $params);
        }

        try {
            $flipped = RechargeService::settle(
                (int) $order['id'],
                (string) ($params['trade_no'] ?? ''),
                (string) ($params['api_trade_no'] ?? ''),
                (string) ($params['buyer'] ?? ''),
                json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
            );
        } catch (Throwable $e) {
            $this->reply('fail', '入账异常：' . $e->getMessage(), $params);
        }
        Logger::write('pay', [
            'event'  => 'epay_notify_ok',
            'order'  => $order['order_no'],
            'amount' => $order['amount'],
            'flipped' => $flipped,
        ]);
        // 重复通知（订单已是已支付）同样返回 success，否则网关会一直重推
        echo self::ACK;
        exit;
    }

    /**
     * 页面跳转通知：用户付款后浏览器回跳，只读展示本地订单状态并引导回充值记录
     * 实际入账由异步通知（POST /pay/notify/epay）完成，此处不写库，避免 GET 触发资金写入（规范 §5.5.3）
     * 若异步通知尚未落库，页面提供「刷新支付状态」按钮，走 POST 主动向网关查询
     */
    public function epayReturn(): never
    {
        $params = EpayService::notifyParams();
        $orderNo = (string) ($params['out_trade_no'] ?? '');
        $order = $orderNo === '' ? null : RechargeOrderModel::findByOrderNo($orderNo);

        $paid = false;
        $orderId = 0;
        $statusText = '';
        $message = '未收到支付单号，无法确认结果。';

        if ($order !== null) {
            $orderId = (int) $order['id'];
            $status = (int) $order['status'];
            $paid = $status === 1;
            $statusText = Status::recharge($status);
            $message = $paid
                ? '支付成功，余额已入账。'
                : '当前状态：' . $statusText . '。若已扣款请点击下方「刷新支付状态」，或稍后在充值记录中查看。';
        } elseif ($orderNo !== '') {
            $message = '未找到对应的充值单，请联系客服核实。';
        }

        View::render('pay/result', [
            'title'      => '支付结果',
            'paid'       => $paid,
            'orderNo'    => $orderNo,
            'orderId'    => $orderId,
            'statusText' => $statusText,
            'message'    => $message,
            'csrf'       => $this->csrfToken(),
        ], 'layouts/base');
        exit;
    }

    /**
     * 通知应答：日志留痕后返回 fail，网关会按策略重推
     */
    private function reply(string $answer, string $reason, array $params): never
    {
        Logger::write('pay', [
            'event'    => 'epay_notify_reject',
            'reason'   => $reason,
            'params'   => $params,
            'clientip' => Request::ip(),
        ], 'warning');
        echo $answer;
        exit;
    }
}
