<?php declare(strict_types=1);
/**
 * @file app/Views/web/rates.php
 * @description 前台转运资费页（沿用官网首页的页头/页脚外壳与设计语言）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $groups     线路资费分组：[{warehouse, routes:[{route,first_weight,first_fee,step_weight,step_fee,aging}]}]
 * @var array $services   增值服务行：[{name,price,desc}]
 * @var int   $routeCount 线路总数
 */
$warehouseCount = count($groups);
$empty = $warehouseCount === 0 && $services === [];

/**
 * 首重/续重展示：重量与金额都可能被后台留空，或金额写成文字（如「以客服报价为准」）
 * 数字金额统一格式化，非数字原样展示，避免把文字硬转成 0.00
 */
$fee = static function (string $weight, string $amount): string {
    $w = trim($weight);
    $a = trim($amount);
    if ($w === '' && $a === '') {
        return '—';
    }
    $amountText = $a === '' ? '' : (is_numeric($a) ? '¥' . number_format((float) $a, 2) : $a);
    $weightText = $w !== '' ? '<em>/ ' . View::e($w) . ' kg</em>' : '';
    if ($amountText === '') {
        return trim($weightText);
    }
    return View::e($amountText) . $weightText;
};
?>
<?= View::partial('partials/home_head', ['pageTitle' => '转运资费', 'pageCss' => '/assets/common/rates.css']) ?>
<?= View::partial('partials/home_sprite') ?>
<?= View::partial('partials/home_header') ?>

<main>
    <!-- ============ 标题与概览 ============ -->
    <section class="sec dark rate-hero">
        <span class="sec-mark" style="top:30px;right:-30px">RATES</span>
        <div class="container">
            <div class="sec-head">
                <span class="eyebrow"><span class="en">RATES</span>· 转运资费</span>
                <h1 class="sec-title">明码标价<br><span class="grad-text">费用一目了然</span></h1>
                <p class="sec-sub">
                    以下为各仓库线路公开资费，价格以人民币计；实际计费按体积重与实际重量取大者，
                    燃油、偏远、敏感货等附加费会在下单试算时逐项明示，绝无隐藏收费。
                </p>
            </div>
            <?php if (!$empty): ?>
                <div class="stats-grid rate-stats reveal">
                    <div class="stat-card">
                        <div class="stat-ic"><svg><use href="#i-warehouse"/></svg></div>
                        <b><?= (int) $warehouseCount ?></b>
                        <p>仓库类别</p>
                    </div>
                    <div class="stat-card">
                        <div class="stat-ic"><svg><use href="#i-plane"/></svg></div>
                        <b><?= (int) $routeCount ?></b>
                        <p>在列线路</p>
                    </div>
                    <div class="stat-card">
                        <div class="stat-ic"><svg><use href="#i-box"/></svg></div>
                        <b><?= count($services) ?></b>
                        <p>增值服务</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($empty): ?>
        <section class="sec">
            <div class="container">
                <div class="tool-card rate-card" style="text-align:center">
                    <h3 style="font-size:19px;font-weight:800;margin-bottom:10px">资费表尚未配置</h3>
                    <p class="sec-sub" style="margin-top:0">
                        管理员可在后台「业务管理 → 转运资费」中填写各仓库的线路首重、续重、平均时效与增值服务，
                        填写后本页自动展示。
                    </p>
                </div>
            </div>
        </section>
    <?php else: ?>
        <!-- ============ 按仓库的线路资费 ============ -->
        <?php if ($groups !== []): ?>
            <section class="sec sec-soft">
                <div class="container">
                    <div class="sec-head">
                        <span class="eyebrow"><span class="en">FREIGHT</span>· 线路资费</span>
                        <h2 class="sec-title">各仓库线路<br><span class="grad-text">首重与续重</span></h2>
                        <p class="sec-sub">同一仓库下可选不同时效的线路，按需权衡价格与时效。</p>
                    </div>
                    <?php foreach ($groups as $group): ?>
                        <div class="tool-card rate-card reveal">
                            <div class="rate-card__head">
                                <h3><?= View::e($group['warehouse']) ?></h3>
                                <span class="rate-card__count"><?= count($group['routes']) ?> 条线路</span>
                            </div>
                            <div class="rate-scroll">
                                <table class="rate-table">
                                    <thead>
                                    <tr><th>线路</th><th>首重</th><th>续重</th><th>平均时效</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($group['routes'] as $route): ?>
                                        <tr>
                                            <td class="rt-name"><?= View::e((string) $route['route']) ?></td>
                                            <td class="rt-fee"><?= $fee($route['first_weight'], $route['first_fee']) ?></td>
                                            <td class="rt-fee"><?= $fee($route['step_weight'], $route['step_fee']) ?></td>
                                            <td>
                                                <?php if ($route['aging'] !== ''): ?>
                                                    <span class="rate-tag"><svg width="13" height="13"><use href="#i-clock"/></svg><?= View::e((string) $route['aging']) ?></span>
                                                <?php else: ?>
                                                    <span class="rate-tag rate-tag--plain">以实际时效为准</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- ============ 增值服务 ============ -->
        <?php if ($services !== []): ?>
            <section class="sec">
                <div class="container">
                    <div class="sec-head">
                        <span class="eyebrow"><span class="en">VALUE-ADDED</span>· 增值服务</span>
                        <h2 class="sec-title">按需勾选<br><span class="grad-text">让包裹更稳妥</span></h2>
                        <p class="sec-sub">以下服务可在下单时按需选择，费用随运费一并结算。</p>
                    </div>
                    <div class="value-grid">
                        <?php foreach ($services as $service): ?>
                            <div class="value-item rate-svc reveal">
                                <span class="vi g2"><svg><use href="#i-shield"/></svg></span>
                                <div class="vt">
                                    <b><?= View::e((string) $service['name']) ?></b>
                                    <?php if ($service['desc'] !== ''): ?>
                                        <p><?= View::e((string) $service['desc']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="rate-svc__price"><?= View::e($service['price'] !== '' ? (string) $service['price'] : '询价') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- ============ 计费口径与转化 ============ -->
        <section class="sec sec-soft">
            <div class="container">
                <div class="cta-panel reveal">
                    <h2>不确定选哪条线路？</h2>
                    <p>注册后录入包裹信息即可实时试算：系统按实重与体积重取大者计费，费用与时效清清楚楚。</p>
                    <div class="cta-actions">
                        <a class="btn btn-gold" href="/user/register">免费注册，马上试算</a>
                        <a class="btn btn-ghost-dark" href="/user/login">登录会员中心</a>
                    </div>
                </div>
                <p class="rate-note">
                    计费说明：首重为起步重量及对应费用，超出部分按续重单位累加；体积重 = 长×宽×高 ÷ 体积重系数，
                    与实际重量取大者作为计费重量。以上资费为公开参考价，最终费用以下单页试算结果为准。
                </p>
            </div>
        </section>
    <?php endif; ?>
</main>

<?= View::partial('partials/home_footer') ?>
<?= View::partial('partials/home_chrome_js') ?>
</body>
</html>
