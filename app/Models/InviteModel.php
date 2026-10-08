<?php declare(strict_types=1);
/**
 * @file app/Models/InviteModel.php
 * @description 邀请返利：我的邀请码、邀请记录与返利明细
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class InviteModel
{
    /**
     * 邀请码：直接复用用户编号，避免额外生成与存储
     */
    public static function inviteCode(string $userNo): string
    {
        return $userNo;
    }

    /**
     * 我邀请的用户（含注册时间与返利状态）
     */
    public static function invitedUsers(int $userId, int $limit, int $offset): array
    {
        $pdo = Database::pdo();
        $count = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('users') . ' WHERE inviter_id = :uid AND deleted_at IS NULL');
        $count->execute([':uid' => $userId]);
        $list = $pdo->prepare(
            'SELECT u.id, u.user_no, u.username, u.created_at
             FROM ' . Database::table('users') . ' u
             WHERE u.inviter_id = :uid AND u.deleted_at IS NULL
             ORDER BY u.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $list->execute([':uid' => $userId]);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    /**
     * 我的返利明细
     */
    public static function rewards(int $userId, int $limit, int $offset): array
    {
        $pdo = Database::pdo();
        $count = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('invite_rewards') . ' WHERE inviter_id = :uid');
        $count->execute([':uid' => $userId]);
        $list = $pdo->prepare(
            'SELECT r.id, r.reward_type, r.amount, r.points, r.biz_no, r.status, r.created_at,
                    u.user_no AS invitee_no, u.username AS invitee_name
             FROM ' . Database::table('invite_rewards') . ' r
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = r.user_id
             WHERE r.inviter_id = :uid ORDER BY r.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $list->execute([':uid' => $userId]);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    /**
     * 累计返利汇总（按发放状态）
     */
    public static function summary(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT
                IFNULL(SUM(CASE WHEN status = 1 AND reward_type = 2 THEN amount ELSE 0 END), 0) AS paid_amount,
                IFNULL(SUM(CASE WHEN status = 1 AND reward_type = 1 THEN points ELSE 0 END), 0) AS paid_points,
                IFNULL(SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END), 0) AS pending_count
             FROM ' . Database::table('invite_rewards') . ' WHERE inviter_id = :uid'
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'paid_amount'   => (float) ($row['paid_amount'] ?? 0),
            'paid_points'   => (int) ($row['paid_points'] ?? 0),
            'pending_count' => (int) ($row['pending_count'] ?? 0),
        ];
    }

    /**
     * 注册时绑定邀请人：按用户编号反查，忽略无效或自邀
     */
    public static function bindInviter(int $newUserId, string $inviteCode): void
    {
        $inviteCode = trim($inviteCode);
        if ($inviteCode === '') {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM ' . Database::table('users') . ' WHERE user_no = :no AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':no' => $inviteCode]);
        $inviterId = (int) $stmt->fetchColumn();
        if ($inviterId <= 0 || $inviterId === $newUserId) {
            return;
        }
        Database::pdo()->prepare('UPDATE ' . Database::table('users') . ' SET inviter_id = :inv WHERE id = :id')
            ->execute([':inv' => $inviterId, ':id' => $newUserId]);
        // 登记待发放返利（实际发放由后台或定时任务执行）
        Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('invite_rewards') . ' (user_id, inviter_id, reward_type, points, status)
             VALUES (:uid, :inv, 1, 200, 0)'
        )->execute([':uid' => $newUserId, ':inv' => $inviterId]);
    }
}
