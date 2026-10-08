<?php declare(strict_types=1);
/**
 * @file app/Views/partials/home_head.php
 * @description 官网外壳共用：头部 meta、站点标题与专属样式（首页与转运资费页共用）
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 *
 * 品牌与 SEO 取自后台「站点信息」；页面可传 pageTitle 作为二级标题
 */

use App\Core\View;
use App\Services\BrandAsset;
use App\Services\SiteChrome;

$brand = $brand ?? SiteChrome::brand();
$keywords = $keywords ?? SiteChrome::keywords();
$description = $description ?? SiteChrome::description();
$pageTitle = $pageTitle ?? '';
$pageCss = $pageCss ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= View::e($pageTitle !== '' ? $pageTitle . ' · ' . $brand : $brand . ' — 国际转运 · 跨境物流一站式服务') ?></title>
<meta name="description" content="<?= View::e($description) ?>">
<?php if ($keywords !== ''): ?><meta name="keywords" content="<?= View::e($keywords) ?>"><?php endif; ?>
<?php if (($faviconTag = BrandAsset::faviconTag()) !== ''): ?><?= $faviconTag ?><?php else: ?><link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8C%8D%3C/text%3E%3C/svg%3E"><?php endif; ?>
<link rel="stylesheet" href="<?= View::asset('/assets/common/home.css') ?>">
<?php if (($pageCss ?? '') !== ''): ?><link rel="stylesheet" href="<?= View::asset($pageCss) ?>"><?php endif; ?>
<script>document.documentElement.classList.add('js')</script>

</head>
<body>

