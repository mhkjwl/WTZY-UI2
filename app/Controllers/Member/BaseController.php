<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/BaseController.php
 * @description 会员中心控制器基类：登录守卫、侧边栏菜单、后台同款视图与局部表渲染
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

namespace App\Controllers\Member;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\UserAuth;
use App\Core\View;
use App\Models\ClaimModel;
use App\Models\MemberLevelModel;
use App\Models\MessageModel;
use App\Models\PackageModel;
use App\Models\SettingsModel;
use App\Models\TicketModel;
use App\Models\UserModel;
use App\Models\WaybillModel;

abstract class BaseController extends Controller
{
    protected ?array $user = null;

    /** 强制实名开启且本人尚未通过认证：业务功能全部受限 */
    protected bool $kycLocked = false;

    /** 发票申请开关（设置项 invoice_enabled）：关闭后隐藏发票入口并拒绝新申请 */
    protected bool $invoiceEnabled = true;

    /**
     * 会员中心一律要求登录且账号可用（正常状态），未登录或账号被后台停用都回到登录页
     */
    protected function requireLogin(): void
    {
        // 账号可用性判定（禁用/黑名单/注销）由 UserAuth 以数据库状态为准统一裁决，
        // 会话中缓存的资料可能过期（改名/升级/积分变动），因此同样复用权威数据
        $fresh = UserAuth::fresh();
        if ($fresh === null) {
            $message = UserAuth::notice() ?? '登录状态已失效，请重新登录';
            // 接口与写操作返回 JSON，由前端提示后引导回登录页，避免把登录页塞进局部刷新区域
            if ($this->isPartial() || Request::method() !== 'GET') {
                Response::json(['success' => false, 'message' => $message, 'data' => ['redirect' => '/user/login']], 403);
            }
            Response::redirect('/user/login');
        }
        $fresh['level_name'] = MemberLevelModel::nameFor((int) $fresh['member_level']);
        // 展示名统一用用户名（未设用户名时回落用户编号），昵称非必填不参与展示
        $fresh['display_name'] = UserModel::displayName($fresh);
        $this->user = $fresh;
        $this->kycLocked = SettingsModel::get('realname_required', '0') === '1'
            && (int) $fresh['kyc_status'] !== 2;
        $this->invoiceEnabled = SettingsModel::get('invoice_enabled', '1') === '1';
        if ($this->kycLocked) {
            $this->enforceRealnameGate();
        }
        // 鉴权与会话读取已完成，释放会话锁排开同账号并发请求（局部刷新、轮询、多标签页）
        $this->closeSession();
    }

    /**
     * 强制实名闸门（后端接口鉴权）：未实名用户仅保留个人资料（含绑定邮箱）、
     * 实名认证与退出登录；其余业务页 GET 重定向到实名认证页，写操作返回 403
     */
    private function enforceRealnameGate(): void
    {
        $path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
        // 公告查看属被动阅读，不属业务操作，强制实名期间同样放行
        $allowed = $path === '/user'
            || str_starts_with($path, '/user/profile')
            || str_starts_with($path, '/user/kyc')
            || str_starts_with($path, '/user/logout')
            || str_starts_with($path, '/user/todos')
            || str_starts_with($path, '/user/notices');
        if ($allowed) {
            return;
        }
        $isAjax = Request::header('X-Requested-With') === 'fetch' || $_SERVER['REQUEST_METHOD'] !== 'GET';
        if ($isAjax) {
            Response::json(['success' => false, 'message' => '请先完成实名认证', 'data' => ['redirect' => '/user/kyc']], 403);
            exit;
        }
        Response::redirect('/user/kyc?locked=1');
    }

    protected function userId(): int
    {
        return (int) ($this->user['id'] ?? 0);
    }

    /**
     * 侧边栏菜单（badge_key 对应待办计数，为 0 时不显示）；
     * 强制实名受限期间只保留工作台入口与资料/实名两项，业务入口全部隐藏
     */
    protected function menu(): array
    {
        $menu = [
            ['group' => null,        'key' => 'dashboard', 'label' => '工作台',     'icon' => '🏠', 'href' => '/user'],
            // 账户设置置于顶部，便于快速进入资料与地址维护
            ['group' => '账户设置',   'key' => 'addresses', 'label' => '海外仓地址', 'icon' => '🏬', 'href' => '/user/addresses'],
            ['group' => '账户设置',   'key' => 'shipping',  'label' => '收货地址',   'icon' => '📍', 'href' => '/user/shipping'],
            ['group' => '账户设置',   'key' => 'profile',   'label' => '个人资料',   'icon' => '👤', 'href' => '/user/profile'],
            ['group' => '账户设置',   'key' => 'kyc',       'label' => '实名认证',   'icon' => '🪪', 'href' => '/user/kyc'],
            ['group' => '我的包裹',   'key' => 'forecast',  'label' => '包裹预报',   'icon' => '📝', 'href' => '/user/forecast'],
            ['group' => '我的包裹',   'key' => 'packages',  'label' => '我的包裹',   'icon' => '📦', 'href' => '/user/packages', 'badge_key' => 'packages'],
            ['group' => '我的包裹',   'key' => 'abnormal',  'label' => '异常件处理', 'icon' => '⚠️', 'href' => '/user/packages/abnormal', 'badge_key' => 'abnormal'],
            ['group' => '转运与物流', 'key' => 'create',    'label' => '新建转运',   'icon' => '➕', 'href' => '/user/waybills/create'],
            ['group' => '转运与物流', 'key' => 'waybills',  'label' => '转运订单',   'icon' => '🧾', 'href' => '/user/waybills', 'badge_key' => 'waybill_due'],
            ['group' => '转运与物流', 'key' => 'tracking',  'label' => '物流跟踪',   'icon' => '🛰️', 'href' => '/user/tracking'],
            ['group' => '财务中心',   'key' => 'finance',   'label' => '余额与充值', 'icon' => '💰', 'href' => '/user/finance'],
            ['group' => '财务中心',   'key' => 'flows',     'label' => '资金流水',   'icon' => '📄', 'href' => '/user/finance/flows'],
            ['group' => '财务中心',   'key' => 'invoices',  'label' => '发票管理',   'icon' => '🧮', 'href' => '/user/finance/invoices'],
            ['group' => '会员与营销', 'key' => 'member',    'label' => '会员权益',   'icon' => '👑', 'href' => '/user/member'],
            ['group' => '会员与营销', 'key' => 'coupons',   'label' => '我的优惠券', 'icon' => '🎫', 'href' => '/user/member/coupons'],
            ['group' => '会员与营销', 'key' => 'points',    'label' => '积分明细',   'icon' => '⭐', 'href' => '/user/member/points'],
            ['group' => '会员与营销', 'key' => 'invite',    'label' => '邀请返利',   'icon' => '🎁', 'href' => '/user/member/invite'],
            ['group' => '客服与售后', 'key' => 'tickets',   'label' => '客服工单',   'icon' => '🎧', 'href' => '/user/tickets', 'badge_key' => 'tickets'],
            ['group' => '客服与售后', 'key' => 'claims',    'label' => '理赔申请',   'icon' => '🛡️', 'href' => '/user/claims', 'badge_key' => 'claims'],
            ['group' => '客服与售后', 'key' => 'notices',   'label' => '网站公告',   'icon' => '📢', 'href' => '/user/notices'],
            ['group' => '客服与售后', 'key' => 'messages',  'label' => '消息中心',   'icon' => '📩', 'href' => '/user/messages', 'badge_key' => 'messages'],
        ];
        if ($this->kycLocked) {
            // 强制实名受限期间保留公告入口：公告属被动阅读，方便用户了解平台信息
            $menu = array_values(array_filter(
                $menu,
                static fn(array $item): bool => in_array($item['key'], ['dashboard', 'profile', 'kyc', 'notices'], true)
            ));
        }
        if (!$this->invoiceEnabled) {
            // 后台关闭开票功能后，用户端不再出现发票入口（直接访问该页同样只显示关闭说明）
            $menu = array_values(array_filter(
                $menu,
                static fn(array $item): bool => $item['key'] !== 'invoices'
            ));
        }
        return $this->withBadges($menu);
    }

    /**
     * 各导航项的待办数量（需要用户本人处理的量，处理完自动归零）
     * @return array<string, int>
     */
    protected function todoCounts(): array
    {
        $userId = $this->userId();
        $packages = PackageModel::statusCounts($userId);
        $tickets = TicketModel::userStatusCounts($userId);
        $claims = ClaimModel::counts($userId);
        return [
            // 等待本人处理的包裹：已到仓(2)/待认领(3) 需认领，已认领(4) 需提交转运
            'packages' => (int) ($packages[2] ?? 0) + (int) ($packages[3] ?? 0) + (int) ($packages[4] ?? 0),
            // 待处理的包裹异常与处置结果
            'abnormal' => PackageModel::pendingAbnormalCount($userId),
            // 客服已回复、等待本人查看
            'tickets'  => (int) ($tickets[2] ?? 0),
            // 理赔待本人跟进（待受理/处理中）
            'claims'   => (int) ($claims[0] ?? 0) + (int) ($claims[1] ?? 0),
            'messages' => MessageModel::unreadCount($userId),
            // 打包重算后需补缴差额的运单，结清前仓库不会出库
            'waybill_due' => WaybillModel::pendingPaymentCount($userId),
        ];
    }

    /**
     * 把待办数量并入菜单项
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
     * 待办数量接口：供前端在操作后/定时刷新侧边栏徽标
     */
    public function todos(): never
    {
        $this->requireLogin();
        $counts = [];
        foreach ($this->menu() as $item) {
            if (isset($item['badge_key'])) {
                $counts[$item['badge_key']] = (int) $item['badge'];
            }
        }
        $this->ok($counts);
    }

    /**
     * 会员中心页面统一渲染
     */
    protected function memberView(string $template, array $data, string $active, string $pageTitle): never
    {
        $data['menu'] = $this->menu();
        $data['active'] = $active;
        $data['user'] = $this->user;
        $data['pageTitle'] = $pageTitle;
        $data['todoUrl'] = '/user/todos';
        $data['kycLocked'] = $this->kycLocked;
        $this->view($template, $data, 'layouts/member');
    }

    /**
     * 局部表渲染：检索/分页/操作后仅替换数据区（规范 §2.5）
     */
    protected function renderTable(string $template, array $data): never
    {
        $data['csrf'] = $this->csrfToken();
        View::render($template, $data, null);
        exit;
    }

    /**
     * 是否为局部刷新请求
     */
    protected function isPartial(): bool
    {
        return Request::header('X-Requested-With') === 'fetch';
    }

    /**
     * 分页参数
     */
    protected function pageParams(int $size = 15): array
    {
        $page = max(1, (int) Request::query('page', '1'));
        return ['page' => $page, 'offset' => ($page - 1) * $size, 'limit' => $size];
    }
}
