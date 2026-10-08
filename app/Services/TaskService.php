<?php declare(strict_types=1);
/**
 * @file app/Services/TaskService.php
 * @description 系统自动任务：自动签收 / 待激活积分入账 / 退运销毁兜底退款
 *              全部任务幂等可重试；由 CLI（tasks/run.php）、后台一键执行与页面懒执行共同驱动
 * @author AI
 * @version 0.6.2
 * @date 2026-09-16
 */

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\SettingsModel;
use PDO;
use Throwable;

final class TaskService
{
    /**
     * 自动签收：出库满 N 天且无异常在途的运单，置为已签收并登记积分
     * 清关异常、有进行中理赔、关联包裹异常/退回/销毁、有待处理异常单的一律跳过
     */
    public static function autoSign(int $limit = 100): int
    {
        $days = max(0, (int) SettingsModel::get('auto_sign_days', '15'));
        if ($days <= 0) {
            return 0;
        }
        $pdo = Database::pdo();
        $sql = 'SELECT w.id FROM ' . Database::table('waybills') . ' w
                WHERE w.deleted_at IS NULL AND w.status IN (5, 6, 7, 8)
                  AND w.outbound_at IS NOT NULL
                  AND w.outbound_at <= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
                  AND w.clear_status <> 4
                  AND NOT EXISTS (
                      SELECT 1 FROM ' . Database::table('claims') . ' c
                      WHERE c.waybill_id = w.id AND c.status IN (0, 1, 2, 5, 6)
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM ' . Database::table('waybill_packages') . ' wp
                      JOIN ' . Database::table('packages') . ' p ON p.id = wp.package_id
                      WHERE wp.waybill_id = w.id AND p.status IN (15, 16, 17)
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM ' . Database::table('waybill_packages') . ' wp2
                      JOIN ' . Database::table('package_abnormals') . ' a ON a.package_id = wp2.package_id
                      WHERE wp2.waybill_id = w.id AND a.status = 0
                  )
                ORDER BY w.outbound_at ASC LIMIT ' . (int) $limit;
        $ids = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $done = 0;
        foreach ($ids as $id) {
            try {
                \App\Models\WaybillModel::changeStatus((int) $id, 9, 0, '出库满 ' . $days . ' 天，系统自动签收', 4);
                $pdo->prepare('UPDATE ' . Database::table('waybills') . ' SET auto_signed = 1 WHERE id = :id')
                    ->execute([':id' => (int) $id]);
                $done++;
            } catch (Throwable $e) {
                Logger::write('tasks', [
                    'task' => 'auto_sign',
                    'waybill_id' => (int) $id,
                    'error' => $e->getMessage(),
                ], 'error');
            }
        }
        return $done;
    }

    /**
     * 待激活积分入账（签收满 T+N 天的记录转已到账）
     */
    public static function activatePoints(int $limit = 200): int
    {
        return PointsService::activateDue($limit);
    }

    /**
     * 退运/销毁兜底退款：未出库且包裹已全部退回/销毁、尚未退款的运单补退运费
     * 仓库端处置时已即时触发，本任务负责重试失败与补齐历史
     */
    public static function refundDisposed(int $limit = 100): int
    {
        $pdo = Database::pdo();
        $sql = 'SELECT w.id FROM ' . Database::table('waybills') . ' w
                WHERE w.deleted_at IS NULL AND w.status IN (2, 3, 4) AND w.refunded_at IS NULL
                  AND EXISTS (
                      SELECT 1 FROM ' . Database::table('waybill_packages') . ' wp WHERE wp.waybill_id = w.id
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM ' . Database::table('waybill_packages') . ' wp2
                      JOIN ' . Database::table('packages') . ' p ON p.id = wp2.package_id
                      WHERE wp2.waybill_id = w.id AND p.status NOT IN (16, 17)
                  )
                ORDER BY w.id ASC LIMIT ' . (int) $limit;
        $ids = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $done = 0;
        foreach ($ids as $id) {
            try {
                if (\App\Models\WaybillModel::refundIfAllPackagesDisposed((int) $id)) {
                    $done++;
                }
            } catch (Throwable $e) {
                Logger::write('tasks', [
                    'task' => 'refund_disposed',
                    'waybill_id' => (int) $id,
                    'error' => $e->getMessage(),
                ], 'error');
            }
        }
        return $done;
    }

    /**
     * 执行全部任务（CLI 与后台一键执行共用）
     * @return array{auto_sign: int, activate_points: int, refund_disposed: int}
     */
    public static function runAll(int $limit = 100): array
    {
        $result = ['auto_sign' => 0, 'activate_points' => 0, 'refund_disposed' => 0];
        try {
            $result['auto_sign'] = self::autoSign($limit);
        } catch (Throwable $e) {
            Logger::write('tasks', ['task' => 'auto_sign', 'error' => $e->getMessage()], 'error');
        }
        try {
            $result['activate_points'] = self::activatePoints($limit * 2);
        } catch (Throwable $e) {
            Logger::write('tasks', ['task' => 'activate_points', 'error' => $e->getMessage()], 'error');
        }
        try {
            $result['refund_disposed'] = self::refundDisposed($limit);
        } catch (Throwable $e) {
            Logger::write('tasks', ['task' => 'refund_disposed', 'error' => $e->getMessage()], 'error');
        }
        return $result;
    }

    /**
     * 懒执行：页面访问时顺带跑一次轻量任务，用设置项做原子抢占限频（默认 10 分钟一次）
     * 无人访问时不影响业务；配置服务器 cron 后该入口自动退化为兜底
     */
    public static function lazyRun(int $limit = 20): void
    {
        try {
            $minutes = max(1, (int) SettingsModel::get('task_lazy_minutes', '10'));
            $pdo = Database::pdo();
            $pdo->prepare(
                'INSERT IGNORE INTO ' . Database::table('settings') . ' (setting_key, setting_value, group_name)
                 VALUES (:k, :v, :g)'
            )->execute([':k' => 'task_last_run_at', ':v' => '2000-01-01 00:00:00', ':g' => 'order']);
            $now = date('Y-m-d H:i:s');
            $threshold = date('Y-m-d H:i:s', time() - $minutes * 60);
            // 原子抢占：只有把上次执行时间推进成功的那次请求才真正执行任务，避免并发重复跑
            $claim = $pdo->prepare(
                'UPDATE ' . Database::table('settings') . '
                 SET setting_value = :now WHERE setting_key = :k AND setting_value < :threshold'
            );
            $claim->execute([':now' => $now, ':k' => 'task_last_run_at', ':threshold' => $threshold]);
            if ($claim->rowCount() !== 1) {
                return;
            }
            self::runAll($limit);
        } catch (Throwable $e) {
            Logger::write('tasks', ['task' => 'lazy_run', 'error' => $e->getMessage()], 'error');
        }
    }
}