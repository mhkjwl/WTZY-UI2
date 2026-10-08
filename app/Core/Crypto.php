<?php declare(strict_types=1);
/**
 * @file app/Core/Crypto.php
 * @description 对称加解密（AES-256-GCM，密钥派生自 .env 的 APP_KEY）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 *
 * 用途：需要在后台可维护、但又不能明文入库的敏感配置（如 SMTP 密码）。
 */

namespace App\Core;

use RuntimeException;

final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    /**
     * 加密并返回 base64（内含 iv 与 tag，便于整体存储）
     */
    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipherText === false) {
            throw new RuntimeException('加密失败');
        }
        return base64_encode($iv . $tag . $cipherText);
    }

    /**
     * 解密；数据损坏或密钥变更时返回空串，由调用方回落到其它配置源
     */
    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) <= 28) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipherText = substr($raw, 28);
        $plain = openssl_decrypt($cipherText, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }

    /**
     * 密钥派生：APP_KEY 经 sha256 归一为 32 字节；未配置时使用应用路径兜底，
     * 保证安装早期（.env 尚未生成）也不会直接抛错
     */
    private static function key(): string
    {
        $appKey = (string) Env::get('APP_KEY', '');
        if ($appKey === '') {
            $appKey = 'wtzy-fallback-key';
        }
        return hash('sha256', $appKey, true);
    }
}
