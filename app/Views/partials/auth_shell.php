<?php declare(strict_types=1);
/**
 * @file app/Views/partials/auth_shell.php
 * @description 认证页外壳（登录 / 注册 / 找回密码共用，光年 v5 版式）：左侧品牌区 + 右侧表单区；
 *              各端认证页只需传入标题、图标、特性列表与表单 HTML，即得到完全一致的版式
 * @author AI
 * @version 0.6
 * @date 2026-10-08
 */

use App\Core\View;
use App\Services\SiteChrome;

/**
 * @var string $mark        表单图标
 * @var string $title       表单标题
 * @var string $sub         表单副标题
 * @var string $body        表单区 HTML（表单与脚本）
 * @var string $heroTag     左侧品牌标签（如 管理后台 / 仓库端 / 会员中心）
 * @var string $heroTitle   左侧主标题
 * @var string $heroDesc    左侧说明
 * @var array  $heroPoints  左侧特性列表
 */
$mark = $mark ?? '🌍';
$title = $title ?? '登录';
$sub = $sub ?? '';
$body = $body ?? '';
$heroTag = $heroTag ?? '';
$heroTitle = $heroTitle ?? '全球转运管理系统';
$heroDesc = $heroDesc ?? '';
$heroPoints = $heroPoints ?? [];
?>
<div class="ly-auth">
    <aside class="ly-auth__hero">
        <div class="ly-auth__brand">
            <span>🌍</span>
            <span><?= View::e(SiteChrome::brand()) ?></span>
            <?php if ($heroTag !== ''): ?><span class="ly-auth__tag"><?= View::e($heroTag) ?></span><?php endif; ?>
        </div>
        <div>
            <h1 class="ly-auth__title"><?= View::e($heroTitle) ?></h1>
            <?php if ($heroDesc !== ''): ?><p class="ly-auth__desc"><?= View::e($heroDesc) ?></p><?php endif; ?>
            <?php if ($heroPoints !== []): ?>
            <ul class="ly-auth__list">
                <?php foreach ($heroPoints as $point): ?>
                <li><i class="mdi mdi-check-circle-outline"></i><?= View::e($point) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <div class="ly-auth__foot">
            <?= View::e(SiteChrome::brand()) ?> · v<?= View::e(APP_VERSION ?? '') ?> · <a href="/">返回官网</a>
        </div>
    </aside>

    <section class="ly-auth__panel">
        <div class="ly-auth__card">
            <div class="ly-auth__mark"><?= View::e($mark) ?></div>
            <h2 class="ly-auth__card-title"><?= View::e($title) ?></h2>
            <?php if ($sub !== ''): ?><p class="ly-auth__card-sub"><?= View::e($sub) ?></p><?php endif; ?>
            <?= $body ?>
        </div>
    </section>
</div>