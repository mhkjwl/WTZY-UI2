<?php declare(strict_types=1);
/**
 * @file app/Services/NoticeService.php
 * @description 公告邮件投递：按注册邮箱向可发送用户逐个投递，统计成功/失败数
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

namespace App\Services;

use App\Core\Database;
use App\Core\Mailer;
use App\Models\MessageModel;
use PDO;
use Throwable;

final class NoticeService
{
    /** 单次投递上限：避免一次性同步发送过多邮件拖垮页面响应 */
    public const BATCH_LIMIT = 500;

    /**
     * 公告邮件的收件人（正常状态、未删除、已绑定邮箱的用户）
     */
    public static function recipients(int $limit = self::BATCH_LIMIT): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, nickname, email FROM ' . Database::table('users') . "
             WHERE deleted_at IS NULL AND status = 1 AND email IS NOT NULL AND email <> ''
             ORDER BY id ASC LIMIT {$limit}"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 投递公告邮件并把结果回写消息的发送状态
     * @return array{total: int, sent: int, failed: int, truncated: bool, error: string}
     */
    public static function deliver(int $noticeId, string $title, string $content): array
    {
        $result = ['total' => 0, 'sent' => 0, 'failed' => 0, 'truncated' => false, 'error' => ''];
        if (!Mailer::isConfigured()) {
            $result['error'] = '邮件服务未配置，请先在「系统 → 邮箱设置」中填写 SMTP 服务器与发件邮箱';
            MessageModel::markSendStatus($noticeId, 0);
            return $result;
        }

        $recipients = self::recipients(self::BATCH_LIMIT + 1);
        if (count($recipients) > self::BATCH_LIMIT) {
            $result['truncated'] = true;
            $recipients = array_slice($recipients, 0, self::BATCH_LIMIT);
        }

        $html = self::body($title, $content);
        foreach ($recipients as $user) {
            $result['total']++;
            try {
                Mailer::send((string) $user['email'], $title, $html);
                $result['sent']++;
            } catch (Throwable $e) {
                $result['failed']++;
                if ($result['error'] === '') {
                    $result['error'] = $e->getMessage();
                }
            }
        }
        if ($result['total'] === 0) {
            $result['error'] = '没有已绑定邮箱的正常状态用户，本次未投递';
        }
        // 全部成功才算已发送；部分失败标记为发送失败，便于人工重发
        MessageModel::markSendStatus($noticeId, $result['sent'] > 0 && $result['failed'] === 0 ? 1 : 2);
        return $result;
    }

    /**
     * 公告邮件正文（与站内信同一份标题与内容，仅做 HTML 包装与换行处理）
     */
    private static function body(string $title, string $content): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeBody = nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="utf-8"><title>{$safeTitle}</title></head>
<body style="margin:0;padding:24px;background:#f5f7fb;font-family:'PingFang SC','Microsoft YaHei',system-ui,sans-serif;color:#1f2937">
  <div style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #e5e9f2;border-radius:12px;padding:24px">
    <h2 style="margin:0 0 16px;font-size:18px">{$safeTitle}</h2>
    <div style="font-size:14px;line-height:1.9;color:#374151">{$safeBody}</div>
    <p style="margin:24px 0 0;padding-top:16px;border-top:1px solid #eef2f7;font-size:12px;color:#94a3b8">
      本邮件由系统自动发出，请勿直接回复。
    </p>
  </div>
</body></html>
HTML;
    }
}
