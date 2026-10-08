<?php declare(strict_types=1);
/**
 * @file app/Services/RechargeService.php
 * @description 充值单结算：网关收款入账、状态同步、退款（余额与流水同源，保证账实一致）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Services;

use App\Core\Logger;
use App\Models\FundFlowModel;
use App\Models\RechargeOrderModel;
use RuntimeException;
use Throwable;

final class RechargeService
{
    /**
     * 网关确认收款后入账：先把订单翻转为已支付，再记充值流水
     * 翻转具备幂等性（仅待支付/待入账可翻），因此重复通知不会重复加钱；
     * 流水失败时把订单退回待入账，让网关按重试策略再次通知，避免「单已付、钱未到」
     */
    public static function settle(int $orderId, string $gatewayTradeNo, string $apiTradeNo, string $buyer, string $notifyRaw): bool
    {
        $order = RechargeOrderModel::findById($orderId);
        if ($order === null) {
            throw new RuntimeException('充值单不存在');
        }
        // 超时已关单却仍收到成功通知：钱已实际到账，照常入账并留痕，避免用户付款后不到账
        if ((int) $order['status'] === 4) {
            Logger::write('pay', [
                'event'  => 'epay_paid_after_expired',
                'order'  => $order['order_no'],
                'amount' => $order['amount'],
            ], 'warning');
        }
        if (!RechargeOrderModel::markPaidByGateway($orderId, $gatewayTradeNo, $apiTradeNo, $buyer, $notifyRaw)) {
            return false;
        }
        try {
            FundFlowModel::record(
                (int) $order['user_id'],
                1,
                1,
                (float) $order['amount'],
                1,
                (string) $order['order_no'],
                null,
                '在线支付充值（易支付）'
            );
        } catch (Throwable $e) {
            RechargeOrderModel::revertToPending($orderId);
            throw new RuntimeException('入账失败：' . $e->getMessage());
        }
        return true;
    }

    /**
     * 主动向网关查询并同步状态（回跳确认、用户端轮询、后台手动同步共用）
     * @return array{paid: bool, gateway_status: int, flipped: bool}
     */
    public static function syncFromGateway(int $orderId): array
    {
        $order = RechargeOrderModel::findById($orderId);
        if ($order === null) {
            throw new RuntimeException('充值单不存在');
        }
        if ((string) ($order['gateway'] ?? '') !== 'epay' || (string) ($order['gateway_trade_no'] ?? '') === '') {
            throw new RuntimeException('该充值单未通过在线网关下单，无法同步');
        }
        $result = EpayService::query((string) $order['order_no']);
        if ($result['status'] !== 1 && $result['status'] !== 2) {
            return ['paid' => false, 'gateway_status' => $result['status'], 'flipped' => false];
        }
        $flipped = self::settle(
            $orderId,
            (string) ($order['gateway_trade_no'] ?? ''),
            $result['api_trade_no'],
            '',
            '后台查询同步：' . json_encode($result['raw'], JSON_UNESCAPED_UNICODE)
        );
        if ((float) $result['refundmoney'] > 0) {
            RechargeOrderModel::applyRefund($orderId, (float) $result['refundmoney'], '');
        }
        return ['paid' => true, 'gateway_status' => $result['status'], 'flipped' => $flipped];
    }

    /**
     * 发起退款：先向网关退款，成功后再扣减用户余额并记退款流水
     * 余额不足时拒绝退款，避免账户出现负余额
     * @return array{refund_no: string, money: float, status: int}
     */
    public static function refund(int $orderId, float $money, string $remark, int $operatorId): array
    {
        $order = RechargeOrderModel::findById($orderId);
        if ($order === null) {
            throw new RuntimeException('充值单不存在');
        }
        if ((int) $order['status'] !== 1) {
            throw new RuntimeException('仅已支付的充值单可以退款');
        }
        if ((string) ($order['gateway'] ?? '') !== 'epay') {
            throw new RuntimeException('该充值单不是在线网关订单，请走人工调整');
        }
        $refunded = (float) $order['refund_money'];
        $refundable = round((float) $order['amount'] - $refunded, 2);
        if ($money <= 0 || $money > $refundable) {
            throw new RuntimeException('退款金额需大于 0 且不超过可退金额 ¥' . number_format($refundable, 2));
        }

        $outRefundNo = (string) $order['order_no'] . 'R' . (int) round(microtime(true));
        $result = EpayService::refund((string) $order['order_no'], $money, $outRefundNo);

        // 余额先行校验：已在平台消费掉的部分无法退回余额，需人工介入
        try {
            FundFlowModel::record(
                (int) $order['user_id'],
                3,
                2,
                $money,
                1,
                (string) $order['order_no'],
                $operatorId,
                $remark !== '' ? $remark : '在线充值退款'
            );
        } catch (Throwable $e) {
            throw new RuntimeException('网关已退款，但用户余额扣减失败：' . $e->getMessage() . '（请人工核对账户）');
        }
        RechargeOrderModel::applyRefund($orderId, $money, (string) $result['refund_no']);
        $fresh = RechargeOrderModel::findById($orderId);
        return [
            'refund_no' => (string) $result['refund_no'],
            'money'     => $money,
            'status'    => (int) ($fresh['status'] ?? 1),
        ];
    }
}
