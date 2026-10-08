<?php declare(strict_types=1);
/**
 * @file app/Views/partials/home_footer.php
 * @description 官网外壳共用：页脚（品牌/联系方式/备案/订阅）与浮动按钮
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

use App\Core\View;
use App\Services\BrandAsset;
use App\Services\SiteChrome;

$brand = $brand ?? SiteChrome::brand();
$contact = $contact ?? SiteChrome::contact();
?>
<!-- ============ Footer ============ -->
<footer id="contact">
  <div class="container">
    <div class="ft-top">
      <div class="ft-brand">
        <a class="logo" href="#home">
          <?php if (($brandLogo = BrandAsset::logoUrl()) !== ''): ?>
          <img class="logo-img" src="<?= View::e($brandLogo) ?>" alt="<?= View::e($brand) ?>">
          <?php else: ?>
          <span class="logo-mark"><svg><use href="#i-globe"/></svg></span>
          <?php endif; ?>
          <span class="logo-name" style="color:#fff"><?= View::e($brand) ?></span>
        </a>
        <p>国家商务部批准的一级国际货运代理企业，专注国际转运与跨境物流 12 年。以科技驱动供应链，让中国好货通达世界。</p>
        <div class="ft-social">
          <a href="#contact">微信</a><a href="#contact">微博</a><a href="#contact">抖音</a><a href="#contact">小红书</a><a href="#contact">领英</a>
        </div>
      </div>
      <div class="ft-col">
        <h4>转运服务</h4>
        <ul>
          <li><a href="#services">国际空运</a></li>
          <li><a href="#services">国际海运</a></li>
          <li><a href="#services">国际快递</a></li>
          <li><a href="#services">FBA 头程</a></li>
          <li><a href="#services">海外仓储</a></li>
          <li><a href="#services">退货换标</a></li>
        </ul>
      </div>
      <div class="ft-col">
        <h4>帮助支持</h4>
        <ul>
          <li><a href="#process">新手指南</a></li>
          <li><a href="#faq">禁运品说明</a></li>
          <li><a href="#faq">保险与理赔</a></li>
          <li><a href="#faq">理赔政策</a></li>
          <li><a href="#network">时效查询</a></li>
          <li><a href="#plus">增值服务</a></li>
          <li><a href="/rates">转运资费</a></li>
        </ul>
      </div>
      <div class="ft-col">
        <h4>联系我们</h4>
        <ul class="ft-contact">
          <?php if ($contact['phone'] !== ''): ?>
          <li><svg><use href="#i-phone"/></svg>服务热线：<?= View::e($contact['phone']) ?><?= $contact['hours'] !== '' ? '（' . View::e($contact['hours']) . '）' : '' ?></li>
          <?php endif; ?>
          <?php if ($contact['email'] !== ''): ?>
          <li><svg><use href="#i-mail"/></svg><?= View::e($contact['email']) ?></li>
          <?php endif; ?>
          <?php if ($contact['address'] !== ''): ?>
          <li><svg><use href="#i-pin"/></svg><?= View::e($contact['address']) ?></li>
          <?php endif; ?>
        </ul>
        <div class="newsletter">
          <input type="email" id="nl-email" placeholder="填写邮箱，注册后接收物流资讯" aria-label="邮箱">
          <button type="button" onclick="subscribeNews()">订阅</button>
        </div>
        <p class="nl-tip" id="nl-tip">物流资讯与公告会通过站内信与邮箱推送，需先注册会员。</p>
      </div>
    </div>
    <div class="ft-bottom">
      <span>© <span id="year">2026</span><?= $contact['company'] !== '' ? ' ' . View::e($contact['company']) : '' ?><?= $contact['icp'] !== '' ? ' · ' . View::e($contact['icp']) : '' ?></span>
      <div class="ft-badges"><span>VISA</span><span>Mastercard</span><span>支付宝</span><span>微信支付</span><span>PayPal</span></div>
    </div>
  </div>
</footer>

<!-- ============ 浮动按钮 ============ -->
<div class="float-btns">
  <a class="fbtn fb-chat" href="#contact" aria-label="在线客服"><svg><use href="#i-chat"/></svg></a>
  <a class="fbtn fb-top" id="backTop" href="#home" aria-label="回到顶部"><svg><use href="#i-up"/></svg></a>
</div>

