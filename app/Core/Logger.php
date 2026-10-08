<?php declare(strict_types=1);
/**
 * @file app/Core/Logger.php
 * @description 轻量文件日志（JSON Lines，按通道分文件，写入 storage/logs）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 *
 * 用于记录支付回调等需要在事后追溯的异步事件；写日志失败不影响主流程。
 */

namespace App\Core;

final class Logger
{
    /**
     * 追加一条日志；通道名只允许字母数字与下划线，避免路径穿越
     */
    public static function write(string $channel, array $context, string $level = 'info'): void
    {
        if (preg_match('/^[a-z0-9_]+$/', $channel) !== 1) {
            return;
        }
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }
        $line = json_encode([
            'time'    => date('Y-m-d H:i:s'),
            'level'   => $level,
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }
        @file_put_contents($dir . '/' . $channel . '.log', $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
