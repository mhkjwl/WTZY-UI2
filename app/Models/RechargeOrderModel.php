<?php declare(strict_types=1);
/**
 * @file app/Models/RechargeOrderModel.php
 * @description 充值订单数据访问与人工确认入账（财务中心）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class RechargeOrderModel
{
    /**
     * 需人工处理的充值单状态：待支付(0) 与 待入账(5)
     * 待支付也计入——银行转账等线下渠道的用户可能已实际付款却未点「我已完成支付」，
     * 若不提醒，这笔款会一直躺在待支付里无人核实；后台徽标与财务中心页内提示共用此口径
     */
    public const PENDING_STATUSES = [0, 5];

    /**
     * 人工入账渠道的支付方式：银行转账(5) 与 后台人工入账(6)
     * 这类渠道不经过支付网关，到账由管理员核实，付款周期远长于在线支付；
     * 且用户可能已实际转账却忘了点「我已完成支付」，若按在线口径超时关单，
     * 会出现「钱已到账、单却已关闭」而无法入账的死结，故一律不纳入超时自动关单
     */
    public const MANUAL_METHODS = [5, 6];

    /**
     * 是否为人工入账渠道（决定是否受支付时效约束）
     */
    public static function isManualMethod(int $payMethod): bool
    {
        return in_array($payMethod, self::MANUAL_METHODS, true);
    }

    /**
     * 从状态分布汇总待处理量（徽标与页内提示的唯一口径）
     */
    public static function pendingCount(array $counts): int
    {
        $total = 0;
        foreach (self::PENDING_STATUSES as $status) {
            $total += (int) ($counts[$status] ?? 0);
        }
        return $total;
    }

    /**
     * 充值单检索（可按状态过滤）
     * @return array{rows: array, total: int}
     */
    public static function search(int $status, int $limit, int $offset): array
    {
        $where = '1=1';
        $params = [];
        if ($status >= 0) {
            $where = 'r.status = :status';
            $params[':status'] = $status;
        }
        $pdo = Database::pdo();
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('recharge_orders') . " r WHERE {$where}");
        $countStmt->execute($params);
        $listStmt = $pdo->prepare(
            'SELECT r.id, r.order_no, r.user_id, u.user_no, u.nickname, r.amount, r.pay_method, r.status,
                    r.trade_no, r.gateway, r.channel_code, r.gateway_trade_no, r.api_trade_no, r.pay_type,
                    r.refund_money, r.paid_at, r.created_at
             FROM ' . Database::table('recharge_orders') . ' r
             LEFT JOIN ' . Database::table('users') . " u ON u.id = r.user_id
             WHERE {$where} ORDER BY r.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $listStmt->execute($params);
        return ['rows' => $listStmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $countStmt->fetchColumn()];
    }

    /**
     * 各状态充值单数（状态机编码为键），用于后台列表提示待办量
     */
    public static function statusCounts(): array
    {
        $rows = Database::pdo()->query(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('recharge_orders') . ' GROUP BY status'
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'c', 'status');
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, order_no, user_id, amount, pay_method, status, channel_code, gateway, gateway_trade_no,
                    api_trade_no, pay_type, refund_money
             FROM ' . Database::table('recharge_orders') . ' WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 按商户订单号查找（网关异步通知 / 回跳按单号定位）
     */
    public static function findByOrderNo(string $orderNo): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, order_no, user_id, amount, pay_method, status, channel_code, gateway, gateway_trade_no,
                    api_trade_no, pay_type, refund_money
             FROM ' . Database::table('recharge_orders') . ' WHERE order_no = :no LIMIT 1'
        );
        $stmt->execute([':no' => $orderNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /* ================= 用户端 ================= */

    /**
     * 我的充值记录
     * @param int $expireMinutes 大于 0 时附带 remain_seconds（倒计时用）
     * @return array{rows: array, total: int}
     */
    public static function userSearch(int $userId, int $limit, int $offset, int $expireMinutes = 0): array
    {
        $pdo = Database::pdo();
        $count = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('recharge_orders') . ' WHERE user_id = :uid');
        $count->execute([':uid' => $userId]);
        $extra = $expireMinutes > 0 ? ', ' . self::remainExpr($expireMinutes) : '';
        $list = $pdo->prepare(
            'SELECT id, order_no, amount, pay_method, status, trade_no, paid_at, remark, created_at' . $extra . '
             FROM ' . Database::table('recharge_orders') . '
             WHERE user_id = :uid ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $list->execute([':uid' => $userId]);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    public static function findForUser(int $id, int $userId, int $expireMinutes = 0): ?array
    {
        $extra = $expireMinutes > 0 ? ', ' . self::remainExpr($expireMinutes) : '';
        $stmt = Database::pdo()->prepare(
            'SELECT id, order_no, amount, pay_method, status, channel_code, gateway, gateway_trade_no, pay_type, created_at'
            . $extra . '
             FROM ' . Database::table('recharge_orders') . ' WHERE id = :id AND user_id = :uid LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 用户发起充值：生成待支付单，由后台核实到账或网关回调后确认入账
     */
    public static function createForUser(int $userId, float $amount, int $payMethod, string $remark): int
    {
        $pdo = Database::pdo();
        $orderNo = '';
        for ($i = 0; $i < 5; $i++) {
            $candidate = 'RC' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $check = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('recharge_orders') . ' WHERE order_no = :no');
            $check->execute([':no' => $candidate]);
            if ((int) $check->fetchColumn() === 0) {
                $orderNo = $candidate;
                break;
            }
        }
        if ($orderNo === '') {
            throw new RuntimeException('充值单号生成失败，请稍后重试');
        }
        $channel = \App\Services\EpayService::CODE_TO_TYPE[$payMethod] ?? null;
        $stmt = $pdo->prepare(
            'INSERT INTO ' . Database::table('recharge_orders') . '
                (order_no, user_id, amount, pay_method, channel_code, gateway, status, remark)
             VALUES (:no, :uid, :amount, :method, :channel, :gateway, 0, :remark)'
        );
        $stmt->execute([
            ':no'      => $orderNo,
            ':uid'     => $userId,
            ':amount'  => $amount,
            ':method'  => $payMethod,
            ':channel' => $channel,
            ':gateway' => $channel === null ? null : 'epay',
            ':remark'  => $remark,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /* ================= 在线网关（易支付） ================= */

    /**
     * 关闭超时未支付的充值单：超过有效时长仍为「待支付」的单转为「已关闭」
     * 无计划任务，故在写操作（发起支付、创建充值单、后台同步）时惰性清理，返回本次关闭数量
     * 人工入账渠道（银行转账）不参与：其到账依赖管理员核实，超时关单只会制造无法入账的死单
     * @param int      $minutes 有效时长（分钟）
     * @param int|null $userId  限定只清理该用户；null 为全表（仅后台维护动作可用）
     */
    public static function closeExpired(int $minutes, ?int $userId = null): int
    {
        $sql = 'UPDATE ' . Database::table('recharge_orders') . '
             SET status = 4
             WHERE status = 0 AND pay_method NOT IN (' . implode(',', array_map('intval', self::MANUAL_METHODS)) . ')
               AND created_at < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)';
        $params = [':minutes' => max(1, $minutes)];
        if ($userId !== null) {
            $sql .= ' AND user_id = :uid';
            $params[':uid'] = $userId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 剩余有效秒数（0 表示已超时）
     * 必须在 SQL 内用 MySQL 的 NOW() 计算：PHP 与 MySQL 时区不一致时，用 PHP 时间戳做减法会整体偏移
     * 人工入账渠道返回 NULL：该类单不受时效约束，前端据此不渲染倒计时
     */
    public static function remainExpr(int $expireMinutes): string
    {
        return 'IF(pay_method IN (' . implode(',', array_map('intval', self::MANUAL_METHODS)) . '), NULL, '
            . 'GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(created_at, INTERVAL '
            . max(1, $expireMinutes) . ' MINUTE)))) AS remain_seconds';
    }

    /**
     * 记录网关下单结果：平台订单号与发起支付类型，供回跳确认与后台对账使用
     */
    public static function attachGateway(int $id, string $tradeNo, string $payType, string $apiTradeNo): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('recharge_orders') . '
             SET gateway = :gw, gateway_trade_no = :trade, pay_type = :type, api_trade_no = :api
             WHERE id = :id'
        );
        $stmt->execute([
            ':gw'    => 'epay',
            ':trade' => $tradeNo !== '' ? $tradeNo : null,
            ':type'  => $payType !== '' ? $payType : null,
            ':api'   => $apiTradeNo !== '' ? $apiTradeNo : null,
            ':id'    => $id,
        ]);
    }

    /**
     * 网关确认收款：订单置为已支付并落网关单号；返回是否本次完成状态翻转（幂等，重复通知不会重复入账）
     * 允许从「已关闭」翻转：超时自动关单后用户仍可能完成付款，此时钱已实际到账，必须入账而非丢弃
     */
    public static function markPaidByGateway(int $id, string $gatewayTradeNo, string $apiTradeNo, string $buyer, string $notifyRaw): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('recharge_orders') . '
             SET status = 1, paid_at = NOW(), trade_no = :trade, gateway_trade_no = :gtrade,
                 api_trade_no = :api, buyer = :buyer, notify_raw = :raw
             WHERE id = :id AND status IN (0, 4, 5)'
        );
        $stmt->execute([
            ':trade'  => $gatewayTradeNo !== '' ? $gatewayTradeNo : null,
            ':gtrade' => $gatewayTradeNo !== '' ? $gatewayTradeNo : null,
            ':api'    => $apiTradeNo !== '' ? $apiTradeNo : null,
            ':buyer'  => $buyer !== '' ? $buyer : null,
            ':raw'    => mb_substr($notifyRaw, 0, 2000),
            ':id'     => $id,
        ]);
        return $stmt->rowCount() === 1;
    }

    /**
     * 入账流水失败时把已翻转的订单退回待入账，等待网关重试通知
     */
    public static function revertToPending(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('recharge_orders') . ' SET status = 5 WHERE id = :id AND status = 1'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * 记录退款成功：累加已退金额，全额退款时订单转为「已退款」
     * status 赋值必须写在 refund_money 之前：MySQL 的 SET 从左到右求值，
     * 写在后面会读到已累加的新值而把本次退款算两遍
     */
    public static function applyRefund(int $id, float $money, string $refundNo): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('recharge_orders') . '
             SET status = IF(refund_money + :money >= amount, 3, status),
                 refund_money = refund_money + :money2, refund_no = :no, refund_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':money' => $money, ':money2' => $money, ':no' => $refundNo !== '' ? $refundNo : null, ':id' => $id]);
    }

    /**
     * 按网关查询结果同步本地状态（后台手动同步 / 用户端轮询用）
     * status: 1已支付 2已退款
     */
    public static function syncPaidStatus(int $id, int $gatewayStatus, string $apiTradeNo, float $refundMoney): bool
    {
        if ($gatewayStatus !== 1 && $gatewayStatus !== 2) {
            return false;
        }
        // 与网关通知一致：已关闭的单若网关确认已付款，同样补记，避免用户付了钱却不到账
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('recharge_orders') . '
             SET status = 1, paid_at = IFNULL(paid_at, NOW()), api_trade_no = IFNULL(:api, api_trade_no)
             WHERE id = :id AND status IN (0, 4, 5)'
        );
        $stmt->execute([':api' => $apiTradeNo !== '' ? $apiTradeNo : null, ':id' => $id]);
        $flipped = $stmt->rowCount() === 1;
        if ($refundMoney > 0) {
            $upd = Database::pdo()->prepare(
                'UPDATE ' . Database::table('recharge_orders') . '
                 SET refund_money = :money, status = IF(:money2 >= amount, 3, status) WHERE id = :id'
            );
            $upd->execute([':money' => $refundMoney, ':money2' => $refundMoney, ':id' => $id]);
        }
        return $flipped;
    }

    /**
     * 用户取消自己的待支付充值单
     */
    public static function cancelByUser(int $id, int $userId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('recharge_orders') . ' SET status = 4
             WHERE id = :id AND user_id = :uid AND status = 0'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('仅待支付的充值单可以取消');
        }
    }

    /**
     * 用户标记「已完成付款」：进入待入账，等待管理员核实到账
     */
    public static function markPaidByUser(int $id, int $userId, string $tradeNo): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('recharge_orders') . '
             SET status = 5, trade_no = :trade
             WHERE id = :id AND user_id = :uid AND status = 0'
        );
        $stmt->execute([':trade' => $tradeNo !== '' ? $tradeNo : null, ':id' => $id, ':uid' => $userId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('仅待支付的充值单可以标记付款');
        }
    }

    /**
     * 人工确认入账：充值单置为已支付 + 记充值流水（流水内部完成余额变更）
     */
    public static function confirmManual(int $id, int $operatorId): void
    {
        $order = self::findById($id);
        if ($order === null) {
            throw new RuntimeException('充值单不存在');
        }
        if ((int) $order['status'] !== 0 && (int) $order['status'] !== 5) {
            throw new RuntimeException('仅待支付或待入账状态的充值单可入账');
        }
        $upd = Database::pdo()->prepare(
            'UPDATE ' . Database::table('recharge_orders') . '
             SET status = 1, paid_at = NOW(), pay_method = 6, confirm_admin_id = :aid
             WHERE id = :id AND status IN (0, 5)'
        );
        $upd->execute([':aid' => $operatorId, ':id' => $id]);
        if ($upd->rowCount() !== 1) {
            throw new RuntimeException('充值单状态已变化，请刷新后重试');
        }
        FundFlowModel::record((int) $order['user_id'], 1, 1, (float) $order['amount'], 1, $order['order_no'], $operatorId, '后台人工确认入账');
    }

    /**
     * 关闭充值单：待支付(0) 与 待入账(5) 均可关闭
     * 待入账的单是用户自行声明「已付款」但管理员核实后发现并未到账（或金额不符），
     * 必须允许关闭，否则这笔账会永久卡在待处理里无法清理
     */
    public static function close(int $id): void
    {
        $order = self::findById($id);
        if ($order === null) {
            throw new RuntimeException('充值单不存在');
        }
        if (!in_array((int) $order['status'], self::PENDING_STATUSES, true)) {
            throw new RuntimeException('仅待支付或待入账状态的充值单可关闭');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('recharge_orders') . ' SET status = 4 WHERE id = :id AND status IN (0, 5)'
        );
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('充值单状态已变化，请刷新后重试');
        }
    }
}
