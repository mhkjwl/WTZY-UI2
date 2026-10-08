<?php declare(strict_types=1);

/**
 * @file config/database.php
 * @description 数据库连接配置（读取 .env，未提供时回退本机开发默认值）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

use App\Core\Env;

return [
    'host'    => Env::get('DB_HOST', '127.0.0.1'),
    'port'    => Env::get('DB_PORT', '3306'),
    'name'    => Env::get('DB_NAME', 'wtzy'),
    'user'    => Env::get('DB_USER', 'root'),
    'pass'    => Env::get('DB_PASS', ''),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    'prefix'  => Env::get('DB_PREFIX', 'wt_'),
];
