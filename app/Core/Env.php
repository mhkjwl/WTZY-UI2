<?php declare(strict_types=1);
/**
 * @file app/Core/Env.php
 * @description .env 环境变量加载器（KEY=VALUE 格式；真实 .env 由安装程序生成，不入版本库）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Core;

final class Env
{
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }
            // 去除成对包裹的引号，避免引号进入运行时配置值
            $len = strlen($value);
            if ($len >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[0] === $value[$len - 1]) {
                $value = substr($value, 1, -1);
            }
            self::$vars[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }
        $env = getenv($key);
        return $env === false ? $default : $env;
    }
}
