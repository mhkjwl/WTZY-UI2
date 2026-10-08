<?php declare(strict_types=1);
/**
 * @file app/Models/VerifyCodeModel.php
 * @description 邮箱/短信验证码的签发与核销（只存哈希、限频、限次、一次性）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

final class VerifyCodeModel
{
    public const CHANNEL_EMAIL = 1;
    public const CHANNEL_SMS = 2;

    private const MAX_ATTEMPTS = 5;
    private const RESEND_INTERVAL = 60;

    /**
     * 签发一枚验证码并落库，返回明文（仅供发送使用，不回传前端）
     */
    public static function issue(string $target, int $channel, string $purpose, int $ttl, string $ip, int $digits = 6): string
    {
        $code = '';
        for ($i = 0; $i < $digits; $i++) {
            $code .= (string) random_int(0, 9);
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('verify_codes') . '
             (target, channel, purpose, code_hash, expires_at, ip)
             VALUES (:target, :channel, :purpose, :hash, DATE_ADD(NOW(), INTERVAL :ttl SECOND), :ip)'
        );
        $stmt->execute([
            ':target'  => $target,
            ':channel' => $channel,
            ':purpose' => $purpose,
            ':hash'    => password_hash($code, PASSWORD_DEFAULT),
            ':ttl'     => $ttl,
            ':ip'      => $ip,
        ]);
        return $code;
    }

    /**
     * 同一目标 + 用途的重发间隔限制
     */
    public static function isTooFrequent(string $target, string $purpose): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('verify_codes') . '
             WHERE target = :target AND purpose = :purpose
               AND created_at > DATE_SUB(NOW(), INTERVAL :interval SECOND)'
        );
        $stmt->execute([':target' => $target, ':purpose' => $purpose, ':interval' => self::RESEND_INTERVAL]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * 时间窗口内同一邮箱/手机号的发码次数（超出视为滥用，拒绝发送）
     */
    public static function recentIssueCount(string $target, int $seconds): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('verify_codes') . '
             WHERE target = :target AND created_at > DATE_SUB(NOW(), INTERVAL :interval SECOND)'
        );
        $stmt->execute([':target' => $target, ':interval' => $seconds]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 时间窗口内同一 IP 的发码次数（防止单一来源批量骚扰多个邮箱）
     */
    public static function recentIpIssueCount(string $ip, int $seconds): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('verify_codes') . '
             WHERE ip = :ip AND created_at > DATE_SUB(NOW(), INTERVAL :interval SECOND)'
        );
        $stmt->execute([':ip' => $ip, ':interval' => $seconds]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 核销验证码：取最近一条未使用且未过期的记录比对，成功即标记已用
     * 失败按次计数，超过上限的记录直接作废，需重新获取
     */
    public static function consume(string $target, string $purpose, string $code): void
    {
        $pdo = Database::pdo();
        $error = null;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT id, code_hash, attempts FROM ' . Database::table('verify_codes') . '
                 WHERE target = :target AND purpose = :purpose
                   AND used_at IS NULL AND expires_at > NOW()
                 ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':target' => $target, ':purpose' => $purpose]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $markUsed = static function (int $id) use ($pdo): void {
                $pdo->prepare('UPDATE ' . Database::table('verify_codes') . ' SET used_at = NOW() WHERE id = :id')
                    ->execute([':id' => $id]);
            };

            if ($row === false) {
                $error = '验证码不存在或已过期，请重新获取';
            } elseif ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
                $markUsed((int) $row['id']);
                $error = '验证码错误次数过多，请重新获取';
            } elseif ($code === '' || !password_verify($code, (string) $row['code_hash'])) {
                $pdo->prepare('UPDATE ' . Database::table('verify_codes') . ' SET attempts = attempts + 1 WHERE id = :id')
                    ->execute([':id' => (int) $row['id']]);
                $error = '验证码不正确';
            } else {
                $markUsed((int) $row['id']);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * 清理已过期或已使用的历史记录（可由定时任务调用）
     */
    public static function purgeExpired(int $keepDays = 7): void
    {
        Database::pdo()->exec(
            'DELETE FROM ' . Database::table('verify_codes') . '
             WHERE expires_at < DATE_SUB(NOW(), INTERVAL ' . (int) $keepDays . ' DAY)'
        );
    }
}
