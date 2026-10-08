<?php declare(strict_types=1);
/**
 * @file app/Views/errors/404.php
 * @description 404 页面（独立外壳：不依赖布局、数据库与历史样式表，自持新版样式）
 * @author AI
 * @version 0.6
 * @date 2026-10-08
 */

use App\Core\View;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 · 页面不存在</title>
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/style.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/ui/theme.css') ?>">
</head>
<body class="ui-new ly-error-body">
<main class="ly-error">
    <div class="ly-error__code">404</div>
    <h1 class="ly-error__title">页面不存在或已被移除</h1>
    <p class="ly-error__text">请检查地址是否正确。</p>
    <div class="ly-error__actions">
        <a class="btn btn--primary" href="/">返回首页</a>
    </div>
</main>
</body>
</html>
