<?php declare(strict_types=1);
/**
 * @file app/Core/Mailer.php
 * @description 邮件发送（原生 SMTP 客户端，无第三方依赖；配置见 config/mail.php 与 .env）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

namespace App\Core;

use App\Models\SettingsModel;
use RuntimeException;

final class Mailer
{
    private const TIMEOUT = 10;

    /** @var resource|null */
    private $socket = null;

    /**
     * 是否已配置发信：主机与发件人都填了才算可用
     */
    public static function isConfigured(): bool
    {
        return self::setting('mail_host', 'host') !== ''
            && self::setting('mail_from', 'from') !== '';
    }

    /**
     * 配置读取优先级：后台「邮箱设置」（settings 表）→ .env
     * 密码在 settings 中以 AES 密文存放，此处解密后使用
     */
    private static function setting(string $key, string $envKey, string $default = ''): string
    {
        try {
            $value = SettingsModel::get($key, '');
            if ($value !== '') {
                return $key === 'mail_pass' ? Crypto::decrypt($value) : $value;
            }
        } catch (\Throwable) {
            // 数据库尚未就绪（如安装向导阶段）时直接回落到 .env
        }
        return (string) Config::get('mail.' . $envKey, $default);
    }

    /**
     * 发送 HTML 邮件；未配置或发送失败均抛异常，由调用方转成用户可读提示
     */
    public static function send(string $to, string $subject, string $htmlBody): void
    {
        if (!self::isConfigured()) {
            throw new RuntimeException('邮件服务未配置，请联系管理员');
        }
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('收件邮箱格式不正确');
        }
        (new self())->deliver($to, $subject, $htmlBody);
    }

    private function deliver(string $to, string $subject, string $htmlBody): void
    {
        $host = self::setting('mail_host', 'host');
        $port = (int) (self::setting('mail_port', 'port') ?: 465);
        $secure = strtolower(self::setting('mail_secure', 'secure', 'ssl'));
        $user = self::setting('mail_user', 'user');
        $pass = self::setting('mail_pass', 'pass');
        $from = self::setting('mail_from', 'from');
        $fromName = self::setting('mail_from_name', 'from_name');

        $transport = $secure === 'ssl' ? 'ssl' : 'tcp';
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $socket = @stream_socket_client(
            sprintf('%s://%s:%d', $transport, $host, $port),
            $errno,
            $errstr,
            self::TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($socket === false) {
            throw new RuntimeException('邮件服务器连接失败');
        }
        $this->socket = $socket;
        stream_set_timeout($this->socket, self::TIMEOUT);

        try {
            $this->expect([220]);
            $this->command('EHLO ' . (gethostname() ?: 'localhost'), [250]);

            if ($secure === 'tls') {
                $this->command('STARTTLS', [220]);
                if (stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT) !== true) {
                    throw new RuntimeException('邮件服务器 TLS 握手失败');
                }
                $this->command('EHLO ' . (gethostname() ?: 'localhost'), [250]);
            }

            if ($user !== '') {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($user), [334]);
                $this->command(base64_encode($pass), [235]);
            }

            $this->command('MAIL FROM:<' . $from . '>', [250]);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', [354]);
            $this->write($this->buildMessage($to, $subject, $htmlBody, $from, $fromName));
            $this->expect([250]);
            $this->command('QUIT', [221]);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    private function buildMessage(string $to, string $subject, string $htmlBody, string $from, string $fromName): string
    {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $headers = [
            'From: ' . $encodedName . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Date: ' . date('r'),
        ];
        $body = chunk_split(base64_encode($htmlBody), 76, "\r\n");
        // 正文以点开头的行需转义，避免被当作数据结束标记
        return implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n";
    }

    /**
     * 发送命令并校验响应码
     * @param int[] $expect
     */
    private function command(string $command, array $expect): void
    {
        $this->write($command . "\r\n");
        $this->expect($expect);
    }

    private function write(string $data): void
    {
        if (!is_resource($this->socket) || fwrite($this->socket, $data) === false) {
            throw new RuntimeException('邮件发送中断');
        }
    }

    /**
     * 读取一行响应并校验首三位状态码
     * @param int[] $expect
     */
    private function expect(array $expect): void
    {
        $line = '';
        while (($chunk = fgets($this->socket, 1024)) !== false) {
            $line .= $chunk;
            // 多行响应以「状态码+空格」结束
            if (strlen($chunk) < 4 || $chunk[3] !== '-') {
                break;
            }
        }
        if ($line === '') {
            throw new RuntimeException('邮件服务器无响应');
        }
        $code = (int) substr($line, 0, 3);
        if (!in_array($code, $expect, true)) {
            throw new RuntimeException('邮件服务器返回异常：' . $code);
        }
    }
}
