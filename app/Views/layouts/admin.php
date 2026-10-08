<?php declare(strict_types=1);
/**
 * @file app/Views/layouts/admin.php
 * @description 管理后台布局（光年 v5 外壳）：左侧导航（分组折叠由模板脚本接管）+ 顶栏（菜单开关 / 页面标题 / 账号菜单）+ 内容区；
 *              未迁移到新版的页面继续加载旧样式表，保证迁移期间逐批切换、页面观感不劣化
 * @author AI
 * @version 0.6
 * @date 2026-10-08
 */

use App\Core\View;
use App\Services\SiteChrome;

/**
 * @var string $title     页面标题
 * @var string $pageTitle 顶栏标题
 * @var array  $menu      侧边栏菜单（含 group 分组标记）
 * @var string $active    当前高亮菜单 key
 * @var array  $staff     当前登录员工
 * @var string $logoutUrl 退出地址
 * @var string $content   模板内容
 * @var string $csrf      CSRF 令牌
 * @var string $__view    当前视图模板名（由 View::render 注入）
 */
// 品牌名统一取后台「站点信息 → 网站名称」，控制器可传 $brand 覆盖
$brand = $brand ?? SiteChrome::brand();
$uiMigrated = View::isMigrated($__view ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= View::e($csrf ?? '') ?>">
    <title><?= View::e($title ?? '管理后台') ?> · <?= View::e($brand) ?></title>
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
                <a href="/admin" class="sidebar-brand">
                    <span class="sidebar-brand__mark">🌍</span>
                    <span class="sidebar-brand__text">
                        <b><?= View::e($brand) ?></b>
                        <small>管理后台</small>
                    </span>
                </a>
            </div>
            <div class="lyear-layout-sidebar-info lyear-scroll">
                <nav class="sidebar-main" id="side-nav" data-side="admin">
                    <?= View::partial('partials/side_nav', ['menu' => $menu ?? [], 'active' => $active ?? '']) ?>
                </nav>
                <div class="sidebar-footer">
                    <p class="copyright">v<?= View::e(APP_VERSION ?? '') ?> · 管理后台</p>
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
                    <span class="navbar-title"><?= View::e($pageTitle ?? $title ?? '控制台') ?></span>
                </div>
                <ul class="navbar-right d-flex align-items-center">
                    <li class="dropdown">
                        <a href="javascript:void(0)" data-bs-toggle="dropdown" class="dropdown-toggle">
                            <span class="avatar-md rounded-circle bg-primary avatar-text"><?= View::e(mb_substr((string) ($staff['real_name'] ?? '员'), 0, 1)) ?></span>
                            <span class="navbar-user"><?= View::e($staff['real_name'] ?? '') ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text text-muted small"><?= View::e($staff['role_name'] ?? '管理员') ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="javascript:void(0)"
                                   onclick="logoutConfirm('<?= View::e($logoutUrl ?? '/admin/logout') ?>')">
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