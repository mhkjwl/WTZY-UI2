<?php declare(strict_types=1);
/**
 * @file app/Views/admin/denied.php
 * @description 无权限访问页（RBAC 拦截提示，规范 §2.4 引导性空状态）
 * @author AI
 * @version 0.6.1
 * @date 2026-10-09
 */
?>
<section class="card">
    <div class="card-body text-center py-5">
        <div class="display-6 text-primary mb-3"><i class="mdi mdi-lock-outline"></i></div>
        <h4 class="mb-2">无权限访问该模块</h4>
        <p class="text-muted mb-4">当前角色未授权此功能。如需开通，请联系超级管理员在「系统 → 角色权限」中调整授权。</p>
        <a class="btn btn--primary" href="/admin">← 返回控制台</a>
    </div>
</section>