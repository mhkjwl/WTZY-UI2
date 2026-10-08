<?php declare(strict_types=1);
/**
 * @file app/Views/layouts/member.php
 * @description 会员中心布局（光年 v5 外壳）：与管理端同款结构，顶栏账号菜单内含会员等级与返回官网；
 *              已迁移页面加载新版样式，未迁移页面保持旧观感
 * @author AI
 * @version 0.6.1
 * @date 2026-10-09
 */

use App\Core\View;
use App\Services\BrandAsset;
use App\Services\SiteChrome;

/**
 * @var string $title     页面标题
 * @var string $pageTitle 顶栏标题
 * @var array  $menu      侧边栏菜单（含 group 分组标记）
 * @var string $active    当前高亮菜单 key
 * @var array  $user      当前登录用户
 * @var string $content   模板内容
 * @var string $csrf      CSRF 令牌
 * @var string $__view    当前视图模板名（由 View::render 注入）
 */
// 品牌名统一取后台「站点信息 → 网站名称」，控制器可传 $brand 覆盖
$brand = $brand ?? SiteChrome::brand();
$uiMigrated = View::isMigrated($__view ?? '');
$memberName = (string) ($user['display_name'] ?? $user['user_no'] ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= View::e($csrf ?? '') ?>">
    <title><?= View::e($title ?? '会员中心') ?> · <?= View::e($brand) ?></title>
    <?= BrandAsset::faviconTag() ?>
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/materialdesignicons.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/animate.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/style.min.css') ?>">
    <?php if (!$uiMigrated): ?>
    <link rel="stylesheet" href="<?= View::asset('/assets/common/common.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/common/admin.css') ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= View::asset('/assets/ui/theme.css') ?>">
</head>
<body class="<?= $uiMigrated ? 'ui-new' : '' ?>" data-todo-url="<?= View::e($todoUrl ?? '') ?>">
<div class="lyear-layout-web">
    <div class="lyear-layout-container">
        <aside class="lyear-layout-sidebar">
            <div id="logo" class="sidebar-header">
                <a href="/user" class="sidebar-brand">
                    <?php if (($brandLogo = BrandAsset::logoUrl()) !== ''): ?>
                    <img class="sidebar-brand__logo" src="<?= View::e($brandLogo) ?>" alt="<?= View::e($brand) ?>">
                    <?php else: ?>
                    <span class="sidebar-brand__mark">📦</span>
                    <?php endif; ?>
                    <span class="sidebar-brand__text">
                        <b><?= View::e($brand) ?></b>
                        <small>会员中心</small>
                    </span>
                </a>
            </div>
            <div class="lyear-layout-sidebar-info lyear-scroll">
                <nav class="sidebar-main" id="side-nav" data-side="member">
                    <?= View::partial('partials/side_nav', ['menu' => $menu ?? [], 'active' => $active ?? '']) ?>
                </nav>
                <div class="sidebar-footer">
                    <p class="copyright">v<?= View::e(APP_VERSION ?? '') ?> · 会员中心</p>
                </div>
            </div>
        </aside>

        <header class="lyear-layout-header">
            <nav class="navbar">
                <div class="navbar-left">
                    <div class="lyear-aside-toggler">
                        <span class="lyear-toggler-bar"></span>
                        <span class="lyear-toggler-bar"></span>
                        <span class="lyear-toggler-bar"></span>
                    </div>
                    <span class="navbar-title"><?= View::e($pageTitle ?? $title ?? '会员中心') ?></span>
                </div>
                <ul class="navbar-right d-flex align-items-center">
                    <li class="dropdown">
                        <a href="javascript:void(0)" data-bs-toggle="dropdown" class="dropdown-toggle">
                            <span class="avatar-md rounded-circle bg-primary avatar-text"><?= View::e(mb_substr($memberName !== '' ? $memberName : '会', 0, 1)) ?></span>
                            <span class="navbar-user"><?= View::e($memberName) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text text-muted small">Lv<?= (int) ($user['member_level'] ?? 1) ?> <?= View::e($user['level_name'] ?? '') ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/"><i class="mdi mdi-home-outline"></i><span>返回官网</span></a></li>
                            <li>
                                <a class="dropdown-item" href="javascript:void(0)" onclick="logoutConfirm('/user/logout')">
                                    <i class="mdi mdi-logout-variant"></i><span>退出登录</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </header>

        <main class="lyear-layout-content">
            <div class="container-fluid">
                <?php if (!empty($kycLocked) && ($active ?? '') !== 'kyc'): ?>
                <div class="alert alert-warning d-flex align-items-center gap-2 flex-wrap" role="alert">
                    <i class="mdi mdi-alert-outline"></i>
                    <div class="flex-grow-1">
                        <b>请先完成实名认证</b>
                        <div class="small">按平台要求，完成实名认证前仅可维护个人资料与实名信息。认证通过后即可使用全部功能。</div>
                    </div>
                    <a class="btn btn-warning btn-sm" href="/user/kyc">前往实名认证</a>
                </div>
                <?php endif; ?>
                <?= $content ?? '' ?>
            </div>
        </main>
    </div>
</div>
<div id="notify-root" class="notify-root" aria-live="polite"></div>
<script src="<?= View::asset('/assets/lyear/js/jquery.min.js') ?>"></script>
<script src="<?= View::asset('/assets/lyear/js/popper.min.js') ?>"></script>
<script src="<?= View::asset('/assets/lyear/js/bootstrap.min.js') ?>"></script>
<script src="<?= View::asset('/assets/lyear/js/perfect-scrollbar.min.js') ?>"></script>
<script src="<?= View::asset('/assets/lyear/js/jquery.cookie.min.js') ?>"></script>
<script src="<?= View::asset('/assets/lyear/js/main.min.js') ?>"></script>
<script src="<?= View::asset('/assets/common/toast.js') ?>"></script>
<script src="<?= View::asset('/assets/common/confirm.js') ?>"></script>
<script src="<?= View::asset('/assets/common/app.js') ?>"></script>
<script src="<?= View::asset('/assets/ui/shell.js') ?>"></script>
</body>
</html>