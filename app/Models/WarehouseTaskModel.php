<?php declare(strict_types=1);
/**
 * @file app/Models/WarehouseTaskModel.php
 * @description 仓库作业任务数据访问：主管指派作业给操作员，作业完成后自动收口
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class WarehouseTaskModel
{
    /** 任务类型（与仓库端模块一一对应） */
    public const TYPES = [
        1 => '收货入库', 2 => '上架理货', 3 => '打包称重', 4 => '出库交接', 5 => '异常处理',
    ];

    /** 状态：0待处理 1处理中 2已完成 3已取消 */
    public const STATUS = [
        0 => '待处理', 1 => '处理中', 2 => '已完成', 3 => '已取消',
    ];

    /** 目标类型 */
    public const TARGET_PACKAGE = 1;
    public const TARGET_WAYBILL = 2;

    /**
     * 任务列表（按状态/执行人/类型筛选，可按单号或任务号检索）
     * @return array{rows: array, total: int}
     */
    public static function search(int $warehouseId, int $status, int $assignAdminId, int $taskType, string $keyword, int $limit, int $offset): array
    {
        $where = ['t.warehouse_id = :wh'];
        $params = [':wh' => $warehouseId];
        if ($status >= 0) {
            $where[] = 't.status = :status';
            $params[':status'] = $status;
        }
        if ($assignAdminId > 0) {
            $where[] = 't.assign_admin_id = :aid';
            $params[':aid'] = $assignAdminId;
        }
        if ($taskType > 0) {
            $where[] = 't.task_type = :type';
            $params[':type'] = $taskType;
        }
        if ($keyword !== '') {
            $where[] = KeywordFilter::any(['t.task_no', 't.target_no'], $params, $keyword, 'tkw');
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $count = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('warehouse_tasks') . " t WHERE {$whereSql}");
        $count->execute($params);
        $list = $pdo->prepare(
            'SELECT t.id, t.task_no, t.task_type, t.target_type, t.target_id, t.target_no, t.status, t.priority,
                    t.remark, t.finish_remark, t.assigned_at, t.finished_at,
                    a.real_name AS assign_name, b.real_name AS assign_by_name
             FROM ' . Database::table('warehouse_tasks') . ' t
             LEFT JOIN ' . Database::table('admins') . ' a ON a.id = t.assign_admin_id
             LEFT JOIN ' . Database::table('admins') . ' b ON b.id = t.assign_by
             WHERE ' . $whereSql . " ORDER BY t.status ASC, t.priority DESC, t.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $list->execute($params);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    /**
     * 指派给某人的未完成任务（工作台「我的任务」）
     */
    public static function mine(int $warehouseId, int $adminId, int $limit = 20): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, task_no, task_type, target_type, target_id, target_no, status, priority, remark, assigned_at
             FROM ' . Database::table('warehouse_tasks') . '
             WHERE warehouse_id = :wh AND assign_admin_id = :aid AND status IN (0, 1)
             ORDER BY priority DESC, id ASC LIMIT ' . (int) $limit
        );
        $stmt->execute([':wh' => $warehouseId, ':aid' => $adminId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 执行人的未完成任务数（用于导航徽标）
     */
    public static function mineCount(int $warehouseId, int $adminId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('warehouse_tasks') . '
             WHERE warehouse_id = :wh AND assign_admin_id = :aid AND status IN (0, 1)'
        );
        $stmt->execute([':wh' => $warehouseId, ':aid' => $adminId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 各状态任务数（状态编码为键）
     */
    public static function counts(int $warehouseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('warehouse_tasks') . '
             WHERE warehouse_id = :wh GROUP BY status'
        );
        $stmt->execute([':wh' => $warehouseId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'c', 'status');
    }

    /**
     * 按执行人汇总未完成任务（主管看板：谁手上还有多少活）
     */
    public static function byOperator(int $warehouseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.id, a.real_name, a.username,
                    SUM(CASE WHEN t.status IN (0, 1) THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN t.status = 2 THEN 1 ELSE 0 END) AS finished
             FROM ' . Database::table('warehouse_tasks') . ' t
             JOIN ' . Database::table('admins') . ' a ON a.id = t.assign_admin_id
             WHERE t.warehouse_id = :wh GROUP BY a.id, a.real_name, a.username ORDER BY pending DESC, a.id ASC'
        );
        $stmt->execute([':wh' => $warehouseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 新建任务
     */
    public static function create(int $warehouseId, array $data, int $assignBy): int
    {
        $pdo = Database::pdo();
        $taskNo = '';
        for ($i = 0; $i < 5; $i++) {
            $candidate = 'WT' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $check = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('warehouse_tasks') . ' WHERE task_no = :no');
            $check->execute([':no' => $candidate]);
            if ((int) $check->fetchColumn() === 0) {
                $taskNo = $candidate;
                break;
            }
        }
        if ($taskNo === '') {
            throw new RuntimeException('任务号生成失败，请重试');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO ' . Database::table('warehouse_tasks') . '
                (task_no, warehouse_id, task_type, target_type, target_id, target_no, assign_admin_id, assign_by, status, priority, remark)
             VALUES (:no, :wh, :type, :ttype, :tid, :tno, :aid, :by, 0, :priority, :remark)'
        );
        $stmt->execute([
            ':no'       => $taskNo,
            ':wh'       => $warehouseId,
            ':type'     => $data['task_type'],
            ':ttype'    => $data['target_type'],
            ':tid'      => $data['target_id'],
            ':tno'      => $data['target_no'],
            ':aid'      => $data['assign_admin_id'],
            ':by'       => $assignBy,
            ':priority' => $data['priority'],
            ':remark'   => $data['remark'] !== '' ? $data['remark'] : null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * 转处理中（操作员打开作业时）
     */
    public static function markProcessing(int $id): void
    {
        Database::pdo()->prepare(
            'UPDATE ' . Database::table('warehouse_tasks') . ' SET status = 1 WHERE id = :id AND status = 0'
        )->execute([':id' => $id]);
    }

    public static function cancel(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('warehouse_tasks') . ' SET status = 3, finished_at = NOW() WHERE id = :id AND status IN (0, 1)'
        );
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('仅待处理或处理中的任务可以取消');
        }
    }

    /**
     * 作业完成时自动收口对应任务（同一目标可能有多个任务，一并完成）
     * @param int $targetType TARGET_PACKAGE / TARGET_WAYBILL
     */
    public static function finishByTarget(int $targetType, int $targetId, string $remark): void
    {
        Database::pdo()->prepare(
            'UPDATE ' . Database::table('warehouse_tasks') . '
             SET status = 2, finished_at = NOW(), finish_remark = :remark
             WHERE target_type = :ttype AND target_id = :tid AND status IN (0, 1)'
        )->execute([
            ':remark' => $remark !== '' ? mb_substr($remark, 0, 255) : null,
            ':ttype'  => $targetType,
            ':tid'    => $targetId,
        ]);
    }

    /**
     * 该目标是否已有未完成任务（避免重复指派同一件活）
     */
    public static function hasOpenTask(int $targetType, int $targetId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('warehouse_tasks') . '
             WHERE target_type = :ttype AND target_id = :tid AND status IN (0, 1)'
        );
        $stmt->execute([':ttype' => $targetType, ':tid' => $targetId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
