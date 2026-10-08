<?php declare(strict_types=1);
/**
 * @file app/Core/Database.php
 * @description PDO 连接单例（utf8mb4 / 异常模式 / 本地预处理），连接信息来自 config/database.php
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    /**
     * 惰性连接：仅在真正触库时建立连接，静态页/接口不产生数据库开销
     */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            Config::get('database.host', '127.0.0.1'),
            Config::get('database.port', '3306'),
            Config::get('database.name', 'wtzy'),
            Config::get('database.charset', 'utf8mb4')
        );
        self::$pdo = new PDO($dsn, (string) Config::get('database.user', 'root'), (string) Config::get('database.pass', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        return self::$pdo;
    }

    /**
     * 返回带配置前缀的反引号表名（前缀由安装向导写入 DB_PREFIX，默认 wt_）
     */
    public static function table(string $name): string
    {
        return '`' . Config::get('database.prefix', 'wt_') . $name . '`';
    }
}
