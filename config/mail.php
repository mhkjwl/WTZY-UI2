<?php declare(strict_types=1);

/**
 * @file config/mail.php
 * @description 邮件发送配置（全部取自 .env，未配置时邮件相关功能自动禁用）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */

use App\Core\Env;

return [
    'host'      => Env::get('MAIL_HOST', ''),
    'port'      => (int) Env::get('MAIL_PORT', '465'),
    // ssl：连接即加密（常见 465）；tls：STARTTLS 升级（常见 587）；none：明文（仅内网调试）
    'secure'    => Env::get('MAIL_SECURE', 'ssl'),
    'user'      => Env::get('MAIL_USER', ''),
    'pass'      => Env::get('MAIL_PASS', ''),
    'from'      => Env::get('MAIL_FROM', ''),
    'from_name' => Env::get('MAIL_FROM_NAME', 'WTZY 全球转运'),
];
