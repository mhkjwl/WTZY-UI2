<?php declare(strict_types=1);
/**
 * @file app/Controllers/Warehouse/TaskController.php
 * @description 任务分配：主管把入库/上架/打包/出库/异常作业指派给操作员并跟踪完成情况
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */

namespace App\Controllers\Warehouse;

use App\Core\Request;
use App\Models\AdminModel;
use App\Models\WarehouseTaskModel;
use RuntimeException;

final class TaskController extends BaseController
{
    /** 任务类型 => [目标类型 1包裹 2运单, 中文名] */
    private const TYPE_MAP = [
        1 => [WarehouseTaskModel::TARGET_PACKAGE, '收货入库'],
        2 => [WarehouseTaskModel::TARGET_PACKAGE, '上架理货'],
        3 => [WarehouseTaskModel::TARGET_WAYBILL, '打包称重'],
        4 => [WarehouseTaskModel::TARGET_WAYBILL, '出库交接'],
        5 => [WarehouseTaskModel::TARGET_PACKAGE, '异常处理'],
    ];

    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('wh.task.assign');
        $page = $this->pageParams();
        $status = (int) Request::query('status', '-1');
        $assignee = (int) Request::query('assignee', '0');
        $type = (int) Request::query('type', '0');
        $keyword = (string) Request::query('kw', '');
        $result = WarehouseTaskModel::search($this->warehouseId(), $status, $assignee, $type, $keyword, $page['limit'], $page['offset']);

        $data = [
            'title'     => '任务分配',
            'rows'      => $result['rows'],
            'total'     => $result['total'],
            'page'      => $page['page'],
            'pages'     => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'status'    => $status,
            'assignee'  => $assignee,
            'type'      => $type,
            'keyword'   => $keyword,
            'statuses'  => WarehouseTaskModel::STATUS,
            'types'     => WarehouseTaskModel::TYPES,
            'operators' => AdminModel::warehouseStaff($this->warehouseId()),
            'byOperator' => WarehouseTaskModel::byOperator($this->warehouseId()),
            'counts'    => WarehouseTaskModel::counts($this->warehouseId()),
            'base'      => '/warehouse/tasks',
        ];
        if ($this->isPartial()) {
            $this->renderTable('warehouse/tasks_table', $data);
        }
        $this->warehouseView('warehouse/tasks', $data, 'tasks', '任务分配');
    }

    /**
     * 新建指派：校验目标单号属于本仓库，避免把别仓的活派给自己人
     */
    public function save(): never
    {
        $this->requireStaff();
        $this->enforce('wh.task.assign');
        $this->verifyCsrf();

        $taskType = (int) Request::input('task_type', 0);
        $targetNo = trim((string) Request::input('target_no', ''));
        $assignAdminId = (int) Request::input('assign_admin_id', 0);
        $priority = (int) Request::input('priority', 0) === 1 ? 1 : 0;
        $remark = trim((string) Request::input('remark', ''));

        if (!isset(self::TYPE_MAP[$taskType])) {
            $this->fail('请选择作业类型');
        }
        if ($targetNo === '') {
            $this->fail('请填写目标单号（包裹编号或运单号）');
        }
        if (mb_strlen($remark) > 255) {
            $this->fail('指派说明不超过 255 字');
        }
        $operator = AdminModel::findStaff($assignAdminId);
        if ($operator === null) {
            $this->fail('请选择执行人');
        }
        if ((int) $operator['warehouse_id'] !== $this->warehouseId()) {
            $this->fail('只能指派给本仓库的员工');
        }

        [$targetType, $typeName] = self::TYPE_MAP[$taskType];
        // 按目标类型定位真实目标并校验仓库归属
        if ($targetType === WarehouseTaskModel::TARGET_PACKAGE) {
            $package = $this->findPackageByNo($targetNo);
            if ($package === null) {
                $this->fail('未找到包裹 ' . $targetNo);
            }
            $targetId = (int) $package['id'];
            $realNo = (string) $package['package_no'];
        } else {
            $waybill = null;
            foreach (['waybill_no', 'carrier_tracking_no'] as $column) {
                $stmt = \App\Core\Database::pdo()->prepare(
                    'SELECT id, waybill_no FROM ' . \App\Core\Database::table('waybills') . '
                     WHERE warehouse_id = :wh AND deleted_at IS NULL AND ' . $column . ' = :no LIMIT 1'
                );
                $stmt->execute([':wh' => $this->warehouseId(), ':no' => $targetNo]);
                $waybill = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($waybill !== false) {
                    break;
                }
            }
            if (empty($waybill)) {
                $this->fail('未找到本仓库的运单 ' . $targetNo);
            }
            $targetId = (int) $waybill['id'];
            $realNo = (string) $waybill['waybill_no'];
        }

        try {
            $id = WarehouseTaskModel::create($this->warehouseId(), [
                'task_type'       => $taskType,
                'target_type'     => $targetType,
                'target_id'       => $targetId,
                'target_no'       => $realNo,
                'assign_admin_id' => $assignAdminId,
                'priority'        => $priority,
                'remark'          => $remark,
            ], $this->adminId());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->auditLog('warehouse', 'task_assign', $id, [
            'type' => $typeName, 'target_no' => $realNo, 'assign_to' => $assignAdminId,
        ], 'task');
        $this->ok(['id' => $id], '已指派「' . $typeName . '」给 ' . $operator['real_name'] . '，作业完成后任务自动收口');
    }

    /**
     * 取消任务
     */
    public function cancel(): never
    {
        $this->requireStaff();
        $this->enforce('wh.task.assign');
        $this->verifyCsrf();
        try {
            WarehouseTaskModel::cancel((int) Request::input('id', 0));
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '任务已取消');
    }

    /**
     * 按包裹编号定位本仓库包裹
     */
    private function findPackageByNo(string $packageNo): ?array
    {
        $stmt = \App\Core\Database::pdo()->prepare(
            'SELECT id, package_no FROM ' . \App\Core\Database::table('packages') . '
             WHERE warehouse_id = :wh AND deleted_at IS NULL AND package_no = :no LIMIT 1'
        );
        $stmt->execute([':wh' => $this->warehouseId(), ':no' => $packageNo]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
