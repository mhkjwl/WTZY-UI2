<?php declare(strict_types=1);
/**
 * @file app/Controllers/Warehouse/BaseController.php
 * @description 仓库端控制器基类：员工登录校验、仓库归属隔离、wh.* 权限门卫、菜单与待办徽标
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Controllers\Warehouse;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\StaffAuth;
use App\Core\View;
use App\Models\AdminModel;
use App\Models\PackageModel;
use App\Models\WarehouseModel;
use App\Models\WarehouseTaskModel;
use App\Models\WaybillModel;

abstract class BaseController extends Controller
{
    /** 仓库端权限点（permMap 供视图做按钮级显隐） */
    private const PERM_CODES = [
        'wh.inbound', 'wh.storage', 'wh.packing', 'wh.outbound', 'wh.abnormal', 'wh.task.assign',
    ];

    protected ?array $staff = null;

    /** 所属仓库名称（布局顶栏展示） */
    protected string $warehouseName = '';

    /** 当前请求内缓存的权限点（每请求读一次库，权限调整后无需重新登录即生效） */
    private ?array $permCodes = null;

    /**
     * 仓库端页面一律要求「仓库端」登录态；未登录或持的是管理端会话都跳仓库端登录页
     * 每请求回读员工资料：角色、所属仓库、状态变更后无需重新登录即生效
     */
    protected function requireStaff(): void
    {
        if (!StaffAuth::boundTo(StaffAuth::END_WAREHOUSE)) {
            Response::redirect('/warehouse/login');
        }
        $session = StaffAuth::staff();
        $fresh = AdminModel::findStaff((int) ($session['id'] ?? 0));
        // 账号被删除、停用、改为管理端、角色停用或未分配仓库时，立即失效并回到登录页
        if ($fresh === null || (int) $fresh['status'] !== 1 || (int) $fresh['staff_type'] !== StaffAuth::TYPE_WAREHOUSE
            || (int) $fresh['role_status'] !== 1 || (int) $fresh['warehouse_id'] <= 0) {
            StaffAuth::logout();
            Response::redirect('/warehouse/login');
        }
        $this->staff = $fresh;
        $warehouse = WarehouseModel::findById($this->warehouseId());
        $this->warehouseName = $warehouse === null ? '未分配仓库' : (string) $warehouse['name'];
    }

    /**
     * 仓库端员工必须归属具体仓库，作业数据按仓库隔离
     */
    protected function warehouseId(): int
    {
        return (int) ($this->staff['warehouse_id'] ?? 0);
    }

    protected function adminId(): int
    {
        return (int) ($this->staff['id'] ?? 0);
    }

    /**
     * 当前员工权限点（以数据库中的角色授权为准，权限矩阵调整后无需重新登录）
     */
    protected function permissions(): array
    {
        if ($this->permCodes === null) {
            $roleId = (int) ($this->staff['role_id'] ?? 0);
            $this->permCodes = $roleId > 0 ? AdminModel::permissionCodesForRole($roleId) : [];
        }
        return $this->permCodes;
    }

    /**
     * RBAC 权限判断（仓库端不使用超级管理员直通，权限由角色矩阵决定）
     */
    protected function can(string $permCode): bool
    {
        return in_array($permCode, $this->permissions(), true);
    }

    /**
     * 权限门卫：接口 403 JSON，页面渲染 403 视图
     */
    protected function enforce(string $permCode): void
    {
        if ($this->can($permCode)) {
            return;
        }
        if (Request::method() !== 'GET') {
            $this->fail('无权限执行此操作（缺少权限点 ' . $permCode . '）', 403);
        }
        http_response_code(403);
        View::render('warehouse/denied', [
            'title'       => '无权限访问',
            'pageTitle'   => '无权限访问',
            'menu'        => $this->visibleMenu(),
            'active'      => '',
            'staff'       => $this->staff ?? [],
            'warehouseName' => $this->warehouseName,
            'logoutUrl'   => '/warehouse/logout',
            'todoUrl'     => '/warehouse/todos',
            'perms'       => [],
        ], 'layouts/warehouse');
        exit;
    }

    /**
     * 侧边栏菜单（badge_key 为 0 时不显示徽标）
     */
    protected function menu(): array
    {
        return $this->withBadges([
            ['group' => null,     'key' => 'dashboard', 'label' => '作业台',   'icon' => '🏠', 'href' => '/warehouse',           'perm' => null, 'badge_key' => 'mine'],
            ['group' => '收件入库', 'key' => 'inbound',   'label' => '收货入库', 'icon' => '📥', 'href' => '/warehouse/inbound',   'perm' => 'wh.inbound', 'badge_key' => 'inbound'],
            ['group' => '收件入库', 'key' => 'storage',   'label' => '仓储管理', 'icon' => '🏗️', 'href' => '/warehouse/storage',   'perm' => 'wh.storage', 'badge_key' => 'unattributed'],
            ['group' => '出库作业', 'key' => 'packing',   'label' => '打包称重', 'icon' => '📦', 'href' => '/warehouse/packing',   'perm' => 'wh.packing', 'badge_key' => 'packing'],
            ['group' => '出库作业', 'key' => 'outbound',  'label' => '出库交接', 'icon' => '🚚', 'href' => '/warehouse/outbound',  'perm' => 'wh.outbound', 'badge_key' => 'outbound'],
            ['group' => '异常与调度', 'key' => 'abnormal', 'label' => '异常处理', 'icon' => '⚠️', 'href' => '/warehouse/abnormal', 'perm' => 'wh.abnormal', 'badge_key' => 'abnormal'],
            ['group' => '异常与调度', 'key' => 'tasks',    'label' => '任务分配', 'icon' => '🗂️', 'href' => '/warehouse/tasks',    'perm' => 'wh.task.assign', 'badge_key' => 'tasks'],
        ]);
    }

    /**
     * 各导航项待办数量（作业做完自动归零）
     * @return array<string, int>
     */
    protected function todoCounts(): array
    {
        $warehouseId = $this->warehouseId();
        $pending = WaybillModel::warehousePendingCounts($warehouseId);
        $taskCounts = WarehouseTaskModel::counts($warehouseId);
        return [
            // 指派给我且未完成的作业任务
            'mine'         => WarehouseTaskModel::mineCount($warehouseId, $this->adminId()),
            // 已预报待收货
            'inbound'      => PackageModel::pendingInboundCount($warehouseId),
            // 无主件等待确认归属
            'unattributed' => (int) (PackageModel::warehouseCounts($warehouseId)[3] ?? 0),
            'packing'      => (int) $pending['packing'],
            'outbound'     => (int) $pending['outbound'],
            'abnormal'     => PackageModel::warehouseAbnormalCount($warehouseId),
            // 未完成的任务总数（主管视角）
            'tasks'        => (int) ($taskCounts[0] ?? 0) + (int) ($taskCounts[1] ?? 0),
        ];
    }

    private function withBadges(array $menu): array
    {
        $counts = $this->todoCounts();
        foreach ($menu as &$item) {
            $key = $item['badge_key'] ?? null;
            $item['badge'] = $key === null ? 0 : (int) ($counts[$key] ?? 0);
        }
        unset($item);
        return $menu;
    }

    /**
     * 按权限过滤后的菜单
     */
    protected function visibleMenu(): array
    {
        return array_values(array_filter(
            $this->menu(),
            fn(array $item): bool => $item['perm'] === null || $this->can($item['perm'])
        ));
    }

    /**
     * 待办数量接口：供前端操作后/定时刷新侧边栏徽标
     */
    public function todos(): never
    {
        $this->requireStaff();
        $counts = [];
        foreach ($this->visibleMenu() as $item) {
            if (isset($item['badge_key'])) {
                $counts[$item['badge_key']] = (int) $item['badge'];
            }
        }
        $this->ok($counts);
    }

    /**
     * 当前员工权限布尔映射（视图按钮显隐）
     */
    protected function permMap(): array
    {
        $map = [];
        foreach (self::PERM_CODES as $code) {
            $map[$code] = $this->can($code);
        }
        return $map;
    }

    /**
     * 仓库端页面统一渲染
     */
    protected function warehouseView(string $template, array $data, string $active, string $pageTitle): never
    {
        $data['menu'] = $this->visibleMenu();
        $data['active'] = $active;
        $data['staff'] = $this->staff ?? [];
        $data['warehouseName'] = $this->warehouseName;
        $data['logoutUrl'] = '/warehouse/logout';
        $data['perms'] = $this->permMap();
        $data['pageTitle'] = $pageTitle;
        $data['todoUrl'] = '/warehouse/todos';
        $this->view($template, $data, 'layouts/warehouse');
    }

    /**
     * 局部表渲染：检索/分页/操作后仅替换数据区（规范 §2.5）
     */
    protected function renderTable(string $template, array $data): never
    {
        $data['csrf'] = $this->csrfToken();
        $data['perms'] = $this->permMap();
        View::render($template, $data, null);
        exit;
    }

    protected function isPartial(): bool
    {
        return Request::header('X-Requested-With') === 'fetch';
    }

    protected function pageParams(int $size = 15): array
    {
        $page = max(1, (int) Request::query('page', '1'));
        return ['page' => $page, 'offset' => ($page - 1) * $size, 'limit' => $size];
    }

    /**
     * 作业留痕：写员工操作日志（该表此前未启用，仓库作业审计用）
     */
    protected function auditLog(string $module, string $action, ?int $targetId, array $detail = [], string $targetType = 'package'): void
    {
        try {
            $stmt = \App\Core\Database::pdo()->prepare(
                'INSERT INTO ' . \App\Core\Database::table('admin_operation_logs') . '
                    (admin_id, admin_name, module, action, target_type, target_id, after_data, ip)
                 VALUES (:aid, :name, :module, :action, :ttype, :tid, :after, :ip)'
            );
            $stmt->execute([
                ':aid'    => $this->adminId(),
                ':name'   => mb_substr((string) ($this->staff['real_name'] ?? ''), 0, 30),
                ':module' => $module,
                ':action' => $action,
                ':ttype'  => $targetType,
                ':tid'    => $targetId,
                ':after'  => $detail === [] ? null : json_encode($detail, JSON_UNESCAPED_UNICODE),
                ':ip'     => Request::ip(),
            ]);
        } catch (\Throwable) {
            // 审计失败不阻断作业主流程
        }
    }
}
