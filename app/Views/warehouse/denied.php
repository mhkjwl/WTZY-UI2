<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/denied.php
 * @description 仓库端无权限提示（当前角色缺少对应 wh.* 权限点）
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */
?>
<section class="card">
    <div class="card-body text-center py-5">
        <div class="display-6 text-primary mb-3"><i class="mdi mdi-lock-outline"></i></div>
        <h4 class="mb-2">无权限访问该作业模块</h4>
        <p class="text-muted mb-4">当前角色未授权此功能。如需开通，请联系仓库主管在「系统 → 角色权限」中调整授权。</p>
        <a class="btn btn--primary" href="/warehouse">← 返回作业台</a>
    </div>
</section>