<?php declare(strict_types=1);
/**
 * @file app/Models/CouponModel.php
 * @description 优惠券：可领列表、领取、我的券、核销
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

final class CouponModel
{
    /**
     * 可领取的券（启用中、未过期、有库存、未达个人上限）
     */
    public static function receivable(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT c.id, c.code, c.name, c.type, c.value, c.min_amount, c.start_at, c.end_at,
                    IFNULL(mine.cnt, 0) AS mine_count, c.per_user_limit
             FROM ' . Database::table('coupons') . ' c
             LEFT JOIN (
                 SELECT coupon_id, COUNT(*) AS cnt FROM ' . Database::table('user_coupons') . '
                 WHERE user_id = :uid GROUP BY coupon_id
             ) mine ON mine.coupon_id = c.id
             WHERE c.status = 1
               AND (c.start_at IS NULL OR c.start_at <= NOW())
               AND (c.end_at IS NULL OR c.end_at >= NOW())
               AND (c.total_qty = 0 OR c.used_qty < c.total_qty)
             ORDER BY c.id DESC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 我的券（可按状态过滤：0未使用 1已使用 2已过期）
     */
    public static function mine(int $userId, int $status = -1): array
    {
        $where = 'uc.user_id = :uid';
        $params = [':uid' => $userId];
        if ($status >= 0) {
            $where .= ' AND uc.status = :status';
            $params[':status'] = $status;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT uc.id, uc.status, uc.received_at, uc.used_at, uc.waybill_id,
                    c.code, c.name, c.type, c.value, c.min_amount, c.start_at, c.end_at
             FROM ' . Database::table('user_coupons') . ' uc
             JOIN ' . Database::table('coupons') . ' c ON c.id = uc.coupon_id
             WHERE ' . $where . ' ORDER BY uc.id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function counts(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('user_coupons') . '
             WHERE user_id = :uid GROUP BY status'
        );
        $stmt->execute([':uid' => $userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'c', 'status');
    }

    /**
     * 领券：事务内校验库存与个人上限后发券
     */
    public static function receive(int $userId, int $couponId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT id, total_qty, used_qty, per_user_limit, start_at, end_at, status
                 FROM ' . Database::table('coupons') . ' WHERE id = :id FOR UPDATE'
            );
            $stmt->execute([':id' => $couponId]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($coupon === false || (int) $coupon['status'] !== 1) {
                throw new InvalidArgumentException('优惠券不存在或已停发');
            }
            if ($coupon['end_at'] !== null && strtotime((string) $coupon['end_at']) < time()) {
                throw new InvalidArgumentException('优惠券已过期');
            }
            if ((int) $coupon['total_qty'] > 0 && (int) $coupon['used_qty'] >= (int) $coupon['total_qty']) {
                throw new InvalidArgumentException('优惠券已领完');
            }
            $mine = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('user_coupons') . ' WHERE user_id = :uid AND coupon_id = :cid');
            $mine->execute([':uid' => $userId, ':cid' => $couponId]);
            if ((int) $mine->fetchColumn() >= (int) $coupon['per_user_limit']) {
                throw new InvalidArgumentException('您已领取过该券');
            }
            $pdo->prepare('INSERT INTO ' . Database::table('user_coupons') . ' (user_id, coupon_id) VALUES (:uid, :cid)')
                ->execute([':uid' => $userId, ':cid' => $couponId]);
            $pdo->prepare('UPDATE ' . Database::table('coupons') . ' SET used_qty = used_qty + 1 WHERE id = :id')
                ->execute([':id' => $couponId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 结算时挑出该用户在当前订单金额下可用的券
     */
    public static function usable(int $userId, float $amount): array
    {
        return array_values(array_filter(self::mine($userId, 0), static function (array $c) use ($amount): bool {
            if ((float) $c['min_amount'] > $amount) {
                return false;
            }
            return $c['end_at'] === null || strtotime((string) $c['end_at']) >= time();
        }));
    }

    /**
     * 计算券的抵扣金额
     */
    public static function discountOf(array $coupon, float $amount): float
    {
        $value = (float) $coupon['value'];
        return match ((int) $coupon['type']) {
            1, 3 => min($value, $amount),
            2    => round($amount * (1 - $value), 2),
            default => 0.0,
        };
    }
}
