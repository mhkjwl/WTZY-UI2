<?php declare(strict_types=1);
/**
 * @file app/Views/layouts/base.php
 * @description 全站公共布局：顶栏导航 + 内容区 + 全局通知容器 + CSRF meta
 * @author AI
 * @version 0.6
 * @date 2026-10-08
 */

use App\Core\View;
use App\Services\SiteChrome;

/**
 * @var string $title  页面标题
 * @var array  $nav    顶部导航 [['label','href','active']]
 * @var string $content 模板渲染内容
 * @var string $csrf   CSRF 令牌
 */
// 品牌名统一取后台「站点信息 → 网站名称」，控制器可传 $brand 覆盖
$brand = $brand ?? SiteChrome::brand();
// 已迁移到新版界面的页面（如支付结果页）追加新版样式：旧样式在前保证顶栏外壳不变，
// 新版样式在后接管新组件类名，二者按页面共存、互不干扰
$uiMigrated = View::isMigrated($__view ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= View::e($csrf ?? '') ?>">
    <title><?= View::e($title ?? $brand) ?></title>
    <link rel="stylesheet" href="<?= View::asset('/assets/common/common.css') ?>">
    <?php if ($uiMigrated): ?>
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/materialdesignicons.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/animate.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/style.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/ui/theme.css') ?>">
    <?php endif; ?>
</head>
<body class="<?= $uiMigrated ? 'ui-new' : '' ?>">
<header class="topbar">
    <a class="topbar__brand" href="/">🌍 <?= View::e($brand) ?></a>
    <nav class="topbar__nav">
        <?php foreach (($nav ?? []) as $item): ?>
            <a class="topbar__link<?= !empty($item['active']) ? ' is-active' : '' ?>"
               href="<?= View::e($item['href']) ?>"><?= View::e($item['label']) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if (!empty($logoutUrl)): ?>
        <button type="button" class="btn btn--ghost btn--sm"
                onclick="logoutConfirm('<?= View::e($logoutUrl) ?>')">退出登录</button>
    <?php endif; ?>
    <span class="topbar__version"><?= View::e(APP_VERSION ?? '') ?></span>
</header>
<main class="container">
    <?= $content ?? '' ?>
</main>
<div id="notify-root" class="notify-root" aria-live="polite"></div>
<script src="<?= View::asset('/assets/common/toast.js') ?>"></script>
<script src="<?= View::asset('/assets/common/confirm.js') ?>"></script>
<script src="<?= View::asset('/assets/common/app.js') ?>"></script>
</body>
</html>
