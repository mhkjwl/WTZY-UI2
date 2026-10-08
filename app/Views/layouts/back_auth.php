<?php declare(strict_types=1);
/**
 * @file app/Views/layouts/back_auth.php
 * @description 后台认证页布局（管理端 / 仓库端登录共用）：全屏分栏版式（左品牌区 + 右表单区），
 *              与前台导航完全隔离；认证页为独立环境，只加载新版样式；
 *              body 标注 ui-new 使 theme.css 组件规则生效，与会员端登录页保持同一套按钮 / 表单样式
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

use App\Core\View;
use App\Services\BrandAsset;

/**
 * @var string $title
 * @var string $content
 * @var string $csrf
 * @var string $extraCss  端内附加样式表
 */
$extraCss = $extraCss ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= View::e($csrf ?? '') ?>">
    <title><?= View::e($title ?? '后台登录') ?></title>
    <?= BrandAsset::faviconTag() ?>
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/materialdesignicons.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/style.min.css') ?>">
    <?php if ($extraCss !== ''): ?>
        <link rel="stylesheet" href="<?= View::e($extraCss) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= View::asset('/assets/ui/theme.css') ?>">
</head>
<body class="ly-auth-body ui-new">
<?= $content ?? '' ?>
<div id="notify-root" class="notify-root" aria-live="polite"></div>
<script src="<?= View::asset('/assets/common/toast.js') ?>"></script>
<script src="<?= View::asset('/assets/common/confirm.js') ?>"></script>
<script src="<?= View::asset('/assets/common/app.js') ?>"></script>
</body>
</html>