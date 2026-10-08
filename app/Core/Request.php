<?php declare(strict_types=1);
/**
 * @file app/Core/Request.php
 * @description 请求读取：路径解析、JSON/表单参数统一取值、请求头与客户端 IP
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

namespace App\Core;

final class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = rawurldecode($uri);
        return $path !== '/' ? rtrim($path, '/') : '/';
    }

    public static function query(string $key, ?string $default = null): ?string
    {
        $value = $_GET[$key] ?? $default;
        return $value === null ? null : (string) $value;
    }

    /**
     * 统一取参：JSON 请求体优先，其次表单 POST（规范 §5.5：业务参数一律走请求体）
     */
    public static function input(string $key, mixed $default = null): mixed
    {
        $body = self::jsonBody();
        if (array_key_exists($key, $body)) {
            return $body[$key];
        }
        return $_POST[$key] ?? $default;
    }

    public static function jsonBody(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($contentType, 'application/json')) {
            return $cached = [];
        }
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        return $cached = is_array($decoded) ? $decoded : [];
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /**
     * 接口请求（/api 前缀或显式 Accept JSON）错误时返回 JSON 而非 HTML 页面
     */
    public static function wantsJson(): bool
    {
        if (str_starts_with(self::path(), '/api')) {
            return true;
        }
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
