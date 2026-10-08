<?php declare(strict_types=1);
/**
 * @file install/steps/step4_config.php
 * @description 安装第四步：系统配置（站点名称 / 域名 / 时区）
 * @author AI
 * @version 0.6
 * @date 2026-10-08
 */

declare(strict_types=1);

install_session();
if (empty($_SESSION['install']['admin'])) {
    install_redirect('3');
}

$supportedTimezones = install_supported_timezones();

$error = '';
$prev = $_SESSION['install']['config'] ?? [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    install_verify_csrf();

    $siteName = trim((string) ($_POST['site_name'] ?? ''));
    $siteUrl  = rtrim(trim((string) ($_POST['site_url'] ?? '')), '/');
    $timezone = (string) ($_POST['site_timezone'] ?? '');

    if ($siteName === '' || mb_strlen($siteName) > 30) {
        $error = '站点名称不能为空且不超过 30 字';
    } elseif (!preg_match('#^https?://#', $siteUrl)) {
        $error = '站点域名需以 http:// 或 https:// 开头';
    } elseif (!array_key_exists($timezone, $supportedTimezones)) {
        $error = '时区取值无效';
    } else {
        $_SESSION['install']['config'] = ['site_name' => $siteName, 'url' => $siteUrl, 'timezone' => $timezone];
        install_redirect('5');
    }
}

$csrfToken = install_csrf_token();
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$defaultUrl = $prev['url'] ?? ($scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1'));
$vSite = htmlspecialchars($error !== '' ? ($siteName ?? '') : ($prev['site_name'] ?? 'WTZY 全球转运'));
$vUrl = htmlspecialchars($error !== '' ? ($siteUrl ?? '') : $defaultUrl);
$vTz = $error !== '' ? ($timezone ?? 'Asia/Shanghai') : ($prev['timezone'] ?? 'Asia/Shanghai');
$errorHtml = $error !== '' ? '<p class="alert alert--error">' . htmlspecialchars($error) . '</p>' : '';

$timezoneOptions = '';
foreach ($supportedTimezones as $tzCode => $tzLabel) {
    $selected = $tzCode === $vTz ? ' selected' : '';
    $timezoneOptions .= '<option value="' . $tzCode . '"' . $selected . '>' . $tzLabel . '</option>';
}

install_view('系统配置', <<<HTML
<h2 class="card-title">系统配置</h2>
<p class="card-sub">安装完成后均可在管理后台修改</p>
<form method="post" action="/install/?step=4" novalidate>
    <input type="hidden" name="_token" value="{$csrfToken}">
    <div class="form-field"><label class="form-label">站点名称</label>
        <input class="form-input" type="text" name="site_name" value="{$vSite}" required></div>
    <div class="form-field"><label class="form-label">站点域名（已自动检测）</label>
        <input class="form-input" type="text" name="site_url" value="{$vUrl}" required></div>
    <div class="form-field"><label class="form-label">默认时区</label>
        <select class="form-input" name="site_timezone">{$timezoneOptions}</select></div>
    {$errorHtml}
    <div class="card-actions">
        <a class="btn btn--ghost" href="/install/?step=3">← 上一步</a>
        <button class="btn btn--primary" type="submit">下一步：执行安装 →</button>
    </div>
</form>
HTML, 4);
