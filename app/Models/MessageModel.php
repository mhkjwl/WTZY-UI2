<?php declare(strict_types=1);
/**
 * @file app/Models/MessageModel.php
 * @description 站内消息数据访问：后台全站公告的发布维护 + 用户端消息中心读取
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class MessageModel
{
    /** 公告（全员消息）在 user_id 上以 NULL 标识 */
    private const BROADCAST_OWNER = 'user_id IS NULL';

    /**
     * 后台公告列表（按更新时间倒序，刚编辑过的排在最前）
     * @return array{rows: array, total: int}
     */
    public static function notices(int $limit, int $offset): array
    {
        $pdo = Database::pdo();
        $total = (int) $pdo->query(
            'SELECT COUNT(*) FROM ' . Database::table('messages') . ' WHERE ' . self::BROADCAST_OWNER
        )->fetchColumn();
        $rows = $pdo->query(
            'SELECT id, msg_type, notice_type, title, content, send_status, created_at, updated_at
             FROM ' . Database::table('messages') . '
             WHERE ' . self::BROADCAST_OWNER . "
             ORDER BY updated_at DESC, id DESC LIMIT {$limit} OFFSET {$offset}"
        )->fetchAll(PDO::FETCH_ASSOC);
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * 单条公告详情（编辑弹窗回填）
     */
    public static function noticeDetail(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, msg_type, notice_type, title, content FROM ' . Database::table('messages') . '
             WHERE id = :id AND ' . self::BROADCAST_OWNER . ' LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 发布全站公告：写全员消息并返回消息ID（邮件渠道由调用方按该ID回写发送状态）
     * 站内信落地即可见故记为已发送，邮件渠道先记待发送，投递后再回写
     */
    public static function createNotice(string $title, string $content, int $msgType, int $noticeType = 1): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('messages') . ' (user_id, msg_type, notice_type, title, content, biz_type, send_status)
             VALUES (NULL, :type, :ntype, :title, :content, 4, :status)'
        );
        $stmt->execute([
            ':type'   => $msgType,
            ':ntype'  => $noticeType === 2 ? 2 : 1,
            ':title'  => $title,
            ':content' => $content,
            ':status' => $msgType === 1 ? 1 : 0,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * 定向站内信：发给单个用户（与全员公告的 user_id IS NULL 区分）
     * 站内信落地即可见，故直接记为已发送；biz_type/biz_no 供消息中心跳转关联业务
     * @param int $bizType 关联业务类型 1包裹 2运单 3工单 4公告
     */
    public static function sendToUser(int $userId, string $title, string $content, int $bizType = 0, string $bizNo = ''): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('messages') . '
                (user_id, msg_type, title, content, biz_type, biz_no, is_read, send_status)
             VALUES (:uid, 1, :title, :content, :biz, :no, 0, 1)'
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':title'   => mb_substr($title, 0, 100),
            ':content' => $content,
            ':biz'     => $bizType > 0 ? $bizType : null,
            ':no'      => $bizNo !== '' ? mb_substr($bizNo, 0, 32) : null,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * 回写发送状态（0 待发送 / 1 已发送 / 2 发送失败）
     */
    public static function markSendStatus(int $id, int $status): void    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('messages') . ' SET send_status = :s WHERE id = :id AND ' . self::BROADCAST_OWNER
        );
        $stmt->execute([':s' => $status, ':id' => $id]);
    }

    /**
     * 编辑公告：刷新 updated_at 使其在用户端与后台列表中都排到最前
     * 改回站内信渠道时重置发送状态，避免残留上一次邮件投递的失败标记
     */
    public static function updateNotice(int $id, string $title, string $content, int $msgType, int $noticeType = 1): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('messages') . '
             SET title = :title, content = :content, msg_type = :type, notice_type = :ntype,
                 send_status = :status, updated_at = NOW()
             WHERE id = :id AND ' . self::BROADCAST_OWNER
        );
        $stmt->execute([
            ':title'   => $title,
            ':content' => $content,
            ':type'    => $msgType,
            ':ntype'   => $noticeType === 2 ? 2 : 1,
            ':status'  => $msgType === 1 ? 1 : 0,
            ':id'      => $id,
        ]);
    }

    public static function deleteNotice(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM ' . Database::table('messages') . ' WHERE id = :id AND ' . self::BROADCAST_OWNER
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * 用户端消息中心：全员公告 + 发给本人的消息，按最新时间倒序
     * @return array{rows: array, total: int}
     */
    /**
     * 个人消息箱：仅发给本人的定向消息；全员公告独立在「网站公告」页展示
     */
    public static function inbox(int $userId, int $limit, int $offset): array
    {
        $pdo = Database::pdo();
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('messages') . ' WHERE user_id = :uid');
        $countStmt->execute([':uid' => $userId]);
        $listStmt = $pdo->prepare(
            'SELECT id, user_id, msg_type, title, content, is_read, created_at, updated_at
             FROM ' . Database::table('messages') . '
             WHERE user_id = :uid ORDER BY updated_at DESC, id DESC LIMIT :limit OFFSET :offset'
        );
        $listStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();
        return ['rows' => $listStmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $countStmt->fetchColumn()];
    }

    /**
     * 网站公告列表（全员广播，user_id IS NULL 且 biz_type=4），会员端「网站公告」页用
     */
    public static function broadcastList(int $limit, int $offset): array
    {
        $pdo = Database::pdo();
        $total = (int) $pdo->query(
            'SELECT COUNT(*) FROM ' . Database::table('messages') . ' WHERE ' . self::BROADCAST_OWNER
        )->fetchColumn();
        $stmt = $pdo->prepare(
            'SELECT id, notice_type, title, content, created_at, updated_at
             FROM ' . Database::table('messages') . '
             WHERE ' . self::BROADCAST_OWNER . '
             ORDER BY updated_at DESC, id DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * 网站公告详情（会员端全文查看）
     */
    public static function broadcastGet(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, notice_type, title, content, created_at, updated_at
             FROM ' . Database::table('messages') . '
             WHERE id = :id AND ' . self::BROADCAST_OWNER . ' LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 未读数：全员公告是共享的单行记录，无法按户记录已读，故仅统计发给本人的消息
     */
    public static function unreadCount(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('messages') . '
             WHERE user_id = :uid AND is_read = 0'
        );
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 标记本人消息为已读（全员公告为共享记录，不改动其状态）
     * @return bool 是否确有本人的未读消息被标记
     */
    public static function markRead(int $userId, int $messageId): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('messages') . ' SET is_read = 1, read_at = NOW()
             WHERE id = :id AND user_id = :uid AND is_read = 0'
        );
        $stmt->execute([':id' => $messageId, ':uid' => $userId]);
        return $stmt->rowCount() === 1;
    }

    /**
     * 一键全部已读：当前用户的所有未读个人消息一次标记
     * @return int 标记的条数
     */
    public static function markAllRead(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('messages') . ' SET is_read = 1, read_at = NOW()
             WHERE user_id = :uid AND is_read = 0 AND user_id IS NOT NULL'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->rowCount();
    }
}
