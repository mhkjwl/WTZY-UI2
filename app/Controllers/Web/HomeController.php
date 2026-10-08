<?php declare(strict_types=1);
/**
 * @file app/Controllers/Web/HomeController.php
 * @description 转运官网首页：品牌展示与合作入口
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Services\HomeContent;
use App\Services\SiteChrome;

final class HomeController extends Controller
{
    /** 统计卡图标：按顺序循环取值（数量与顺序由设计稿固定） */
    private const STAT_ICONS = ['i-globe', 'i-box', 'i-warehouse', 'i-award'];

    /**
     * 首页自带上、下页头页脚与专属样式（独立外壳），故不套用系统站点布局，
     * 注入后台可配的品牌、SEO、联系方式、官网数字与当前登录态
     */
    public function index(): never
    {
        $this->view('web/home', [
            'title'       => SiteChrome::brand(),
            'brand'       => SiteChrome::brand(),
            'keywords'    => SiteChrome::keywords(),
            'description' => SiteChrome::description(),
            'contact'     => SiteChrome::contact(),
            'identity'    => SiteChrome::identity(),
            'stats'       => HomeContent::stats(),
            'promises'    => HomeContent::promises(),
            'faqs'        => HomeContent::faqs(),
            'storageDays' => HomeContent::storageDays(),
            'statIcons'   => self::STAT_ICONS,
        ], null);
    }
}
