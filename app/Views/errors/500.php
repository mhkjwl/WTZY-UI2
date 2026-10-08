<?php declare(strict_types=1);
/**
 * @file app/Views/errors/500.php
 * @description 500 页面：生产环境隐藏异常细节，仅开发环境展示（独立外壳，自持新版样式）
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

use App\Core\View;
use App\Services\BrandAsset;

/**
 * @var bool   $debug 是否展示异常细节（仅开发环境）
 * @var string $error 异常消息
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 · 系统错误</title>
    <?= BrandAsset::faviconTag() ?>
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/lyear/css/style.min.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('/assets/ui/theme.css') ?>">
</head>
<body class="ui-new ly-error-body">
<main class="ly-error">
    <div class="ly-error__code">500</div>
    <h1 class="ly-error__title">服务器开小差了</h1>
    <p class="ly-error__text">请稍后重试；若持续出现请联系管理员。</p>
    <?php if ($debug): ?>
        <pre class="ly-error__detail"><?= View::e($error) ?></pre>
    <?php endif; ?>
    <div class="ly-error__actions">
        <a class="btn btn--primary" href="/">返回首页</a>
    </div>
</main>
</body>
</html>
