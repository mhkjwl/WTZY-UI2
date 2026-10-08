<?php declare(strict_types=1);
/**
 * @file app/Controllers/Warehouse/HomeController.php
 * @description 仓库端作业台：今日作业量 + 我的任务 + 模块入口（按权限显隐）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Controllers\Warehouse;

use App\Models\PackageModel;
use App\Models\WarehouseTaskModel;
use App\Models\WaybillModel;

final class HomeController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $warehouseId = $this->warehouseId();
        $packageCounts = PackageModel::warehouseCounts($warehouseId);
        $pending = WaybillModel::warehousePendingCounts($warehouseId);
        $inStock = 0;
        foreach ($packageCounts as $status => $count) {
            if ((int) $status >= PackageModel::IN_STOCK_FROM && (int) $status <= 9) {
                $inStock += (int) $count;
            }
        }
        $taskCounts = WarehouseTaskModel::counts($warehouseId);

        $this->warehouseView('warehouse/home', [
            'title'  => '仓库作业台',
            'today'  => [
                'inbound' => $this->todayInboundCount($warehouseId),
                'packed'  => $this->todayPackedCount($warehouseId),
                'shipped' => $this->todayOutboundCount($warehouseId),
            ],
            'stats'  => [
                ['label' => '待收货', 'value' => PackageModel::pendingInboundCount($warehouseId), 'icon' => '📥', 'tone' => 'blue', 'href' => '/warehouse/inbound'],
                ['label' => '在库', 'value' => $inStock, 'icon' => '🏗️', 'tone' => 'emerald', 'href' => '/warehouse/storage'],
                ['label' => '待打包', 'value' => (int) $pending['packing'], 'icon' => '📦', 'tone' => 'orange', 'href' => '/warehouse/packing'],
                ['label' => '待出库', 'value' => (int) $pending['outbound'], 'icon' => '🚚', 'tone' => 'violet', 'href' => '/warehouse/outbound'],
            ],
            'abnormalCount' => PackageModel::warehouseAbnormalCount($warehouseId),
            'myTasks'  => WarehouseTaskModel::mine($warehouseId, $this->adminId(), 20),
            'taskCounts' => $taskCounts,
            'taskTypes'  => WarehouseTaskModel::TYPES,
            'taskStatus' => WarehouseTaskModel::STATUS,
            'modules'  => $this->modules(),
        ], 'dashboard', '仓库作业台');
    }

    /**
     * 已开发模块入口（未授权的不展示）
     */
    private function modules(): array
    {
        $all = [
            ['key' => 'inbound',  'icon' => '📥', 'name' => '收货入库', 'href' => '/warehouse/inbound',  'color' => 'blue',    'perm' => 'wh.inbound',     'desc' => '扫描快递单号 / 识别码匹配归属 / 称重量尺 / 上架'],
            ['key' => 'storage',  'icon' => '🏗️', 'name' => '仓储管理', 'href' => '/warehouse/storage',  'color' => 'emerald', 'perm' => 'wh.storage',     'desc' => '在库检索 / 上架移库 / 无主件归属 / 库位占用'],
            ['key' => 'packing',  'icon' => '📦', 'name' => '打包称重', 'href' => '/warehouse/packing',  'color' => 'amber',   'perm' => 'wh.packing',     'desc' => '拣货核对 / 打包录入 / 按实重重算运费'],
            ['key' => 'outbound', 'icon' => '🚚', 'name' => '出库交接', 'href' => '/warehouse/outbound', 'color' => 'violet',  'perm' => 'wh.outbound',    'desc' => '待出库运单 / 承运单号 / 出库确认'],
            ['key' => 'abnormal', 'icon' => '⚠️', 'name' => '异常处理', 'href' => '/warehouse/abnormal', 'color' => 'red',     'perm' => 'wh.abnormal',    'desc' => '破损缺件登记 / 退运销毁处置'],
            ['key' => 'tasks',    'icon' => '🗂️', 'name' => '任务分配', 'href' => '/warehouse/tasks',    'color' => 'cyan',    'perm' => 'wh.task.assign', 'desc' => '指派作业给操作员 / 进度跟踪'],
        ];
        return array_values(array_filter($all, fn(array $m): bool => $this->can($m['perm'])));
    }

    private function todayInboundCount(int $warehouseId): int
    {
        $stmt = \App\Core\Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . \App\Core\Database::table('packages') . '
             WHERE deleted_at IS NULL AND warehouse_id = :wh AND DATE(inbound_at) = CURDATE()'
        );
        $stmt->execute([':wh' => $warehouseId]);
        return (int) $stmt->fetchColumn();
    }

    private function todayPackedCount(int $warehouseId): int
    {
        $stmt = \App\Core\Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . \App\Core\Database::table('waybills') . '
             WHERE deleted_at IS NULL AND warehouse_id = :wh AND DATE(packed_at) = CURDATE()'
        );
        $stmt->execute([':wh' => $warehouseId]);
        return (int) $stmt->fetchColumn();
    }

    private function todayOutboundCount(int $warehouseId): int
    {
        $stmt = \App\Core\Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . \App\Core\Database::table('waybills') . '
             WHERE deleted_at IS NULL AND warehouse_id = :wh AND DATE(outbound_at) = CURDATE()'
        );
        $stmt->execute([':wh' => $warehouseId]);
        return (int) $stmt->fetchColumn();
    }
}
