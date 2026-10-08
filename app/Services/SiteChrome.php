<?php declare(strict_types=1);
/**
 * @file app/Services/SiteChrome.php
 * @description 官网外壳数据：品牌、SEO 信息、锚点菜单与按身份显示的导航入口
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

namespace App\Services;

use App\Core\Request;
use App\Core\UserAuth;
use App\Models\MessageModel;
use App\Models\SettingsModel;

final class SiteChrome
{
    public static function brand(): string
    {
        return SettingsModel::get('site_name', '全球转运系统');
    }

    public static function keywords(): string
    {
        return SettingsModel::get('site_keywords', '');
    }

    public static function description(): string
    {
        $text = SettingsModel::get('site_description', '');
        return $text !== '' ? $text : '海外仓收货、包裹集运与全球转运一站式服务';
    }

    /**
     * 官网菜单：anchor 为首页内的区块锚点，href 为站内绝对路径
     * 「会员中心」按登录状态决定去向，且已登录并身处会员中心时不再显示（避免当前页自链接）
     * 锚点须与首页区块 id 一一对应（见 app/Views/web/home.php）
     */
    public static function nav(): array
    {
        $nav = [
            ['label' => '首页', 'anchor' => ''],
            ['label' => '服务体系', 'anchor' => 'services'],
            ['label' => '全球网络', 'anchor' => 'network'],
            ['label' => '服务流程', 'anchor' => 'process'],
            ['label' => '增值服务', 'anchor' => 'plus'],
            ['label' => '转运资费', 'href' => '/rates'],
            ['label' => '解决方案', 'anchor' => 'solutions'],
            ['label' => '常见问题', 'anchor' => 'faq'],
        ];

        // 未登录始终保留入口（点击引导登录）；已登录仅在会员中心之外显示
        $insideMemberCenter = UserAuth::check() && str_starts_with(Request::path(), '/user');
        if (!$insideMemberCenter) {
            $nav[] = ['label' => '会员中心', 'href' => self::memberCenterUrl()];
        }
        return $nav;
    }

    /**
     * 联系方式与备案信息（后台「站点信息」可配置），页头、页脚与官网各页共用
     * @return array{phone: string, email: string, address: string, hours: string, icp: string, company: string}
     */
    public static function contact(): array
    {
        return [
            'phone'   => SettingsModel::get('site_phone', ''),
            'email'   => SettingsModel::get('site_email', ''),
            'address' => SettingsModel::get('site_address', ''),
            'hours'   => SettingsModel::get('site_hours', ''),
            'icp'     => SettingsModel::get('site_icp', ''),
            'company' => SettingsModel::get('site_company', ''),
        ];
    }

    /**
     * 会员中心入口：已登录直达用户后台，未登录先引导登录
     */
    public static function memberCenterUrl(): string
    {
        return UserAuth::check() ? '/user' : '/user/login';
    }

    /**
     * 导航右侧入口：只认用户端身份，后台入口不出现在官网（前后台隔离）
     * @return array{who: string, role: string, entries: array, logout: string|null}
     */
    public static function identity(): array
    {
        if (!UserAuth::check()) {
            return ['who' => '', 'role' => '', 'entries' => [], 'logout' => null];
        }
        $user = UserAuth::user() ?? [];
        $unread = MessageModel::unreadCount((int) ($user['id'] ?? 0));
        return [
            'who'     => (string) ($user['nickname'] ?? ''),
            'role'    => '用户',
            // 会员中心已作为主导航入口，此处只放快捷入口，避免重复
            'entries' => [
                ['label' => $unread > 0 ? "消息（{$unread}）" : '消息', 'href' => '/user/messages'],
            ],
            'logout'  => '/user/logout',
        ];
    }
}
