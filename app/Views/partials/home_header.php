<?php declare(strict_types=1);
/**
 * @file app/Views/partials/home_header.php
 * @description 官网外壳共用：顶栏、主导航与移动端菜单（品牌与联系方式取自后台配置）
 * @author AI
 * @version 0.6.1
 * @date 2026-10-09
 */

use App\Core\View;
use App\Services\BrandAsset;
use App\Services\SiteChrome;

$brand = $brand ?? SiteChrome::brand();
$contact = $contact ?? SiteChrome::contact();
$identity = $identity ?? SiteChrome::identity();
?>
<div id="progressBar"></div>

<!-- ============ Header ============ -->
<header id="header">
  <div id="topbar">
    <div class="container">
      <div class="tb-left">
        <?php if ($contact['phone'] !== ''): ?>
        <span class="tb-item"><svg><use href="#i-phone"/></svg>服务热线：<?= View::e($contact['phone']) ?></span>
        <?php endif; ?>
        <?php if ($contact['email'] !== ''): ?>
        <span class="tb-item"><svg><use href="#i-mail"/></svg><?= View::e($contact['email']) ?></span>
        <?php endif; ?>
        <?php if ($contact['hours'] !== ''): ?>
        <span class="tb-item"><svg><use href="#i-clock"/></svg><?= View::e($contact['hours']) ?></span>
        <?php endif; ?>
      </div>
      <div class="tb-right">
        <a href="#plus">增值服务</a>
        <span class="tb-divider"></span>
        <a href="#faq">帮助中心</a>
        <span class="tb-divider"></span>
        <a href="#cta">中文（简体） ▾</a>
      </div>
    </div>
  </div>
  <div class="container">
    <nav id="nav">
      <a class="logo" href="#home">
        <?php if (($brandLogo = BrandAsset::logoUrl()) !== ''): ?>
        <img class="logo-img" src="<?= View::e($brandLogo) ?>" alt="<?= View::e($brand) ?>">
        <?php else: ?>
        <span class="logo-mark"><svg><use href="#i-globe"/></svg></span>
        <?php endif; ?>
        <span class="logo-name"><?= View::e($brand) ?></span>
      </a>
      <ul class="nav-links">
        <li class="nav-item"><a class="nav-link" href="#home">首页</a></li>
        <li class="nav-item">
          <a class="nav-link" href="#services">服务体系 <svg><use href="#i-chev"/></svg></a>
          <div class="dropdown">
            <a href="#services"><span class="dd-icon"><svg><use href="#i-plane"/></svg></span><span><b>国际空运</b><span>全球主要机场 3-7 日直达</span></span></a>
            <a href="#services"><span class="dd-icon"><svg><use href="#i-anchor"/></svg></span><span><b>国际海运</b><span>整柜 / 拼箱，经济之选</span></span></a>
            <a href="#services"><span class="dd-icon"><svg><use href="#i-bolt"/></svg></span><span><b>国际快递</b><span>DHL / FedEx / UPS 代理价</span></span></a>
            <a href="#services"><span class="dd-icon"><svg><use href="#i-warehouse"/></svg></span><span><b>海外仓储</b><span>40+ 海外仓，一件代发</span></span></a>
            <a href="#services"><span class="dd-icon"><svg><use href="#i-box"/></svg></span><span><b>集运合箱</b><span>免费合箱 · 质检 · 打包</span></span></a>
            <a href="#services"><span class="dd-icon"><svg><use href="#i-filecheck"/></svg></span><span><b>清关报关</b><span>专业关务，极速放行</span></span></a>
          </div>
        </li>
        <li class="nav-item"><a class="nav-link" href="#network">全球网络</a></li>
        <li class="nav-item"><a class="nav-link" href="#process">服务流程</a></li>
        <li class="nav-item"><a class="nav-link" href="#plus">增值服务</a></li>
        <li class="nav-item"><a class="nav-link" href="/rates">转运资费</a></li>
        <li class="nav-item"><a class="nav-link" href="#solutions">解决方案</a></li>
        <li class="nav-item"><a class="nav-link" href="#faq">常见问题</a></li>
      </ul>
      <div class="nav-actions">
        <?php if ($identity['logout'] !== null): ?>
          <?php /* 已登录只占两个位（与设计一致），退出入口在会员中心内 */ ?>
          <a class="nav-login" href="/user/messages"><?= View::e($identity['entries'][0]['label'] ?? '消息') ?></a>
          <a class="btn btn-gold btn-sm" href="/user">会员中心</a>
        <?php else: ?>
          <a class="nav-login" href="/user/login">登录</a>
          <a class="btn btn-gold btn-sm" href="/user/register">免费注册</a>
        <?php endif; ?>
        <button id="navToggle" aria-label="打开菜单"><svg><use href="#i-menu"/></svg></button>
      </div>
    </nav>
  </div>
  <div class="mobile-menu">
    <a href="#home">首页</a>
    <a href="#services">服务体系</a>
    <a href="#network">全球网络</a>
    <a href="#process">服务流程</a>
    <a href="#plus">增值服务</a>
    <a href="/rates">转运资费</a>
    <a href="#solutions">解决方案</a>
    <a href="#faq">常见问题</a>
    <?php if ($identity['logout'] !== null): ?>
      <a href="/user">会员中心</a>
      <a href="/user/messages">消息</a>
      <a href="<?= View::e($identity['logout']) ?>">退出</a>
    <?php else: ?>
      <a href="/user/login">登录</a>
      <a class="btn btn-gold" href="/user/register">免费注册</a>
    <?php endif; ?>
  </div>
</header>

