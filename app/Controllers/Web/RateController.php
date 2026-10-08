<?php declare(strict_types=1);
/**
 * @file app/Controllers/Web/RateController.php
 * @description 前台转运资费页：按仓库分组展示线路资费与增值服务（内容来自后台「转运资费」）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Services\RateContent;

final class RateController extends Controller
{
    /**
     * 资费页与首页共用同一套外壳（自带页头页脚与样式），故不套用系统站点布局
     * 品牌、SEO、导航与登录入口由 partials/home_* 经 SiteChrome 注入，此处只传页面内容
     */
    public function index(): never
    {
        $groups = RateContent::routesByWarehouse();
        $routeCount = 0;
        foreach ($groups as $group) {
            $routeCount += count($group['routes']);
        }
        $this->view('web/rates', [
            'title'      => '转运资费',
            'groups'     => $groups,
            'services'   => RateContent::services(),
            'routeCount' => $routeCount,
        ], null);
    }
}
