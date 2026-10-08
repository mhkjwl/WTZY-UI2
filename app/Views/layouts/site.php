<?php declare(strict_types=1);
/**
 * @file app/Views/layouts/site.php
 * @description 转运官网布局：顶部导航（按登录身份显示入口）+ 内容区 + 页脚
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

use App\Core\Request;
use App\Core\View;
use App\Services\BrandAsset;
use App\Services\SiteChrome;

/**
 * @var string $title
 * @var string $content
 * @var string $csrf
 * @var string|null $appName
 * @var string|null $keywords
 * @var string|null $description
 * @var array|null  $nav       官网锚点菜单
 * @var array|null  $identity  导航右侧身份入口
 * @var string|null $pageTitle 内容页标题（登录/注册等页面的卡片标题，用于导航高亮）
 */
// 未显式传入时回落到站点配置，使任意页面都能直接套用官网外壳
$appName = $appName ?? SiteChrome::brand();
$keywords = $keywords ?? SiteChrome::keywords();
$description = $description ?? SiteChrome::description();
$nav = $nav ?? SiteChrome::nav();
$identity = $identity ?? SiteChrome::identity();

// 首页内用纯锚点平滑滚动，其它页面跳回首页对应区块
$isHome = Request::path() === '/';
$anchorHref = static function (string $anchor) use ($isHome): string {
    if ($anchor === '') {
        return $isHome ? '#top' : '/';
    }
    return $isHome ? '#' . $anchor : '/#' . $anchor;
};
// 已迁移到新版界面的页面（如认证页）追加新版样式：旧样式在前保证官网外壳不变，
// 新版样式在后接管新组件类名，二者按页面共存、互不干扰
$uiMigrated = View::isMigrated($__view ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= View::e($csrf ?? '') ?>">
    <?php if (($keywords ?? '') !== ''): ?>
        <meta name="keywords" content="<?= View::e($keywords) ?>">
    <?php endif; ?>
    <?php if (($description ?? '') !== ''): ?>
        <meta name="description" content="<?= View::e($description) ?>">
    <?php endif; ?>
    <title><?= View::e($title ?? $appName) ?></title>
    <?= BrandAsset::faviconTag() ?>
    <link rel="stylesheet" href="<?= View::asset('/assets/common/common.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/common/site.css') ?>">
    <?php if ($uiMigrated): ?>
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/materialdesignicons.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/animate.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/style.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/ui/theme.css') ?>">
    <?php endif; ?>
</head>
<body class="site-body<?= $uiMigrated ? ' ui-new' : '' ?>">
<header class="site-header" id="top">
    <div class="site-header__inner">
        <a class="site-brand" href="/">
            <span class="site-brand__logo">🌍</span>
            <span class="site-brand__text">
                <b><?= View::e($appName) ?></b>
                <small>全球转运 · 海外仓一站式服务</small>
            </span>
        </a>

        <nav class="site-nav">
            <?php foreach ($nav as $item): ?>
                <?php
                // href：站内绝对路径；anchor：首页内锚点（首页内平滑滚动，其它页面跳回首页对应区块）
                $href = isset($item['href']) ? $item['href'] : $anchorHref((string) ($item['anchor'] ?? ''));
                ?>
                <a class="site-nav__link" href="<?= View::e($href) ?>"><?= View::e($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="site-actions">
            <?php if ($identity['logout'] !== null): ?>
                <span class="site-user">
                    <b><?= View::e($identity['who']) ?></b>
                    <?php if ($identity['role'] !== ''): ?><em><?= View::e($identity['role']) ?></em><?php endif; ?>
                </span>
                <?php foreach ($identity['entries'] as $entry): ?>
                    <a class="btn btn--ghost btn--sm" href="<?= View::e($entry['href']) ?>"><?= View::e($entry['label']) ?></a>
                <?php endforeach; ?>
                <button type="button" class="btn btn--primary btn--sm"
                        onclick="logoutConfirm('<?= View::e($identity['logout']) ?>')">退出</button>
            <?php else: ?>
                <a class="btn btn--ghost btn--sm" href="/user/login">登录</a>
                <a class="btn btn--primary btn--sm" href="/user/register">免费注册</a>
            <?php endif; ?>
        </div>

        <button class="site-nav-toggle" type="button" onclick="toggleSiteNav()" aria-label="展开导航">☰</button>
    </div>
</header>

<main class="site-main">
    <?= $content ?? '' ?>
</main>

<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__brand">
            <span class="site-brand__logo">🌍</span>
            <div>
                <b><?= View::e($appName) ?></b>
                <p><?= View::e($description !== '' ? $description : '海外仓收货、包裹集运与全球转运一站式服务') ?></p>
            </div>
        </div>
        <div class="site-footer__cols">
            <div>
                <h4>服务</h4>
                <a href="<?= View::e($anchorHref('services')) ?>">服务体系</a>
                <a href="<?= View::e($anchorHref('network')) ?>">全球网络</a>
                <a href="<?= View::e($anchorHref('process')) ?>">服务流程</a>
            </div>
            <div>
                <h4>增值与方案</h4>
                <a href="<?= View::e($anchorHref('plus')) ?>">增值服务</a>
                <a href="<?= View::e($anchorHref('solutions')) ?>">解决方案</a>
                <a href="<?= View::e($anchorHref('faq')) ?>">常见问题</a>
            </div>
            <div>
                <h4>账户</h4>
                <a href="/user/login">登录</a>
                <a href="/user/register">注册</a>
                <a href="/user/forgot">找回密码</a>
                <a href="/user/messages">消息中心</a>
            </div>
        </div>
    </div>
    <div class="site-footer__bottom">
        <span>© <?= date('Y') ?> <?= View::e($appName) ?> · 保留所有权利</span>
        <span>v<?= View::e(APP_VERSION ?? '') ?></span>
    </div>
</footer>

<div id="notify-root" class="notify-root" aria-live="polite"></div>
<script src="<?= View::asset('/assets/common/toast.js') ?>"></script>
<script src="<?= View::asset('/assets/common/confirm.js') ?>"></script>
<script src="<?= View::asset('/assets/common/app.js') ?>"></script>
<script>
function toggleSiteNav() {
    document.querySelector('.site-nav').classList.toggle('is-open');
}
// 锚点滚动：平滑定位到对应区块，不做整页跳转
document.querySelectorAll('.site-nav__link[href^="#"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
        var target = document.querySelector(this.getAttribute('href'));
        if (!target) { return; }
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.querySelector('.site-nav').classList.remove('is-open');
    });
});
</script>
</body>
</html>
