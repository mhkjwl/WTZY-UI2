<?php declare(strict_types=1);
/**
 * @file app/Views/admin/home.php
 * @description 管理后台控制台模板：渐变欢迎横幅 + 实时统计卡 + 模块上线磁贴 + 系统概览
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;
use App\Services\SiteChrome;

/**
 * @var array $staff
 * @var array $stats 每项含 label/value/tone/icon/trend
 * @var array $modules 每项含 key/icon/name/href/desc/color/status
 * @var array $meters 每项含 label/value/percent/color
 * @var int $liveCount
 * @var int $moduleCount
 */
?>
<section class="welcome-banner">
    <div>
        <h2><?= date('H') < 12 ? '上午好' : (date('H') < 18 ? '下午好' : '晚上好') ?>，<?= View::e($staff['real_name'] ?? '') ?> 👋</h2>
        <p>欢迎回到 <?= View::e(SiteChrome::brand()) ?> 控制台，今日运营概览与全部业务模块入口都在这里。</p>
    </div>
    <div class="welcome-banner__meta">
        <span class="badge--glass">📅 <?= date('Y年m月d日') ?></span>
        <span class="badge--glass">🚀 v<?= View::e(APP_VERSION ?? '') ?></span>
    </div>
</section>

<section class="astat-grid">
    <?php foreach ($stats as $stat): ?>
        <div class="astat astat--<?= View::e($stat['tone']) ?>">
            <div class="astat__icon"><?= $stat['icon'] ?></div>
            <div>
                <div class="astat__value"><?= View::e((string) $stat['value']) ?></div>
                <div class="astat__label"><?= View::e($stat['label']) ?></div>
            </div>
            <span class="astat__trend"><?= View::e($stat['trend']) ?></span>
        </div>
    <?php endforeach; ?>
</section>

<div class="section-head">
    <h3>业务模块</h3>
    <span class="badge badge--green"><?= (int) $liveCount ?> / <?= (int) $moduleCount ?> 已上线</span>
    <span class="rule"></span>
</div>

<section class="tile-grid">
    <?php foreach ($modules as $module): ?>
        <?php if ($module['status'] === 'live'): ?>
            <a class="tile tc-<?= View::e($module['color']) ?>" href="<?= View::e($module['href']) ?>">
                <div class="tile__icon"><?= $module['icon'] ?></div>
                <div class="tile__title"><?= View::e($module['name']) ?></div>
                <div class="tile__desc"><?= View::e($module['desc']) ?></div>
                <div class="tile__foot">
                    <span class="badge badge--green">已上线</span>
                    <span class="tile__arrow">→</span>
                </div>
            </a>
        <?php else: ?>
            <div class="tile tile--planned tc-<?= View::e($module['color']) ?>">
                <div class="tile__icon"><?= $module['icon'] ?></div>
                <div class="tile__title"><?= View::e($module['name']) ?></div>
                <div class="tile__desc"><?= View::e($module['desc']) ?></div>
                <div class="tile__foot">
                    <span class="badge badge--gray">未开发</span>
                    <span class="tile__arrow">·</span>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</section>

<div class="section-head" style="margin-top:24px">
    <h3>系统概览</h3>
    <span class="rule"></span>
</div>

<section class="panel" style="border-radius:16px;box-shadow:0 8px 22px rgba(15,23,42,.07)">
    <?php foreach ($meters as $meter): ?>
        <div class="meter tc-<?= View::e($meter['color']) ?>">
            <div class="meter__head"><b><?= View::e($meter['label']) ?></b><span><?= View::e($meter['value']) ?></span></div>
            <div class="meter__bar"><div class="meter__fill" style="width:<?= View::e((string) $meter['percent']) ?>%"></div></div>
        </div>
    <?php endforeach; ?>
</section>
