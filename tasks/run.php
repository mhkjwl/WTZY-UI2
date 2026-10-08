<?php declare(strict_types=1);
/**
 * @file tasks/run.php
 * @description 系统自动任务命令行入口：自动签收 / 待激活积分入账 / 退运销毁兜底退款
 * @author AI
 * @version 0.6.1
 * @date 2026-09-16
 *
 * 用法：php tasks/run.php all             执行全部任务
 *       php tasks/run.php auto_sign       仅自动签收
 *       php tasks/run.php activate_points 仅积分入账
 *       php tasks/run.php refund_disposed 仅兜底退款
 * 说明：生产环境建议配置 cron，例如每 10 分钟执行一次 php /path/tasks/run.php all
 */

if (PHP_SAPI !== 'cli') {
    exit('仅允许命令行执行');
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/common.php';
require BASE_PATH . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Env;
use App\Services\TaskService;

Autoloader::register();
Env::load(BASE_PATH . '/.env');

$command = (string) ($argv[1] ?? 'all');
$start = microtime(true);

switch ($command) {
    case 'auto_sign':
        $count = TaskService::autoSign(200);
        echo "[自动签收] 处理 {$count} 单\n";
        break;
    case 'activate_points':
        $count = TaskService::activatePoints(500);
        echo "[积分入账] 激活 {$count} 笔\n";
        break;
    case 'refund_disposed':
        $count = TaskService::refundDisposed(200);
        echo "[兜底退款] 退款 {$count} 单\n";
        break;
    case 'all':
        $result = TaskService::runAll(200);
        echo "[自动签收] {$result['auto_sign']} 单\n";
        echo "[积分入账] {$result['activate_points']} 笔\n";
        echo "[兜底退款] {$result['refund_disposed']} 单\n";
        break;
    default:
        echo "未知命令：{$command}\n";
        echo "可用命令：all | auto_sign | activate_points | refund_disposed\n";
        exit(1);
}

echo '耗时 ' . number_format(microtime(true) - $start, 3) . " 秒\n";
exit(0);