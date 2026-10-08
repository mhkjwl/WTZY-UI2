<?php declare(strict_types=1);
/**
 * @file app/Views/web/home.php
 * @description 官网首页（独立页面外壳：自带页头/页脚与专属样式，与系统其余页面样式隔离）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 *
 * 品牌名、SEO 与联系方式均来自后台「系统 → 站点信息」；
 * 页头右侧按登录状态显示入口；正文各区块为静态营销内容，
 * 标注「可后续接后台」的区块可按对应数据源改为动态渲染。
 */

use App\Core\View;
use App\Services\HomeContent;

/**
 * @var string $brand       网站名称（后台可配）
 * @var string $keywords    网站关键词（后台可配，留空则不输出 meta）
 * @var string $description 网站介绍（后台可配）
 * @var array  $contact     联系方式：phone/email/address/hours/icp/company
 * @var array  $identity    登录态：who/entries/logout（未登录时 logout 为 null）
 * @var array  $stats       首页统计数字（后台可配，默认见 HomeContent::STATS_DEFAULT）
 * @var array  $promises    首页服务承诺数字（后台可配）
 * @var array  $faqs        常见问题（取后台「营销内容 → 常见问题」）
 * @var array  $statIcons   统计卡图标（按顺序循环取值）
 */
?>
<?= View::partial('partials/home_head') ?>
<?= View::partial('partials/home_sprite') ?>
<?= View::partial('partials/home_header') ?>
<main>
<!-- ============ Hero ============ -->
<section class="hero" id="home">
  <div class="hero-orb o1"></div><div class="hero-orb o2"></div><div class="hero-orb o3"></div>
  <div class="container">
    <div class="hero-grid">
      <div class="hero-copy">
        <span class="chip hero-chip reveal"><span class="dot"></span>专注&nbsp;<span class="rotor"><i>国际空运</i><i>国际海运</i><i>国际快递</i><i>海外仓储</i></span>&nbsp;· 一站式跨境物流</span>
        <h1 class="reveal d1">货通全球<br>让每一次跨越 <span class="grad-text">快人一步</span></h1>
        <p class="hero-sub reveal d2">整合国际空运、海运、快递三大干线资源，为跨境电商与海淘用户提供仓储集运、国际转运、清关派送一体化解决方案，覆盖 220+ 国家与地区，门到门直达。</p>
        <div class="hero-actions reveal d3">
          <a class="btn btn-primary" href="#cta"><svg class="ic"><use href="#i-headset"/></svg>免费咨询顾问</a>
          <a class="btn btn-ghost" href="#process">查看服务流程</a>
        </div>
        <p class="hero-note reveal d3"><svg><use href="#i-check"/></svg>1 对 1 专属顾问，最快 30 分钟内响应</p>
        <div class="hero-trust reveal d4">
          <div class="avatars">
            <span class="av-1">张</span><span class="av-2">李</span><span class="av-3">王</span><span class="av-4">陈</span><span class="av-5">+</span>
          </div>
          <div class="trust-txt">
            <b><span class="stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></span>4.9 / 5.0 好评率</b>
            128,000+ 跨境卖家与海淘用户的共同选择
          </div>
        </div>
        <div class="hero-stats reveal d5">
          <?php /* Hero 数字条：取统计数字前三项，与下方统计卡同源 */ ?>
          <?php foreach (array_slice($stats, 0, 3) as $hs): ?>
          <?php $hdec = HomeContent::decimals((string) $hs['value']); ?>
          <div class="hstat"><b><span class="count" data-count="<?= View::e((string) $hs['value']) ?>"<?= $hdec > 0 ? ' data-decimals="' . $hdec . '"' : '' ?>>0</span><em><?= View::e((string) $hs['unit']) ?></em></b><span><?= View::e((string) $hs['label']) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="hero-visual reveal d2">
        <div class="hv-ring"></div>
        <svg class="hv-plane-path" viewBox="0 0 600 560" fill="none">
          <path class="trail" d="M80 420 C 180 200, 400 120, 540 150"/>
          <path id="flyPath" class="fly" d="M80 420 C 180 200, 400 120, 540 150"/>
          <circle r="5" fill="#38BDF8">
            <animateMotion dur="7s" repeatCount="indefinite" rotate="auto"><mpath href="#flyPath"/></animateMotion>
          </circle>
          <circle r="12" fill="rgba(56,189,248,.25)">
            <animateMotion dur="7s" repeatCount="indefinite"><mpath href="#flyPath"/></animateMotion>
          </circle>
        </svg>
        <div class="hv-card">
          <div class="hv-head">
            <div><b>跨境干线 · 实时时效</b><br><span>SZX → LAX 今日航线动态</span></div>
            <span class="status-pill"><span class="dot"></span>准点率 99.2%</span>
          </div>
          <div class="hv-route">
            <div class="hv-city"><b>深圳</b><span>SZX</span></div>
            <div class="hv-line"><svg><use href="#i-plane"/></svg></div>
            <div class="hv-city"><b>洛杉矶</b><span>LAX</span></div>
          </div>
          <div class="hv-steps">
            <div class="hv-step done"><span class="hv-dot"></span>揽收入库</div>
            <div class="hv-step done"><span class="hv-dot"></span>干线运输</div>
            <div class="hv-step active"><span class="hv-dot"></span>清关中</div>
            <div class="hv-step"><span class="hv-dot"></span>末端派送</div>
          </div>
          <div class="hv-progress"><i></i></div>
          <div class="hv-meta"><span>平均清关：<b>8 小时</b></span><span>门到门时效：<b>3-5 天</b></span></div>
        </div>
        <div class="hv-chip c1">
          <span class="ci"><svg><use href="#i-plane"/></svg></span>
          <span>每日货运直飞<small>SZX → ANC → LAX 干线航线</small></span>
        </div>
        <div class="hv-chip c2">
          <span class="ci"><svg><use href="#i-shield"/></svg></span>
          <span>清关白名单通道<small>平均 8 小时极速放行</small></span>
        </div>
        <div class="hv-chip c3">
          <span class="ci"><svg><use href="#i-clock"/></svg></span>
          <span>99.2% 准点达成<small>近 30 天干线时效统计</small></span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ 合作平台 ============ -->
<!-- 信任背书：静态内容。可后续接后台「合作平台/客户」管理 -->
<section class="logos">
  <div class="container">
    <p class="logos-label">受到全球领先电商平台与物流伙伴的信赖</p>
    <div class="marquee">
      <div class="marquee-track">
        <span class="logo-item"><svg><use href="#i-cart"/></svg>Amazon FBA</span>
        <span class="logo-item"><svg><use href="#i-globe"/></svg>Shopify</span>
        <span class="logo-item"><svg><use href="#i-box"/></svg>eBay</span>
        <span class="logo-item"><svg><use href="#i-truck"/></svg>DHL</span>
        <span class="logo-item"><svg><use href="#i-plane"/></svg>FedEx</span>
        <span class="logo-item"><svg><use href="#i-bolt"/></svg>UPS</span>
        <span class="logo-item"><svg><use href="#i-warehouse"/></svg>AliExpress</span>
        <span class="logo-item"><svg><use href="#i-anchor"/></svg>Shein</span>
        <span class="logo-item"><svg><use href="#i-users"/></svg>TikTok Shop</span>
        <span class="logo-item"><svg><use href="#i-award"/></svg>Temu</span>
        <span class="logo-item"><svg><use href="#i-cart"/></svg>Amazon FBA</span>
        <span class="logo-item"><svg><use href="#i-globe"/></svg>Shopify</span>
        <span class="logo-item"><svg><use href="#i-box"/></svg>eBay</span>
        <span class="logo-item"><svg><use href="#i-truck"/></svg>DHL</span>
        <span class="logo-item"><svg><use href="#i-plane"/></svg>FedEx</span>
        <span class="logo-item"><svg><use href="#i-bolt"/></svg>UPS</span>
        <span class="logo-item"><svg><use href="#i-warehouse"/></svg>AliExpress</span>
        <span class="logo-item"><svg><use href="#i-anchor"/></svg>Shein</span>
        <span class="logo-item"><svg><use href="#i-users"/></svg>TikTok Shop</span>
        <span class="logo-item"><svg><use href="#i-award"/></svg>Temu</span>
      </div>
    </div>
  </div>
</section>

<!-- ============ 数据统计 ============ -->
<!-- 统计数字：静态写死（覆盖国家数、时效、仓库数等）。可后续接真实业务统计 -->
<section class="stats sec">
  <div class="container">
    <div class="stats-grid">
      <?php /* 统计数字：后台「站点信息 → 官网首页数字」可改；图标按顺序固定取值 */ ?>
      <?php foreach ($stats as $i => $s): ?>
      <?php $dec = HomeContent::decimals((string) $s['value']); ?>
      <div class="stat-card reveal<?= $i > 0 ? ' d' . $i : '' ?>"><div class="stat-ic"><svg><use href="#<?= $statIcons[$i % 4] ?>"/></svg></div><b><span class="count" data-count="<?= View::e((string) $s['value']) ?>"<?= $dec > 0 ? ' data-decimals="' . $dec . '"' : '' ?>>0</span><em><?= View::e((string) $s['unit']) ?></em></b><p><?= View::e((string) $s['label']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============ 服务体系 ============ -->
<!-- 服务项目：静态内容。可后续接后台「物流渠道」分类渲染 -->
<section class="sec" id="services">
  <span class="sec-mark" style="top:30px;right:-40px">SERVICE</span>
  <div class="container">
    <div class="sec-head">
      <span class="eyebrow"><span class="en">OUR SERVICES</span>· 服务体系</span>
      <h2 class="sec-title">全链路物流服务，<span class="grad-text">一站解决</span></h2>
      <p class="sec-sub">从揽收、仓储、合箱到清关、派送，每一个环节都有专业团队为你的包裹保驾护航。</p>
    </div>
    <div class="services-grid">
      <div class="svc-card reveal">
        <div class="svc-top"><span class="svc-icon g1"><svg><use href="#i-plane"/></svg></span><span class="svc-num">01</span></div>
        <h3>国际空运</h3><div class="svc-en">AIR FREIGHT</div>
        <p>对接全球主要航空枢纽，每日多班货运航班，急货专线 48 小时起飞。</p>
        <div class="svc-tags"><i>3-7 日达</i><i>每日航班</i><i>急货专线</i></div>
        <a class="svc-link" href="#plus">了解更多 <svg><use href="#i-arrow"/></svg></a>
      </div>
      <div class="svc-card reveal d1">
        <div class="svc-top"><span class="svc-icon g2"><svg><use href="#i-anchor"/></svg></span><span class="svc-num">02</span></div>
        <h3>国际海运</h3><div class="svc-en">OCEAN FREIGHT</div>
        <p>整柜 FCL / 拼箱 LBC 灵活选择，美森快船、以星快船限时达航线。</p>
        <div class="svc-tags"><i>25-35 日达</i><i>美森快船</i><i>双清包税</i></div>
        <a class="svc-link" href="#plus">了解更多 <svg><use href="#i-arrow"/></svg></a>
      </div>
      <div class="svc-card reveal d2">
        <div class="svc-top"><span class="svc-icon g3"><svg><use href="#i-bolt"/></svg></span><span class="svc-num">03</span></div>
        <h3>国际快递</h3><div class="svc-en">EXPRESS</div>
        <p>DHL、FedEx、UPS 官方合作渠道，全球 48 小时上门取件，旺季舱位有保障。</p>
        <div class="svc-tags"><i>2-5 日达</i><i>官方合作</i><i>旺季保障</i></div>
        <a class="svc-link" href="#plus">了解更多 <svg><use href="#i-arrow"/></svg></a>
      </div>
      <div class="svc-card reveal d3">
        <div class="svc-top"><span class="svc-icon g4"><svg><use href="#i-warehouse"/></svg></span><span class="svc-num">04</span></div>
        <h3>海外仓储</h3><div class="svc-en">OVERSEAS WAREHOUSE</div>
        <p>美、欧、日、澳等 40+ 自营海外仓，一件代发、退换标、换仓调拨。</p>
        <div class="svc-tags"><i>一件代发</i><i>退货换标</i><i>头程入仓</i></div>
        <a class="svc-link" href="#plus">了解更多 <svg><use href="#i-arrow"/></svg></a>
      </div>
      <div class="svc-card reveal">
        <div class="svc-top"><span class="svc-icon g5"><svg><use href="#i-box"/></svg></span><span class="svc-num">05</span></div>
        <h3>集运合箱</h3><div class="svc-en">CONSOLIDATION</div>
        <p>多商家包裹免费仓储 <?= (int) $storageDays ?> 天，智能合箱去物减重，包裹更轻更省空间。</p>
        <div class="svc-tags"><i>免费仓储</i><i>智能合箱</i><i>免费质检</i></div>
        <a class="svc-link" href="#plus">了解更多 <svg><use href="#i-arrow"/></svg></a>
      </div>
      <div class="svc-card reveal d1">
        <div class="svc-top"><span class="svc-icon g6"><svg><use href="#i-filecheck"/></svg></span><span class="svc-num">06</span></div>
        <h3>清关报关</h3><div class="svc-en">CUSTOMS CLEARANCE</div>
        <p>自有持证关务团队，Pro Forma / 正式报关均可操作，疑难件专门处理。</p>
        <div class="svc-tags"><i>持证团队</i><i>8h 极速放行</i><i>合规申报</i></div>
        <a class="svc-link" href="#plus">了解更多 <svg><use href="#i-arrow"/></svg></a>
      </div>
      <div class="svc-card reveal d2">
        <div class="svc-top"><span class="svc-icon g7"><svg><use href="#i-cart"/></svg></span><span class="svc-num">07</span></div>
        <h3>电商专线</h3><div class="svc-en">E-COMMERCE LINE</div>
        <p>Amazon FBA 头程、Temu / Shein 平台专线，尾程官方账号直派入仓。</p>
        <div class="svc-tags"><i>FBA 头程</i><i>平台专线</i><i>预约入仓</i></div>
        <a class="svc-link" href="#plus">了解更多 <svg><use href="#i-arrow"/></svg></a>
      </div>
      <div class="svc-card reveal d3">
        <div class="svc-top"><span class="svc-icon g8"><svg><use href="#i-refresh"/></svg></span><span class="svc-num">08</span></div>
        <h3>退货换标</h3><div class="svc-en">RETURNS & RELABEL</div>
        <p>海外退货接收、质检翻新、重新贴标再上架，让滞销库存重新流动。</p>
        <div class="svc-tags"><i>退货接收</i><i>质检翻新</i><i>重新上架</i></div>
        <a class="svc-link" href="#plus">了解更多 <svg><use href="#i-arrow"/></svg></a>
      </div>
    </div>
  </div>
</section>

<!-- ============ 全球航线网络 ============ -->
<!-- 全球网络与航线：静态内容。可后续接后台「物流渠道」+「仓库」数据 -->
<section class="routes sec dark" id="network">
  <span class="sec-mark" style="bottom:20px;left:-30px">NETWORK</span>
  <div class="container">
    <div class="routes-grid">
      <div>
        <div class="sec-head left" style="margin-bottom:34px">
          <span class="eyebrow"><span class="en">GLOBAL NETWORK</span>· 全球网络</span>
          <h2 class="sec-title">航线通达全球<br><span class="grad-text">220+ 国家与地区</span></h2>
          <p class="sec-sub">以深圳、香港、上海三大枢纽为核心，链接北美、欧洲、东南亚主要空运港与海运港，干线班次每日稳定起飞。</p>
        </div>
        <div class="route-list">
          <div class="route-row reveal">
            <span class="route-cities">上海 <svg><use href="#i-plane"/></svg> 洛杉矶 <small>PVG-LAX</small></span>
            <span class="route-mode m-air">空运</span>
            <span class="route-days">3-5 天</span>
            <span class="route-freq">每周 14 班</span>
          </div>
          <div class="route-row reveal d1">
            <span class="route-cities">深圳 <svg><use href="#i-plane"/></svg> 纽约 <small>SZX-JFK</small></span>
            <span class="route-mode m-sea">海运</span>
            <span class="route-days">28-35 天</span>
            <span class="route-freq">每周 3 班</span>
          </div>
          <div class="route-row reveal d2">
            <span class="route-cities">香港 <svg><use href="#i-plane"/></svg> 伦敦 <small>HKG-LHR</small></span>
            <span class="route-mode m-air">空运</span>
            <span class="route-days">4-6 天</span>
            <span class="route-freq">每周 8 班</span>
          </div>
          <div class="route-row reveal d3">
            <span class="route-cities">广州 <svg><use href="#i-plane"/></svg> 东京 <small>CAN-NRT</small></span>
            <span class="route-mode m-exp">快递</span>
            <span class="route-days">2-4 天</span>
            <span class="route-freq">每日 2 班</span>
          </div>
          <div class="route-row reveal d4">
            <span class="route-cities">义乌 <svg><use href="#i-plane"/></svg> 悉尼 <small>YIW-SYD</small></span>
            <span class="route-mode m-sea">海运</span>
            <span class="route-days">22-30 天</span>
            <span class="route-freq">每周 2 班</span>
          </div>
        </div>
        <a class="route-foot reveal d5" href="#cta">咨询顾问获取全部 200+ 航线 <svg><use href="#i-arrow"/></svg></a>
      </div>
      <div class="net-panel reveal d2">
        <div class="map-tag t1"><svg><use href="#i-pin"/></svg>枢纽城市 <b>12</b>&nbsp;· 海外仓 <b>40+</b></div>
        <div class="map-tag t2"><svg><use href="#i-plane"/></svg>每周干线班次 <b>680+</b></div>
        <svg class="netmap" viewBox="0 0 800 500" role="img" aria-label="全球航线网络示意图">
          <defs>
            <pattern id="mapDots" width="26" height="26" patternUnits="userSpaceOnUse">
              <circle cx="1.6" cy="1.6" r="1.6" fill="rgba(148,163,184,.18)"/>
            </pattern>
          </defs>
          <rect width="800" height="500" fill="url(#mapDots)"/>
          <path class="arc" d="M670 157 Q 460 10 137 149"/>
          <path class="arc" d="M670 157 Q 470 30 236 131"/>
          <path class="arc" d="M670 157 Q 545 55 400 103"/>
          <path class="arc" d="M670 157 Q 560 70 419 107"/>
          <path class="arc" d="M670 157 Q 610 110 522 173"/>
          <path class="arc" d="M670 157 Q 665 195 631 236"/>
          <path class="arc" d="M670 157 Q 700 110 710 145"/>
          <path class="arc" d="M670 157 Q 735 235 736 330"/>
          <path class="arc" d="M670 157 Q 500 400 296 302"/>
          <circle r="3.2" fill="#FBBF24"><animateMotion dur="5s" repeatCount="indefinite"><mpath href="#r1"/></animateMotion></circle>
          <path id="r1" d="M670 157 Q 460 10 137 149" fill="none"/>
          <circle r="3.2" fill="#34D399"><animateMotion dur="6.5s" begin="-2s" repeatCount="indefinite"><mpath href="#r2"/></animateMotion></circle>
          <path id="r2" d="M670 157 Q 470 30 236 131" fill="none"/>
          <circle r="3.2" fill="#F87171"><animateMotion dur="4.2s" begin="-1s" repeatCount="indefinite"><mpath href="#r3"/></animateMotion></circle>
          <path id="r3" d="M670 157 Q 545 55 400 103" fill="none"/>
          <circle r="3.2" fill="#A78BFA"><animateMotion dur="7s" begin="-3s" repeatCount="indefinite"><mpath href="#r4"/></animateMotion></circle>
          <path id="r4" d="M670 157 Q 735 235 736 330" fill="none"/>
          <circle cx="670" cy="157" r="6" fill="none" stroke="#38BDF8" stroke-width="1.5" opacity=".8">
            <animate attributeName="r" values="6;26" dur="2.4s" repeatCount="indefinite"/>
            <animate attributeName="opacity" values=".8;0" dur="2.4s" repeatCount="indefinite"/>
          </circle>
          <circle cx="670" cy="157" r="6" fill="none" stroke="#38BDF8" stroke-width="1.5" opacity=".8">
            <animate attributeName="r" values="6;26" dur="2.4s" begin="1.2s" repeatCount="indefinite"/>
            <animate attributeName="opacity" values=".8;0" dur="2.4s" begin="1.2s" repeatCount="indefinite"/>
          </circle>
          <g class="nodes">
            <circle class="node-dot hub" cx="670" cy="157" r="6"/>
            <text class="hub-t" x="670" y="140" text-anchor="middle">上海 · 枢纽</text>
            <circle class="node-dot" cx="137" cy="149" r="4.5"/><text x="137" y="172" text-anchor="middle">洛杉矶</text>
            <circle class="node-dot" cx="236" cy="131" r="4.5"/><text x="236" y="118" text-anchor="middle">纽约</text>
            <circle class="node-dot" cx="296" cy="302" r="4.5"/><text x="296" y="325" text-anchor="middle">圣保罗</text>
            <circle class="node-dot" cx="400" cy="103" r="4.5"/><text x="384" y="90" text-anchor="middle">伦敦</text>
            <circle class="node-dot" cx="419" cy="107" r="4"/><text x="440" y="96" text-anchor="middle">法兰克福</text>
            <circle class="node-dot" cx="522" cy="173" r="4.5"/><text x="522" y="158" text-anchor="middle">迪拜</text>
            <circle class="node-dot" cx="631" cy="236" r="4.5"/><text x="614" y="258" text-anchor="middle">新加坡</text>
            <circle class="node-dot" cx="710" cy="145" r="4.5"/><text x="716" y="128" text-anchor="middle">东京</text>
            <circle class="node-dot" cx="736" cy="330" r="4.5"/><text x="736" y="353" text-anchor="middle">悉尼</text>
          </g>
        </svg>
        <div class="map-legend">
          <span><i style="background:#38BDF8"></i>空运干线</span>
          <span><i style="background:#34D399"></i>海运航线</span>
          <span><i style="background:#FBBF24"></i>转运班列</span>
          <span><i style="background:#A78BFA"></i>末端派送</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ 服务流程 ============ -->
<!-- 服务流程：静态内容（说明性文案，一般无需后台化） -->
<section class="sec" id="process">
  <div class="container">
    <div class="sec-head">
      <span class="eyebrow"><span class="en">HOW IT WORKS</span>· 服务流程</span>
      <h2 class="sec-title">四步开启<span class="grad-text">全球转运</span></h2>
      <p class="sec-sub">注册即可获得专属海外仓地址，全程节点可视，包裹状态实时同步。</p>
    </div>
    <div class="process-steps">
      <div class="p-step reveal">
        <div class="p-num"><svg><use href="#i-users"/></svg><span class="p-badge">2 分钟注册</span></div>
        <h3><i>STEP 1</i>注册会员</h3>
        <p>注册即获专属仓库地址与会员码，实名认证一步完成。</p>
        <span class="p-chip">约需 2 分钟</span>
      </div>
      <div class="p-step reveal d1">
        <div class="p-num"><svg><use href="#i-warehouse"/></svg></div>
        <h3><i>STEP 2</i>发货入仓</h3>
        <p>淘宝、京东下单直接填转运地址，商家发货后预报包裹。</p>
        <span class="p-chip">国内包邮代收</span>
      </div>
      <div class="p-step reveal d2">
        <div class="p-num"><svg><use href="#i-box"/></svg></div>
        <h3><i>STEP 3</i>入库合箱</h3>
        <p>免费仓储 <?= (int) $storageDays ?> 天，质检拍照、去物合箱、加固打包。</p>
        <span class="p-chip">包装更轻省</span>
      </div>
      <div class="p-step reveal d3">
        <div class="p-num"><svg><use href="#i-truck"/></svg></div>
        <h3><i>STEP 4</i>支付出库</h3>
        <p>确认线路下单出库，航班起飞推送节点，全球门到门签收。</p>
        <span class="p-chip">节点实时可视</span>
      </div>
    </div>
  </div>
</section>

<!-- ============ 增值服务 + 服务承诺 ============ -->
<!-- 增值服务：静态内容。可后续接后台「增值服务」表（value_added_services） -->
<section class="sec sec-soft" id="plus">
  <div class="container">
    <div class="sec-head">
      <span class="eyebrow"><span class="en">VALUE-ADDED</span>· 增值服务</span>
      <h2 class="sec-title">不止运输，<span class="grad-text">服务更周到</span></h2>
      <p class="sec-sub">从入库到签收的每一个环节，都有免费增值服务与写进合同的服务承诺兜底。</p>
    </div>
    <div class="tools-grid">
      <div class="tool-card reveal">
        <div class="tool-head">
          <span class="tool-ic"><svg><use href="#i-box"/></svg></span>
          <span><b>免费增值服务</b><span>贯穿揽收、仓储、出库全流程</span></span>
        </div>
        <div class="value-grid">
          <div class="value-item"><span class="vi g4"><svg><use href="#i-warehouse"/></svg></span><div class="vt"><b>免费仓储 <?= (int) $storageDays ?> 天</b><p>入库长期免费存放，凑单更从容</p></div></div>
          <div class="value-item"><span class="vi g2"><svg><use href="#i-scan"/></svg></span><div class="vt"><b>出库质检拍照</b><p>逐件检查，出库前拍照确认</p></div></div>
          <div class="value-item"><span class="vi g5"><svg><use href="#i-box"/></svg></span><div class="vt"><b>智能合箱减重</b><p>多包裹合并，去除冗余包装</p></div></div>
          <div class="value-item"><span class="vi g6"><svg><use href="#i-shield"/></svg></span><div class="vt"><b>加固防损打包</b><p>气泡膜加固，长途运输无忧</p></div></div>
          <div class="value-item"><span class="vi g1"><svg><use href="#i-filecheck"/></svg></span><div class="vt"><b>代缴关税税费</b><p>关税代缴，无需自己对接海关</p></div></div>
          <div class="value-item"><span class="vi g7"><svg><use href="#i-award"/></svg></span><div class="vt"><b>全程货物保价</b><p>按货物价值保价，丢损必赔</p></div></div>
          <div class="value-item"><span class="vi g8"><svg><use href="#i-truck"/></svg></span><div class="vt"><b>白手套派送</b><p>大件入户安放，签收更省心</p></div></div>
          <div class="value-item"><span class="vi g3"><svg><use href="#i-refresh"/></svg></span><div class="vt"><b>退货换标处理</b><p>海外退货质检换标再上架</p></div></div>
        </div>
      </div>
      <div class="tool-card reveal d1">
        <div class="tool-head">
          <span class="tool-ic" style="background:linear-gradient(135deg,#FBBF24,#F59E0B);box-shadow:0 12px 26px -10px rgba(245,158,11,.55)"><svg><use href="#i-shield"/></svg></span>
          <span><b>我们的服务承诺</b><span>写进合同的确定性，拒绝模糊表达</span></span>
        </div>
        <div class="promise-grid">
          <?php /* 服务承诺：后台「站点信息 → 官网首页数字」可改 */ ?>
          <?php foreach ($promises as $p): ?>
          <div class="promise"><b><?= View::e((string) $p['value']) ?><em><?= View::e((string) $p['unit']) ?></em></b><p><?= View::e((string) $p['label']) ?></p></div>
          <?php endforeach; ?>
        </div>
        <div class="promise-foot">
          <a class="btn btn-primary" href="#cta">咨询专属顾问</a>
          <?php if ($contact['phone'] !== ''): ?>
          <span class="promise-hot"><svg><use href="#i-phone"/></svg><?= View::e($contact['phone']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ 核心优势 Bento ============ -->
<!-- 选择我们的理由：静态内容，与「增值服务」同源 -->
<section class="sec">
  <span class="sec-mark" style="top:30px;left:-30px">WHY US</span>
  <div class="container">
    <div class="sec-head">
      <span class="eyebrow"><span class="en">WHY GLOBEPILOT</span>· 核心优势</span>
      <h2 class="sec-title">为什么 12 万用户<br>选择<span class="grad-text"><?= View::e($brand) ?></span>？</h2>
    </div>
    <div class="bento">
      <div class="b-card b-a reveal">
        <span class="b-tag">REAL-TIME</span>
        <h3><svg><use href="#i-scan"/></svg>全程节点可视</h3>
        <p>自研物流中台对接 60+ 承运商数据，从揽收到签收，每一个节点都清晰可见。</p>
        <div class="mini-track">
          <div class="mt-head"><b>深圳 → 洛杉矶 · 今日干线</b><span>准点率 99.2%</span></div>
          <div class="mt-route">SZX<span class="ln"><i></i></span>LAX</div>
          <div class="mt-steps"><b>已揽收</b><b>已起飞</b><span>清关中</span><span>派送中</span></div>
        </div>
      </div>
      <div class="b-card reveal d1">
        <span class="b-tag">WAREHOUSE</span>
        <span class="b-ic g4"><svg><use href="#i-warehouse"/></svg></span>
        <div class="b-big"><span class="count" data-count="40">0</span><small>+</small></div>
        <p>全球自营海外仓，近服务器式布局，一件代发次日达。</p>
      </div>
      <div class="b-card reveal d2">
        <span class="b-tag">SMART BOX</span>
        <h3>智能合箱减重</h3>
        <p>算法推荐最优装箱组合，去除冗余包装。</p>
        <div class="stacks"><i></i><i></i><i></i></div>
      </div>
      <div class="b-card b-d reveal d1">
        <span class="b-tag">CUSTOMS</span>
        <h3><svg><use href="#i-filecheck"/></svg>极速清关</h3>
        <p>自有持证关务团队 + 白名单通道，平均放行时间远超行业水平。</p>
        <div class="bars">
          <div class="bar-row">
            <div class="bar-lbl"><span>行业平均放行</span><b>72 h</b></div>
            <div class="bar bar-gray"><i data-w="92%"></i></div>
          </div>
          <div class="bar-row">
            <div class="bar-lbl"><span><?= View::e($brand) ?>放行</span><b style="color:var(--indigo-500)">8 h</b></div>
            <div class="bar bar-brand"><i data-w="14%"></i></div>
          </div>
        </div>
      </div>
      <div class="b-card reveal d2">
        <span class="b-tag">INSURANCE</span>
        <h3><svg><use href="#i-shield"/></svg>丢损必赔</h3>
        <div class="shield-wrap">
          <span class="b-ic g5"><svg><use href="#i-shield"/></svg></span>
          <div><div class="amount">全额赔付</div><p>按货物实际价值足额理赔，48 小时快速到账。</p></div>
        </div>
      </div>
      <div class="b-card reveal d3">
        <span class="b-tag">24 / 7</span>
        <h3>专属顾问服务</h3>
        <p>中文客服全天在线，1 对 1 群响应，急货专人跟盯。</p>
        <div class="face-row">
          <span class="av-2">客</span><span class="av-4">服</span><span class="av-1">中</span><span class="av-5">心</span>
          <b class="b-24">7×24h</b>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ 行业解决方案 ============ -->
<!-- 行业解决方案：静态内容 -->
<section class="sec sec-soft" id="solutions">
  <div class="container">
    <div class="sec-head">
      <span class="eyebrow"><span class="en">SOLUTIONS</span>· 行业解决方案</span>
      <h2 class="sec-title">不同场景，<span class="grad-text">皆有方案</span></h2>
      <p class="sec-sub">针对跨境电商、留学生与海淘个人，顾问将为你组合专属的线路与服务方案。</p>
    </div>
    <div class="pricing-grid">
      <div class="price-card reveal">
        <div class="price-name"><svg><use href="#i-cart"/></svg>跨境电商企业</div>
        <p class="price-desc">大货量出海的一站式物流中台</p>
        <div class="sol-tag"><svg><use href="#i-users"/></svg>适合月均 100kg+ 商家</div>
        <ul class="feat">
          <li><svg><use href="#i-check"/></svg>FBA 头程预约入仓</li>
          <li><svg><use href="#i-check"/></svg>海外仓储一件代发</li>
          <li><svg><use href="#i-check"/></svg>系统 API 对接自动下单</li>
          <li><svg><use href="#i-check"/></svg>大客户经理 1 对 1 服务</li>
          <li><svg><use href="#i-check"/></svg>旺季舱位优先保障</li>
        </ul>
        <a class="btn btn-ghost-dark" href="#cta">咨询行业方案</a>
      </div>
      <div class="price-card hot reveal d1">
        <span class="hot-badge">🎓 留学生家庭首选</span>
        <div class="price-name"><svg><use href="#i-plane"/></svg>留学生活寄递</div>
        <p class="price-desc">行李家当跨洋直寄，轻装出发</p>
        <div class="sol-tag"><svg><use href="#i-award"/></svg>留学生与家长群体</div>
        <ul class="feat">
          <li><svg><use href="#i-check"/></svg>留学季专线旺季加舱</li>
          <li><svg><use href="#i-check"/></svg>被褥衣物智能合箱减重</li>
          <li><svg><use href="#i-check"/></svg>宿舍门到门直送</li>
          <li><svg><use href="#i-check"/></svg>清关资料全程代办</li>
          <li><svg><use href="#i-check"/></svg>全程保价安心托付</li>
        </ul>
        <a class="btn btn-primary" href="#cta">咨询留学方案</a>
      </div>
      <div class="price-card reveal d2">
        <div class="price-name"><svg><use href="#i-bolt"/></svg>海淘个人玩家</div>
        <p class="price-desc">全球好物一键转运到家</p>
        <div class="sol-tag"><svg><use href="#i-star"/></svg>海淘与代购个人</div>
        <ul class="feat">
          <li><svg><use href="#i-check"/></svg>海淘订单免费代收入仓</li>
          <li><svg><use href="#i-check"/></svg>多商家包裹免费合箱</li>
          <li><svg><use href="#i-check"/></svg>敏感货合规专线可选</li>
          <li><svg><use href="#i-check"/></svg>物流节点实时推送</li>
          <li><svg><use href="#i-check"/></svg>新人顾问全程指导</li>
        </ul>
        <a class="btn btn-ghost-dark" href="#cta">咨询转运方案</a>
      </div>
    </div>
  </div>
</section>

<!-- ============ 用户口碑 ============ -->
<!-- 用户口碑：静态内容。可后续接后台评价管理（需新增数据表） -->
<section class="sec voices">
  <div class="container">
    <div class="sec-head">
      <span class="eyebrow"><span class="en">TESTIMONIALS</span>· 用户口碑</span>
      <h2 class="sec-title">被 128,000+ 用户<span class="grad-text">信赖与推荐</span></h2>
      <p class="sec-sub">好评率 4.9 / 5.0，真实反馈来自跨境电商卖家、海淘达人与留学生家庭。</p>
    </div>
  </div>
  <div class="voice-row">
    <div class="voice-track">
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"做亚马逊三年，头程换过四家物流商，最后稳定在<?= View::e($brand) ?>。美森快船基本 26 天入仓，旺季舱位也从不掉链子。"</p><div class="vc-user"><span class="av-1">陈</span><span><b>陈先生</b><i>跨境电商卖家 · 深圳</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"海淘五年用过七八家转运，合箱最干净的一家，包装纸箱都帮我去了，重量一下子轻了一大截，拍照质检也很清楚。"</p><div class="vc-user"><span class="av-2">林</span><span><b>小林同学</b><i>资深海淘达人 · 上海</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"给孩子寄留学生活用品，客服全程微信群里跟盯，清关遇到抽查也第一时间帮忙处理，第六天就签收了，特别安心。"</p><div class="vc-user"><span class="av-3">王</span><span><b>王女士</b><i>留学生家长 · 北京</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"带电产品一直是老大难，他们家带电专线稳定走了两年，手机、耳机都顺利，丢损理赔 48 小时真到账。"</p><div class="vc-user"><span class="av-4">周</span><span><b>Kevin 周</b><i>3C 品类卖家 · 东莞</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"日本线路时效稳定在 5 天左右，旺季也能稳定订到舱，量大还有客户经理 1 对 1 专属对接。"</p><div class="vc-user"><span class="av-5">Y</span><span><b>Yuki</b><i>日本代购 · 广州</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"退换标服务救了我们一批滞销库存，翻新贴标重新上架只用了 4 天，海外仓系统对接Shopify 也很快。"</p><div class="vc-user"><span class="av-1">吴</span><span><b>吴经理</b><i>家居品牌出海 · 杭州</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"做亚马逊三年，头程换过四家物流商，最后稳定在<?= View::e($brand) ?>。美森快船基本 26 天入仓，旺季舱位也从不掉链子。"</p><div class="vc-user"><span class="av-1">陈</span><span><b>陈先生</b><i>跨境电商卖家 · 深圳</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"海淘五年用过七八家转运，合箱最干净的一家，包装纸箱都帮我去了，重量一下子轻了一大截，拍照质检也很清楚。"</p><div class="vc-user"><span class="av-2">林</span><span><b>小林同学</b><i>资深海淘达人 · 上海</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"给孩子寄留学生活用品，客服全程微信群里跟盯，清关遇到抽查也第一时间帮忙处理，第六天就签收了，特别安心。"</p><div class="vc-user"><span class="av-3">王</span><span><b>王女士</b><i>留学生家长 · 北京</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"带电产品一直是老大难，他们家带电专线稳定走了两年，手机、耳机都顺利，丢损理赔 48 小时真到账。"</p><div class="vc-user"><span class="av-4">周</span><span><b>Kevin 周</b><i>3C 品类卖家 · 东莞</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"日本线路时效稳定在 5 天左右，旺季也能稳定订到舱，量大还有客户经理 1 对 1 专属对接。"</p><div class="vc-user"><span class="av-5">Y</span><span><b>Yuki</b><i>日本代购 · 广州</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"退换标服务救了我们一批滞销库存，翻新贴标重新上架只用了 4 天，海外仓系统对接Shopify 也很快。"</p><div class="vc-user"><span class="av-1">吴</span><span><b>吴经理</b><i>家居品牌出海 · 杭州</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
    </div>
  </div>
  <div class="voice-row rev">
    <div class="voice-track">
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"欧线 10 天稳稳到，双清包税一站式搞定，流程透明，财务对账特别省心。"</p><div class="vc-user"><span class="av-2">郑</span><span><b>郑老板</b><i>灯具外贸 · 中山</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"APP 每个节点都有推送，飞机起飞、落地、清关全知道，第一次海淘也没踩坑。"</p><div class="vc-user"><span class="av-4">苏</span><span><b>苏小姐</b><i>美妆海淘 · 成都</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"旺季舱位真的稳，别人家排一周，他们 48 小时起飞，急单全靠他们救场。"</p><div class="vc-user"><span class="av-3">梁</span><span><b>梁先生</b><i>数码配件 · 华强北</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"澳洲线 22 天入仓，FBA 预约入仓他们全包了，我只需要在系统点确认。"</p><div class="vc-user"><span class="av-1">何</span><span><b>Heidi 何</b><i>宠物用品卖家 · 宁波</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"客服半夜十二点还在回消息，帮我改了收件地址，这种服务真的没话说。"</p><div class="vc-user"><span class="av-5">C</span><span><b>Cindy</b><i>服饰跨境电商 · 广州</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"从 50kg 试单到每月 3 吨，三年合作零丢件，时效比同行还稳一截。"</p><div class="vc-user"><span class="av-2">马</span><span><b>马经理</b><i>汽配出口 · 重庆</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"欧线 10 天稳稳到，双清包税一站式搞定，流程透明，财务对账特别省心。"</p><div class="vc-user"><span class="av-2">郑</span><span><b>郑老板</b><i>灯具外贸 · 中山</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"APP 每个节点都有推送，飞机起飞、落地、清关全知道，第一次海淘也没踩坑。"</p><div class="vc-user"><span class="av-4">苏</span><span><b>苏小姐</b><i>美妆海淘 · 成都</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"旺季舱位真的稳，别人家排一周，他们 48 小时起飞，急单全靠他们救场。"</p><div class="vc-user"><span class="av-3">梁</span><span><b>梁先生</b><i>数码配件 · 华强北</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"澳洲线 22 天入仓，FBA 预约入仓他们全包了，我只需要在系统点确认。"</p><div class="vc-user"><span class="av-1">何</span><span><b>Heidi 何</b><i>宠物用品卖家 · 宁波</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"客服半夜十二点还在回消息，帮我改了收件地址，这种服务真的没话说。"</p><div class="vc-user"><span class="av-5">C</span><span><b>Cindy</b><i>服饰跨境电商 · 广州</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
      <div class="voice-card"><div class="vc-stars"><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg><svg><use href="#i-star"/></svg></div><p>"从 50kg 试单到每月 3 吨，三年合作零丢件，时效比同行还稳一截。"</p><div class="vc-user"><span class="av-2">马</span><span><b>马经理</b><i>汽配出口 · 重庆</i></span><svg class="vc-quote" width="26" height="26"><use href="#i-chat"/></svg></div></div>
    </div>
  </div>
</section>

<!-- ============ FAQ ============ -->
<!-- 常见问题：静态内容。可后续接后台内容管理（需新增数据表） -->
<section class="sec sec-soft" id="faq">
  <div class="container">
    <div class="sec-head">
      <span class="eyebrow"><span class="en">FAQ</span>· 常见问题</span>
      <h2 class="sec-title">你关心的，<span class="grad-text">都在这里</span></h2>
      <p class="sec-sub">还有疑问？<?= $contact['phone'] !== '' ? '点击右下角在线客服，或拨打 ' . View::e($contact['phone']) . ' 咨询。' : '点击右下角在线客服咨询。' ?></p>
    </div>
    <div class="faq-wrap">
      <?php /* 常见问题：后台「营销内容 → 常见问题」可改（按空行分段，首行问题、其余答案） */ ?>
      <?php foreach ($faqs as $i => $f): ?>
      <div class="faq-item<?= $i === 0 ? ' open' : '' ?>">
        <button class="faq-q"><span class="fnum">Q<?= $i + 1 ?></span><?= View::e($f['q']) ?><span class="chev"><svg><use href="#i-chev"/></svg></span></button>
        <div class="faq-a"><div><?= View::e($f['a']) ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============ CTA ============ -->
<!-- 转化区：CTA 指向注册与页脚联系方式 -->
<section class="sec" id="cta">
  <div class="container">
    <div class="cta-panel reveal">
      <h2>让全球生意，从此简单</h2>
      <p>立即注册，专属物流顾问 1 对 1 为你规划最优线路与一站式出海方案。</p>
      <div class="cta-actions">
        <a class="btn btn-gold" href="#contact">联系顾问开通</a>
        <a class="btn btn-ghost" href="#solutions">查看解决方案</a>
      </div>
      <?php if ($contact['phone'] !== ''): ?>
      <span class="cta-hot"><svg><use href="#i-phone"/></svg>大客户专线：<b><?= View::e($contact['phone']) ?></b></span>
      <?php endif; ?>
    </div>
  </div>
</section>
</main>


<?= View::partial('partials/home_footer') ?>
<?= View::partial('partials/home_chrome_js') ?>
<script>/* 优势条形图 */
const io3=new IntersectionObserver(es=>{es.forEach(e=>{if(e.isIntersecting){e.target.style.width=e.target.dataset.w;io3.unobserve(e.target);}})},{threshold:.4});
$$('.bar i').forEach(el=>io3.observe(el));

/* FAQ 手风琴 */
$$('.faq-item').forEach(it=>{
  const btn=it.querySelector('.faq-q'), a=it.querySelector('.faq-a');
  if(it.classList.contains('open')) a.style.maxHeight=a.scrollHeight+'px';
  btn.addEventListener('click',()=>{
    const wasOpen=it.classList.contains('open');
    $$('.faq-item.open').forEach(o=>{o.classList.remove('open');o.querySelector('.faq-a').style.maxHeight=null;});
    if(!wasOpen){ it.classList.add('open'); a.style.maxHeight=a.scrollHeight+'px'; }
  });
});

</script>
</body>
</html>
