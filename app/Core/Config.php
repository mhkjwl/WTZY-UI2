<?php declare(strict_types=1);
/**
 * @file app/Core/Config.php
 * @description 配置读取器：点号路径访问 config/ 下的数组配置，如 Config::get('database.host')
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */

namespace App\Core;

final class Config
{
    private static array $items = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        [$file, $path] = array_pad(explode('.', $key, 2), 2, null);
        if (!isset(self::$items[$file])) {
            $file_path = dirname(__DIR__, 2) . '/config/' . $file . '.php';
            self::$items[$file] = is_file($file_path) ? require $file_path : [];
        }
        $value = self::$items[$file];
        if ($path === null) {
            return $value;
        }
        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
