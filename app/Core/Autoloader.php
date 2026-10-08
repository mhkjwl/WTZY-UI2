<?php declare(strict_types=1);
/**
 * @file app/Core/Autoloader.php
 * @description PSR-4 风格自动加载器：App\ 命名空间映射到 dev/app/ 目录（项目未使用 Composer）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Core;

final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefix = 'App\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
