<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/HomeController.php
 * @description 管理后台控制台：实时运营概览 + 模块上线状态 + 系统概览
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

namespace App\Controllers\Admin;

use App\Core\Database;
use PDO;

final class HomeController extends BaseController
{
    /** 模块在线状态：live=已上线（可进入），planned=未开发（保留提示不给入口） */
    private const MODULE_STATUS = [
        'users'     => 'live',
        'packages'  => 'live',
        'orders'    => 'live',
        'warehouse' => 'live',
        'channels'  => 'live',
        'customs'   => 'live',
        'finance'   => 'live',
        'tickets'   => 'live',
        'marketing' => 'live',
        'reports'   => 'live',
    ];

    public function index(): never
    {
        $this->requireStaff();
        // 管理端访问也顺带推进自动任务，保证无人访问用户端时任务仍能跟进
        \App\Services\TaskService::lazyRun();

        $counts = $this->counts();
        // 只展示当前角色有权进入的模块，避免点进去才提示无权限
        $modules = array_values(array_filter(
            $this->modules(),
            fn(array $m): bool => $m['perm'] === null || $this->can($m['perm'])
        ));
        $liveCount = count(array_filter($modules, static fn(array $m): bool => $m['status'] === 'live'));

        $this->adminView('admin/home', [
            'stats'       => $this->stats($counts),
            'meters'      => $this->meters($counts),
            'modules'     => $modules,
            'liveCount'   => $liveCount,
            'moduleCount' => count($modules),
        ], 'dashboard', '控制台');
    }

    /**
     * 控制台运营指标（一次取数，供统计卡与概览条共用）
     */
    private function counts(): array
    {
        $pdo = Database::pdo();
        $users = Database::table('users');
        $waybills = Database::table('waybills');
        $packages = Database::table('packages');
        $tickets = Database::table('tickets');
        $warehouses = Database::table('warehouses');
        $channels = Database::table('channels');

        $one = static fn(string $sql): int => (int) $pdo->query($sql)->fetchColumn();

        return [
            'usersTotal'       => $one("SELECT COUNT(*) FROM {$users} WHERE deleted_at IS NULL"),
            'usersToday'       => $one("SELECT COUNT(*) FROM {$users} WHERE deleted_at IS NULL AND DATE(created_at) = CURDATE()"),
            'usersActive'      => $one("SELECT COUNT(*) FROM {$users} WHERE deleted_at IS NULL AND status = 1"),
            'waybillsTotal'    => $one("SELECT COUNT(*) FROM {$waybills} WHERE deleted_at IS NULL"),
            'waybillsToday'    => $one("SELECT COUNT(*) FROM {$waybills} WHERE deleted_at IS NULL AND DATE(created_at) = CURDATE()"),
            // 在库：已到仓 → 待出库（货物仍在仓库内）
            'packagesInStock'  => $one("SELECT COUNT(*) FROM {$packages} WHERE deleted_at IS NULL AND status BETWEEN 2 AND 9"),
            'packagesTotal'    => $one("SELECT COUNT(*) FROM {$packages} WHERE deleted_at IS NULL"),
            'ticketsPending'   => $one("SELECT COUNT(*) FROM {$tickets} WHERE status IN (0, 1)"),
            'ticketsTotal'     => $one("SELECT COUNT(*) FROM {$tickets}"),
            'warehousesTotal'  => $one("SELECT COUNT(*) FROM {$warehouses} WHERE deleted_at IS NULL"),
            'warehousesActive' => $one("SELECT COUNT(*) FROM {$warehouses} WHERE deleted_at IS NULL AND status = 1"),
            'channelsTotal'    => $one("SELECT COUNT(*) FROM {$channels} WHERE deleted_at IS NULL"),
            'channelsActive'   => $one("SELECT COUNT(*) FROM {$channels} WHERE deleted_at IS NULL AND status = 1"),
        ];
    }

    private function stats(array $c): array
    {
        return [
            ['label' => '注册用户',     'value' => $c['usersTotal'],      'icon' => '👥', 'tone' => 'blue',    'trend' => '今日 +' . $c['usersToday']],
            ['label' => '今日新增运单', 'value' => $c['waybillsToday'],   'icon' => '🧾', 'tone' => 'emerald', 'trend' => '累计 ' . $c['waybillsTotal']],
            ['label' => '待处理工单',   'value' => $c['ticketsPending'],  'icon' => '🎧', 'tone' => 'amber',   'trend' => '累计 ' . $c['ticketsTotal']],
            ['label' => '在库包裹',     'value' => $c['packagesInStock'], 'icon' => '📦', 'tone' => 'violet',  'trend' => '累计 ' . $c['packagesTotal']],
        ];
    }

    private function meters(array $c): array
    {
        return [
            ['label' => '用户正常率', 'value' => $c['usersActive'] . ' / ' . $c['usersTotal'],           'percent' => $this->percent($c['usersActive'], $c['usersTotal']),           'color' => 'blue'],
            ['label' => '仓库启用率', 'value' => $c['warehousesActive'] . ' / ' . $c['warehousesTotal'], 'percent' => $this->percent($c['warehousesActive'], $c['warehousesTotal']), 'color' => 'emerald'],
            ['label' => '渠道启用率', 'value' => $c['channelsActive'] . ' / ' . $c['channelsTotal'],     'percent' => $this->percent($c['channelsActive'], $c['channelsTotal']),     'color' => 'violet'],
        ];
    }

    private function percent(int $part, int $total): float
    {
        return $total > 0 ? round($part * 100 / $total, 1) : 0.0;
    }

    /**
     * 未开发模块不提供入口，避免用户点击后落到空页面
     */
    private function modules(): array
    {
        $modules = [
            ['key' => 'users',     'icon' => '👥', 'name' => '用户管理', 'href' => '/admin/users',     'color' => 'blue',    'perm' => 'user.view',        'desc' => '列表检索 / 状态审核 / 风控等级 / 余额调整'],
            ['key' => 'packages',  'icon' => '📦', 'name' => '包裹管理', 'href' => '/admin/packages',  'color' => 'teal',    'perm' => 'package.view',     'desc' => '全用户包裹检索 / 详情 / 异常与凭证'],
            ['key' => 'orders',    'icon' => '🧾', 'name' => '订单运单', 'href' => '/admin/orders',    'color' => 'indigo',  'perm' => 'order.view',       'desc' => '运单列表检索 / 取消与拦截'],
            ['key' => 'warehouse', 'icon' => '🏭', 'name' => '仓库管理', 'href' => '/admin/warehouse', 'color' => 'amber',   'perm' => 'warehouse.config', 'desc' => '仓库维护 / 启用停用 / 库位管理'],
            ['key' => 'channels',  'icon' => '🚛', 'name' => '物流渠道', 'href' => '/admin/channels',  'color' => 'cyan',    'perm' => 'channel.view',     'desc' => '渠道维护 / 分区报价 / 附加费'],
            ['key' => 'customs',   'icon' => '🛃', 'name' => '清关管理', 'href' => '/admin/customs',   'color' => 'violet',  'perm' => 'customs.manage',   'desc' => '报关信息列表 / 清关状态维护'],
            ['key' => 'finance',   'icon' => '💳', 'name' => '财务中心', 'href' => '/admin/finance',   'color' => 'emerald', 'perm' => 'finance.view',     'desc' => '充值单审核 / 汇率维护 / 资金流水'],
            ['key' => 'tickets',   'icon' => '🎧', 'name' => '客服工单', 'href' => '/admin/tickets',   'color' => 'rose',    'perm' => 'ticket.view',      'desc' => '工单分配 / 回复 / 关闭'],
            ['key' => 'marketing', 'icon' => '📣', 'name' => '营销内容', 'href' => '/admin/marketing', 'color' => 'fuchsia', 'perm' => 'marketing.manage', 'desc' => '公告发布 / 协议与 FAQ 内容'],
            ['key' => 'reports',   'icon' => '📊', 'name' => '数据报表', 'href' => '/admin/reports',   'color' => 'sky',     'perm' => 'report.view',      'desc' => '用户增长 / 充值 / 运单趋势'],
        ];

        foreach ($modules as &$module) {
            $module['status'] = self::MODULE_STATUS[$module['key']] ?? 'planned';
        }
        unset($module);

        return $modules;
    }
}
