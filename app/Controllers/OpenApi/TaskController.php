<?php declare(strict_types=1);
/**
 * @file app/Controllers/OpenApi/TaskController.php
 * @description 自动任务远程触发端点：供外部定时任务（cron / 云函数 / 其他系统）通过 URL 调用
 * @author AI
 * @version 0.6.1
 * @date 2026-09-16
 */

namespace App\Controllers\OpenApi;

use App\Core\Request;
use App\Models\SettingsModel;
use App\Services\TaskService;

final class TaskController extends BaseController
{
    /**
     * 执行全部自动任务（远程触发）
     * 鉴权：URL 携带 ?token= 与设置项 task_run_token 一致才放行；
     * 未配置 token 时拒绝执行，避免被随意调用
     */
    public function run(): never
    {
        $configured = (string) SettingsModel::get('task_run_token', '');
        $given = (string) Request::query('token', '');
        if ($configured === '' || $given === '' || !hash_equals($configured, $given)) {
            $this->fail('token 无效或未配置', 403);
        }

        $result = TaskService::runAll(200);
        SettingsModel::upsert('task_last_run_at', date('Y-m-d H:i:s'), 'order', '自动任务上次执行时间（懒执行限频用）');
        $this->ok($result, '任务已执行：自动签收 ' . $result['auto_sign'] . ' 单、积分入账 '
            . $result['activate_points'] . ' 笔、兜底退款 ' . $result['refund_disposed'] . ' 单');
    }
}