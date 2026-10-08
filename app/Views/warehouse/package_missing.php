<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/package_missing.php
 * @description 仓库端包裹不存在提示（编号有误、已删除或不属于本仓库）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-15
 */
?>
<section class="module-empty">
    <div class="module-empty__icon">📦</div>
    <h4>包裹不存在或不属于本仓库</h4>
    <p>该包裹可能已被删除，或归属其它仓库。请返回列表重新检索。</p>
    <a class="btn btn--primary" href="/warehouse/storage">← 返回仓储管理</a>
</section>
