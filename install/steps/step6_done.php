<?php declare(strict_types=1);
/**
 * @file install/steps/step6_done.php
 * @description 安装第六步：完成页（入口指引与锁文件说明）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

declare(strict_types=1);

install_session();

if (!file_exists(LOCK_FILE)) {
    // 未完成安装却直达本页：回退到第一步
    install_redirect('1');
}

$lock = require LOCK_FILE;
$adminUser = htmlspecialchars((string) ($lock['admin_user'] ?? ''));
$siteName = htmlspecialchars((string) ($lock['site_name'] ?? ''));

install_view('安装完成', <<<HTML
<div class="done-icon">✅</div>
<h2 class="card-title">安装完成！</h2>
<p class="card-sub">「{$siteName}」已就绪，安装锁文件已生成，重复访问安装向导将被拒绝。</p>
<table class="check-table"><tbody>
    <tr><td>管理员账号</td><td>{$adminUser}</td></tr>
    <tr><td>配置文件</td><td>.env 已生成（含随机密钥，请勿泄露与提交版本库）</td></tr>
    <tr><td>重新安装</td><td>删除 install/lock/installed.lock 后可重新运行安装向导</td></tr>
</tbody></table>
<div class="card-actions">
    <a class="btn btn--primary" href="/admin/login">登录管理后台 →</a>
    <a class="btn btn--ghost" href="/user/register">用户注册入口</a>
    <a class="btn btn--ghost" href="/">打开站点首页</a>
</div>
HTML, 6);
