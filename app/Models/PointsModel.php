<?php declare(strict_types=1);
/**
 * @file app/Models/PointsModel.php
 * @description 用户积分账本：余额变动只经流水，保证可追溯；签收积分先入待激活账本，缓冲期满再入账
 * @author AI
 * @version 0.6.2
 * @date 2026-09-16
 */

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

final class PointsModel
{
    /** 业务类型：1注册 2下单 3邀请 4抵扣 5人工调整 6充值 7消费 8签收到账 9积分取消 10积分扣回 */
    public const TYPE_REGISTER = 1;
    public const TYPE_ORDER = 2;
    public const TYPE_INVITE = 3;
    public const TYPE_DEDUCT = 4;
    public const TYPE_MANUAL = 5;
    public const TYPE_RECHARGE = 6;
    public const TYPE_CONSUME = 7;
    public const TYPE_SIGN = 8;
    public const TYPE_CANCEL = 9;
    public const TYPE_CLAWBACK = 10;

    /** 待激活状态 */
    public const PENDING_WAITING = 0;
    public const PENDING_ACTIVATED = 1;
    public const PENDING_CANCELED = 2;

    /**
     * 积分变动：锁定账户行 → 校验余额 → 写流水 → 回写余额（同一事务）
     * $allowNegative 用于退款扣回场景：允许把余额扣成负数，其余场景一律拒绝透支
     */
    public static function change(
        int $userId,
        int $change,
        int $bizType,
        string $bizNo = '',
        string $remark = '',
        bool $allowNegative = false
    ): void {
        $pdo = Database::pdo();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $lock = $pdo->prepare('SELECT points, member_level FROM ' . Database::table('users') . ' WHERE id = :id FOR UPDATE');
            $lock->execute([':id' => $userId]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new InvalidArgumentException('用户不存在');
            }
            $current = (int) $row['points'];
            $after = $current + $change;
            if ($after < 0 && !$allowNegative) {
                throw new InvalidArgumentException('积分不足');
            }
            $pdo->prepare('UPDATE ' . Database::table('users') . ' SET points = :p WHERE id = :id')
                ->execute([':p' => $after, ':id' => $userId]);
            // 积分达到等级门槛即自动升级（只升不降）：等级是积分的衍生值，与后台门槛重算口径一致
            $targetLevel = MemberLevelModel::levelForPoints($after);
            if ($targetLevel > (int) $row['member_level']) {
                $pdo->prepare('UPDATE ' . Database::table('users') . ' SET member_level = :l WHERE id = :id')
                    ->execute([':l' => $targetLevel, ':id' => $userId]);
            }
            $pdo->prepare(
                'INSERT INTO ' . Database::table('points_logs') . '
                 (user_id, change_amount, balance_after, biz_type, biz_no, remark)
                 VALUES (:uid, :chg, :bal, :type, :no, :remark)'
            )->execute([
                ':uid' => $userId, ':chg' => $change, ':bal' => $after,
                ':type' => $bizType, ':no' => $bizNo, ':remark' => $remark,
            ]);
            if ($ownTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * 登记一笔待激活积分：同一业务单号只允许一笔（唯一键保证幂等，重复登记返回 false）
     */
    public static function pending(
        int $userId,
        int $points,
        int $bizType,
        string $bizNo,
        float $baseAmount,
        string $activateAt,
        string $remark = ''
    ): bool {
        if ($points <= 0) {
            return false;
        }
        $stmt = Database::pdo()->prepare(
            'INSERT IGNORE INTO ' . Database::table('points_pending') . '
             (user_id, points, base_amount, biz_type, biz_no, status, activate_at, signed_at, remark)
             VALUES (:uid, :p, :amt, :type, :no, 0, :act, NOW(), :remark)'
        );
        $stmt->execute([
            ':uid' => $userId, ':p' => $points, ':amt' => $baseAmount,
            ':type' => $bizType, ':no' => $bizNo, ':act' => $activateAt, ':remark' => $remark,
        ]);
        return $stmt->rowCount() === 1;
    }

    /**
     * 激活到期的待激活积分（逐条独立事务：抢占状态与入账同事务，失败自动回退保持待激活）
     * @return int 本次激活的笔数
     */
    public static function activateDue(int $limit = 200): int
    {
        $pdo = Database::pdo();
        $rows = $pdo->prepare(
            'SELECT id, user_id, points, biz_no, remark FROM ' . Database::table('points_pending') . '
             WHERE status = 0 AND activate_at <= NOW() ORDER BY id ASC LIMIT ' . (int) $limit
        );
        $rows->execute();
        $activated = 0;
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pdo->beginTransaction();
            try {
                $lock = $pdo->prepare(
                    'SELECT id FROM ' . Database::table('points_pending') . '
                     WHERE id = :id AND status = 0 FOR UPDATE'
                );
                $lock->execute([':id' => (int) $row['id']]);
                if ((int) $lock->fetchColumn() !== (int) $row['id']) {
                    $pdo->rollBack();
                    continue;
                }
                $pdo->prepare(
                    'UPDATE ' . Database::table('points_pending') . '
                     SET status = 1, activated_at = NOW() WHERE id = :id'
                )->execute([':id' => (int) $row['id']]);
                self::change(
                    (int) $row['user_id'],
                    (int) $row['points'],
                    self::TYPE_SIGN,
                    (string) $row['biz_no'],
                    (string) ($row['remark'] ?? '') !== '' ? (string) $row['remark'] : '签收赠送积分'
                );
                $pdo->commit();
                $activated++;
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        return $activated;
    }

    /**
     * 取消指定单号的待激活积分（未到账直接作废；已到账的不受影响，由扣回处理）
     * @return int 取消的笔数
     */
    public static function cancelPending(string $bizNo, string $remark = '退款/退件，取消未到账积分'): int
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('points_pending') . '
             SET status = 2, remark = :remark WHERE biz_no = :no AND status = 0'
        );
        $stmt->execute([':no' => $bizNo, ':remark' => $remark]);
        return $stmt->rowCount();
    }

    /**
     * 按单号扣回已到账的签收积分（可扣成负分）
     * @return int 实际扣回的积分数（未到账或无记录返回 0）
     */
    public static function clawback(string $bizNo, string $remark = '退款扣回积分'): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT user_id, points FROM ' . Database::table('points_pending') . '
             WHERE biz_no = :no AND status = 1 ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([':no' => $bizNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return 0;
        }
        self::change((int) $row['user_id'], -(int) $row['points'], self::TYPE_CLAWBACK, $bizNo, $remark, true);
        return (int) $row['points'];
    }

    /**
     * 待激活积分合计（会员积分页展示用）
     */
    public static function pendingSum(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(points), 0) FROM ' . Database::table('points_pending') . '
             WHERE user_id = :uid AND status = 0'
        );
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 负积分下单闸门：返回阻断文案，未受限返回 null
     */
    public static function negativeGate(int $userId): ?string
    {
        $settings = SettingsModel::get('points_negative_block', '1');
        if ((string) $settings !== '1') {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT points FROM ' . Database::table('users') . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $points = $stmt->fetchColumn();
        if ($points === false || (int) $points >= 0) {
            return null;
        }
        return '当前积分待还清（' . (int) $points . '），请先获得足够积分后再提交转运';
    }

    /**
     * @return array{rows: array, total: int}
     */
    public static function logs(int $userId, int $limit, int $offset): array
    {
        $pdo = Database::pdo();
        $count = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('points_logs') . ' WHERE user_id = :uid');
        $count->execute([':uid' => $userId]);
        $list = $pdo->prepare(
            'SELECT id, change_amount, balance_after, biz_type, biz_no, remark, created_at
             FROM ' . Database::table('points_logs') . '
             WHERE user_id = :uid ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $list->execute([':uid' => $userId]);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }
}