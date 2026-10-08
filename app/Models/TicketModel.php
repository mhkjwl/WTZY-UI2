<?php declare(strict_types=1);
/**
 * @file app/Models/TicketModel.php
 * @description 客服工单与回复数据访问
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class TicketModel
{
    /**
     * 工单检索（可按状态/类型过滤）
     * @return array{rows: array, total: int}
     */
    public static function search(int $status, int $category, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];
        if ($status >= 0) {
            $where[] = 't.status = :status';
            $params[':status'] = $status;
        }
        if ($category > 0) {
            $where[] = 't.category = :cat';
            $params[':cat'] = $category;
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('tickets') . " t WHERE {$whereSql}");
        $countStmt->execute($params);
        $listStmt = $pdo->prepare(
            'SELECT t.id, t.ticket_no, t.user_id, u.user_no, u.nickname, t.category, t.title, t.status,
                    t.waybill_id, t.package_id, t.assign_admin_id, t.last_reply_at, t.created_at,
                    a.real_name AS assign_name
             FROM ' . Database::table('tickets') . ' t
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = t.user_id
             LEFT JOIN ' . Database::table('admins') . " a ON a.id = t.assign_admin_id
             WHERE {$whereSql} ORDER BY t.last_reply_at DESC, t.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $listStmt->execute($params);
        return ['rows' => $listStmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $countStmt->fetchColumn()];
    }

    /**
     * 各状态工单数
     */
    public static function statusCounts(): array
    {
        $rows = Database::pdo()->query(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('tickets') . ' GROUP BY status'
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'c', 'status');
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT id, ticket_no, status, assign_admin_id FROM ' . Database::table('tickets') . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 后台工单详情（含提交用户、受理客服、关联运单/包裹）
     */
    public static function findDetail(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT t.id, t.ticket_no, t.user_id, t.category, t.title, t.status,
                    t.waybill_id, t.package_id, t.assign_admin_id, t.last_reply_at, t.closed_at, t.created_at,
                    u.user_no, u.username, u.nickname, u.phone, u.email,
                    a.real_name AS assign_name, a.username AS assign_account,
                    w.waybill_no, p.package_no
             FROM ' . Database::table('tickets') . ' t
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = t.user_id
             LEFT JOIN ' . Database::table('admins') . ' a ON a.id = t.assign_admin_id
             LEFT JOIN ' . Database::table('waybills') . ' w ON w.id = t.waybill_id
             LEFT JOIN ' . Database::table('packages') . ' p ON p.id = t.package_id
             WHERE t.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 最后一条回复是否来自用户（用于列表上标记「待客服回复」）
     * @param array<int,int> $ticketIds
     * @return array<int,bool>
     */
    public static function pendingUserReplyMap(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }
        $ids = implode(',', array_map('intval', $ticketIds));
        $rows = Database::pdo()->query(
            'SELECT r.ticket_id, r.reply_side FROM ' . Database::table('ticket_replies') . " r
             JOIN (SELECT ticket_id, MAX(id) AS max_id FROM " . Database::table('ticket_replies') . "
                   WHERE ticket_id IN ({$ids}) GROUP BY ticket_id) m ON m.max_id = r.id"
        )->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['ticket_id']] = (int) $row['reply_side'] === 1;
        }
        return $map;
    }

    /**
     * 工单对话记录（含客服姓名）；关联 admins 后必须逐列加表别名，否则 id 字段歧义
     */
    public static function replies(int $ticketId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT r.id, r.reply_side, r.admin_id, a.real_name AS admin_name, r.content, r.images, r.created_at
             FROM ' . Database::table('ticket_replies') . ' r
             LEFT JOIN ' . Database::table('admins') . ' a ON a.id = r.admin_id
             WHERE r.ticket_id = :id ORDER BY r.id ASC'
        );
        $stmt->execute([':id' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 客服受理：置为处理中并锁定受理人
     */
    public static function assign(int $id, int $adminId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('tickets') . ' SET status = 1, assign_admin_id = :aid WHERE id = :id AND status IN (0, 2)'
        );
        $stmt->execute([':aid' => $adminId, ':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('工单状态已变化，请刷新后重试');
        }
    }

    /**
     * 客服回复：写回复 + 更新工单状态与最后回复时间
     */
    public static function reply(int $id, int $adminId, string $content, array $images = []): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO ' . Database::table('ticket_replies') . ' (ticket_id, reply_side, admin_id, content, images)
                 VALUES (:tid, 2, :aid, :content, :images)'
            );
            $ins->execute([
                ':tid' => $id, ':aid' => $adminId, ':content' => $content,
                ':images' => $images !== [] ? json_encode($images, JSON_UNESCAPED_UNICODE) : null,
            ]);
            $upd = $pdo->prepare(
                'UPDATE ' . Database::table('tickets') . ' SET status = 2, assign_admin_id = :aid, last_reply_at = NOW() WHERE id = :id AND status IN (0, 1, 2)'
            );
            $upd->execute([':aid' => $adminId, ':id' => $id]);
            if ($upd->rowCount() !== 1) {
                throw new RuntimeException('工单已关闭或不存在，无法回复');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function close(int $id, int $adminId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('tickets') . ' SET status = 3, close_admin_id = :aid, closed_at = NOW() WHERE id = :id AND status IN (0, 1, 2)'
        );
        $stmt->execute([':aid' => $adminId, ':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('工单已关闭或不存在');
        }
    }

    /* ================= 用户端 ================= */

    /**
     * 我的工单检索
     * @return array{rows: array, total: int}
     */
    public static function userSearch(int $userId, int $status, int $limit, int $offset): array
    {
        $where = ['t.user_id = :uid'];
        $params = [':uid' => $userId];
        if ($status >= 0) {
            $where[] = 't.status = :status';
            $params[':status'] = $status;
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $count = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('tickets') . " t WHERE {$whereSql}");
        $count->execute($params);
        $list = $pdo->prepare(
            'SELECT t.id, t.ticket_no, t.category, t.title, t.status, t.last_reply_at, t.created_at,
                    w.waybill_no, a.real_name AS admin_name
             FROM ' . Database::table('tickets') . ' t
             LEFT JOIN ' . Database::table('waybills') . ' w ON w.id = t.waybill_id
             LEFT JOIN ' . Database::table('admins') . " a ON a.id = t.assign_admin_id
             WHERE {$whereSql} ORDER BY t.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $list->execute($params);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    public static function userStatusCounts(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('tickets') . ' WHERE user_id = :uid GROUP BY status'
        );
        $stmt->execute([':uid' => $userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'c', 'status');
    }

    public static function findForUser(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT t.*, w.waybill_no, a.real_name AS admin_name
             FROM ' . Database::table('tickets') . ' t
             LEFT JOIN ' . Database::table('waybills') . ' w ON w.id = t.waybill_id
             LEFT JOIN ' . Database::table('admins') . ' a ON a.id = t.assign_admin_id
             WHERE t.id = :id AND t.user_id = :uid LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 用户提交工单
     */
    public static function createForUser(int $userId, array $data): int
    {
        $pdo = Database::pdo();
        $ticketNo = '';
        for ($i = 0; $i < 5; $i++) {
            $candidate = 'TK' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $check = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('tickets') . ' WHERE ticket_no = :no');
            $check->execute([':no' => $candidate]);
            if ((int) $check->fetchColumn() === 0) {
                $ticketNo = $candidate;
                break;
            }
        }
        if ($ticketNo === '') {
            throw new RuntimeException('工单号生成失败，请稍后重试');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO ' . Database::table('tickets') . '
             (ticket_no, user_id, category, waybill_id, package_id, title, status)
             VALUES (:no, :uid, :cat, :wid, :pid, :title, 0)'
        );
        $stmt->execute([
            ':no' => $ticketNo, ':uid' => $userId, ':cat' => $data['category'],
            ':wid' => $data['waybill_id'] > 0 ? $data['waybill_id'] : null,
            ':pid' => $data['package_id'] > 0 ? $data['package_id'] : null,
            ':title' => $data['title'],
        ]);
        $ticketId = (int) $pdo->lastInsertId();
        // 首条内容作为用户提问写入对话记录
        $pdo->prepare(
            'INSERT INTO ' . Database::table('ticket_replies') . ' (ticket_id, reply_side, content) VALUES (:tid, 1, :content)'
        )->execute([':tid' => $ticketId, ':content' => $data['content']]);
        return $ticketId;
    }

    /**
     * 用户追加回复：已关闭工单不允许再回复
     */
    public static function userReply(int $ticketId, int $userId, string $content): void
    {
        $ticket = self::findForUser($ticketId, $userId);
        if ($ticket === null) {
            throw new RuntimeException('工单不存在');
        }
        if ((int) $ticket['status'] === 3) {
            throw new RuntimeException('工单已关闭，如需继续请新建工单');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO ' . Database::table('ticket_replies') . ' (ticket_id, reply_side, content) VALUES (:tid, 1, :content)'
            )->execute([':tid' => $ticketId, ':content' => $content]);
            // 用户回复后回到「处理中」，便于客服再次跟进
            $pdo->prepare('UPDATE ' . Database::table('tickets') . ' SET status = 1, last_reply_at = NOW() WHERE id = :id')
                ->execute([':id' => $ticketId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 用户关闭自己的工单
     */
    public static function userClose(int $ticketId, int $userId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('tickets') . ' SET status = 3, closed_at = NOW()
             WHERE id = :id AND user_id = :uid AND status IN (0, 1, 2)'
        );
        $stmt->execute([':id' => $ticketId, ':uid' => $userId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('工单已关闭或不存在');
        }
    }
}
