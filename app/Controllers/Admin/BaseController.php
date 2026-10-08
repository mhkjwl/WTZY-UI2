<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/BaseController.php
 * @description 管理端控制器基类：员工登录校验、RBAC 权限门卫、菜单过滤、后台视图与局部表渲染
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\StaffAuth;
use App\Core\View;
use App\Models\AdminModel;
use App\Models\ClaimModel;
use App\Models\InvoiceModel;
use App\Models\PackageModel;
use App\Models\RechargeOrderModel;
use App\Models\TicketModel;
use App\Models\UserModel;
use App\Models\WaybillModel;

abstract class BaseController extends Controller
{
    /** 全部权限点（permMap 供视图做按钮级权限显隐） */
    private const PERM_CODES = [
        'user.view', 'user.edit', 'user.audit', 'user.kyc', 'user.balance.adjust', 'user.risk.set',
        'package.view',
        'order.view', 'order.manage',
        'warehouse.config', 'warehouse.report',
        'channel.view', 'channel.manage',
        'customs.manage',
        'finance.view', 'finance.manage',
        'ticket.view', 'ticket.handle',
        'claim.view', 'claim.review', 'claim.finance_audit', 'claim.admin_audit', 'claim.pay',
        'marketing.manage',
        'member.manage',
        'report.view',
        'system.manage',
        'staff.view', 'staff.manage',
        'wh.inbound', 'wh.storage', 'wh.packing', 'wh.outbound', 'wh.abnormal', 'wh.task.assign',
    ];

    protected ?array $staff = null;

    /** 当前请求内缓存的权限点（每请求读一次库，权限矩阵调整后无需重新登录即生效） */
    private ?array $permCodes = null;

    /**
     * 管理端页面一律要求「管理端」登录态；未登录或持的是仓库端会话都跳管理端登录页
     * 每请求回读员工资料：角色、仓库、状态变更后无需重新登录即生效
     */
    protected function requireStaff(): void
    {
        if (!StaffAuth::boundTo(StaffAuth::END_ADMIN)) {
            Response::redirect('/admin/login');
        }
        $session = StaffAuth::staff();
        $fresh = AdminModel::findStaff((int) ($session['id'] ?? 0));
        // 账号被删除、停用、切换为仓库端或角色停用时，立即失效并回到登录页
        if ($fresh === null || (int) $fresh['status'] !== 1 || (int) $fresh['staff_type'] !== StaffAuth::TYPE_ADMIN
            || (int) $fresh['role_status'] !== 1) {
            StaffAuth::logout();
            Response::redirect('/admin/login');
        }
        $this->staff = $fresh;
    }

    /**
     * 当前员工权限点：以数据库中的角色授权为准（登录时写入会话的快照仅作留痕，不再作为判据）
     * 这样在「角色权限」里调整后，相关员工下一次请求即按新权限渲染菜单与按钮
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
     * RBAC 权限判断：超级管理员跳过检查（角色取自本次请求回读的资料，改角色即时生效）
     */
    protected function can(string $permCode): bool
    {
        if ((string) ($this->staff['role_code'] ?? '') === 'super_admin') {
            return true;
        }
        return in_array($permCode, $this->permissions(), true);
    }

    /**
     * 权限门卫：无权限时接口返回 403 JSON，页面渲染 403 视图
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
        View::render('admin/denied', [
            'title'       => '无权限访问',
            'pageTitle'   => '无权限访问',
            'menu'        => $this->visibleMenu(),
            'active'      => '',
            'staff'       => $this->staff ?? [],
            'logoutUrl'   => '/admin/logout',
            'todoUrl'     => '/admin/todos',
            'perms'       => [],
        ], 'layouts/admin');
        exit;
    }

    /**
     * 侧边栏菜单定义（perm=null 顶部分组外项；badge_key 对应待办计数，为 0 时不显示）
     */
    protected function menu(): array
    {
        return [
            ['group' => null,      'key' => 'dashboard', 'label' => '控制台',   'icon' => '🏠', 'href' => '/admin',           'perm' => null],
            ['group' => '业务管理', 'key' => 'users',     'label' => '用户管理', 'icon' => '👥', 'href' => '/admin/users',     'perm' => 'user.view'],
            ['group' => '业务管理', 'key' => 'kyc',       'label' => '实名审核', 'icon' => '🪪', 'href' => '/admin/users/kyc', 'perm' => 'user.kyc', 'badge_key' => 'kyc'],
            ['group' => '业务管理', 'key' => 'packages',  'label' => '包裹管理', 'icon' => '📦', 'href' => '/admin/packages',  'perm' => 'package.view', 'badge_key' => 'packages'],
            ['group' => '业务管理', 'key' => 'orders',    'label' => '订单运单', 'icon' => '🧾', 'href' => '/admin/orders',    'perm' => 'order.view', 'badge_key' => 'orders'],
            ['group' => '业务管理', 'key' => 'warehouse', 'label' => '仓库管理', 'icon' => '🏭', 'href' => '/admin/warehouse', 'perm' => 'warehouse.config'],
            ['group' => '业务管理', 'key' => 'warehouse_reports', 'label' => '仓库报表', 'icon' => '📈', 'href' => '/admin/warehouse/reports', 'perm' => 'warehouse.report'],
            ['group' => '业务管理', 'key' => 'channels',  'label' => '物流渠道', 'icon' => '🚛', 'href' => '/admin/channels',  'perm' => 'channel.view'],
            ['group' => '业务管理', 'key' => 'rates',     'label' => '转运资费', 'icon' => '💰', 'href' => '/admin/rates',     'perm' => 'channel.manage'],
            ['group' => '业务管理', 'key' => 'services',  'label' => '增值服务', 'icon' => '✨', 'href' => '/admin/services',  'perm' => 'channel.manage'],
            ['group' => '业务管理', 'key' => 'customs',   'label' => '清关管理', 'icon' => '🛃', 'href' => '/admin/customs',   'perm' => 'customs.manage', 'badge_key' => 'customs'],
            ['group' => '运营支撑', 'key' => 'finance',   'label' => '财务中心', 'icon' => '💳', 'href' => '/admin/finance',   'perm' => 'finance.view', 'badge_key' => 'finance'],
            ['group' => '运营支撑', 'key' => 'recharge',  'label' => '充值设置', 'icon' => '🧾', 'href' => '/admin/finance/recharge-settings', 'perm' => 'finance.manage'],
            ['group' => '运营支撑', 'key' => 'invoices',  'label' => '发票管理', 'icon' => '🧮', 'href' => '/admin/finance/invoices', 'perm' => 'finance.view', 'badge_key' => 'invoices'],
            ['group' => '运营支撑', 'key' => 'tickets',   'label' => '客服工单', 'icon' => '🎧', 'href' => '/admin/tickets',   'perm' => 'ticket.view', 'badge_key' => 'tickets'],
            ['group' => '运营支撑', 'key' => 'claims',    'label' => '理赔处理', 'icon' => '🛡️', 'href' => '/admin/claims',    'perm' => 'claim.view', 'badge_key' => 'claims'],
            ['group' => '运营支撑', 'key' => 'marketing', 'label' => '营销内容', 'icon' => '📣', 'href' => '/admin/marketing', 'perm' => 'marketing.manage'],
            ['group' => '运营支撑', 'key' => 'reports',   'label' => '数据报表', 'icon' => '📊', 'href' => '/admin/reports',   'perm' => 'report.view'],
            ['group' => '会员与营销', 'key' => 'levels',   'label' => '会员等级', 'icon' => '👑', 'href' => '/admin/member/levels',  'perm' => 'member.manage'],
            ['group' => '会员与营销', 'key' => 'coupons',  'label' => '优惠券',   'icon' => '🎫', 'href' => '/admin/member/coupons', 'perm' => 'member.manage'],
            ['group' => '会员与营销', 'key' => 'invite',   'label' => '邀请设置', 'icon' => '🎁', 'href' => '/admin/member/invite',  'perm' => 'member.manage'],
            ['group' => '系统',     'key' => 'system_site',     'label' => '基本信息', 'icon' => '🏷️', 'href' => '/admin/system/site',     'perm' => 'system.manage'],
            ['group' => '系统',     'key' => 'system_mail',     'label' => '邮箱设置', 'icon' => '✉️', 'href' => '/admin/system/mail',     'perm' => 'system.manage'],
            ['group' => '系统',     'key' => 'system_tasks',    'label' => '自动任务', 'icon' => '⏱️', 'href' => '/admin/system/tasks',    'perm' => 'system.manage'],
            ['group' => '系统',     'key' => 'system_staff',    'label' => '员工管理', 'icon' => '👤', 'href' => '/admin/system/staff',    'perm' => 'staff.view'],
            ['group' => '系统',     'key' => 'system_roles',    'label' => '角色权限', 'icon' => '🔑', 'href' => '/admin/system/roles',    'perm' => 'system.manage'],
        ];
    }

    /**
     * 各导航项的待办数量（需要人工处理的量，处理完自动归零）
     * @return array<string, int>
     */
    protected function todoCounts(): array
    {
        $kyc = UserModel::kycCounts();
        $recharge = RechargeOrderModel::statusCounts();
        $tickets = TicketModel::statusCounts();
        $claims = ClaimModel::adminCounts();
        return [
            // 待审核的实名申请
            'kyc'      => (int) ($kyc[1] ?? 0),
            // 未处理的包裹异常与处置申请
            'packages' => PackageModel::pendingAbnormalCount(),
            // 已出库、等待录入轨迹并推进运输段的运单
            'orders'   => WaybillModel::pendingAdvanceCount(),
            // 已进入运输/清关段、清关状态未到终态的运单
            'customs'  => WaybillModel::pendingClearCount(),
            // 待支付 + 待入账的充值单（口径见 RechargeOrderModel::PENDING_STATUSES）
            'finance'  => RechargeOrderModel::pendingCount($recharge),
            // 待受理 + 处理中的工单
            'tickets'  => (int) ($tickets[0] ?? 0) + (int) ($tickets[1] ?? 0),
            // 待开票的发票申请
            'invoices' => (int) (InvoiceModel::statusCounts()[InvoiceModel::PENDING] ?? 0),
            // 理赔全流程待推进：待受理/处理中/待财务审核/待管理员终审/待赔付（3 已驳回、4 已赔付为终态）
            'claims'   => (int) ($claims[0] ?? 0) + (int) ($claims[1] ?? 0) + (int) ($claims[2] ?? 0)
                          + (int) ($claims[5] ?? 0) + (int) ($claims[6] ?? 0),
        ];
    }

    /**
     * 把待办数量并入菜单项（badge_key 对应见 menu()）
     */
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
     * 按当前员工权限过滤后的菜单（无权限的模块不出现在侧边栏），并附带待办数量
     */
    protected function visibleMenu(): array
    {
        $menu = array_values(array_filter($this->menu(), fn(array $item): bool => $item['perm'] === null || $this->can($item['perm'])));
        return $this->withBadges($menu);
    }

    /**
     * 侧边栏菜单片段：保存权限矩阵后局部替换，无需整页刷新即可看到菜单变化
     */
    public function nav(): never
    {
        $this->requireStaff();
        View::render('partials/side_nav', [
            'menu'   => $this->visibleMenu(),
            'active' => (string) Request::query('active', ''),
        ], null);
        exit;
    }

    /**
     * 待办数量接口：供前端在操作后/定时刷新侧边栏徽标
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
     * 当前员工全部权限点布尔映射（视图按钮级显隐）
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
     * 后台页面统一渲染：注入菜单/权限/员工等公共数据
     */
    protected function adminView(string $template, array $data, string $active, string $pageTitle): never
    {
        $data['menu'] = $this->visibleMenu();
        $data['active'] = $active;
        $data['staff'] = $this->staff ?? [];
        $data['logoutUrl'] = '/admin/logout';
        $data['perms'] = $this->permMap();
        $data['isSuper'] = StaffAuth::isSuperAdmin();
        $data['pageTitle'] = $pageTitle;
        $data['todoUrl'] = '/admin/todos';
        $this->view($template, $data, 'layouts/admin');
    }

    /**
     * 局部表渲染：列表检索/分页/操作后仅替换数据区（规范 §2.5）
     */
    protected function renderTable(string $template, array $data): never
    {
        $data['csrf'] = $this->csrfToken();
        $data['perms'] = $this->permMap();
        $data['isSuper'] = StaffAuth::isSuperAdmin();
        View::render($template, $data, null);
        exit;
    }

    /**
     * 分页参数
     */
    protected function pageParams(): array
    {
        $page = max(1, (int) Request::query('page', '1'));
        return ['page' => $page, 'offset' => ($page - 1) * 15, 'limit' => 15];
    }
}
