<?php declare(strict_types=1);

/**
 * @file config/app.php
 * @description 应用基础配置（站点名/调试开关等，敏感项一律走 .env）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

use App\Core\Env;

return [
    'name'      => Env::get('APP_NAME', 'WTZY 全球转运'),
    'env'       => Env::get('APP_ENV', 'development'),
    'debug'     => Env::get('APP_DEBUG', 'true') === 'true',
    'url'       => Env::get('APP_URL', 'http://127.0.0.1:8099'),
    'lang'      => Env::get('APP_DEFAULT_LANG', 'zh-CN'),
    'currency'  => Env::get('APP_DEFAULT_CURRENCY', 'CNY'),
    'timezone'  => Env::get('APP_DEFAULT_TIMEZONE', 'Asia/Shanghai'),
];
