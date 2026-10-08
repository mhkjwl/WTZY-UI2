<?php declare(strict_types=1);
/**
 * @file app/Views/admin/package_missing.php
 * @description 后台包裹不存在提示（单号错误或已删除）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */
?>
<section class="module-empty">
    <div class="module-empty__icon">📦</div>
    <h4>包裹不存在或已删除</h4>
    <p>该包裹可能已被删除，或链接中的编号有误。请返回列表重新检索。</p>
    <a class="btn btn--primary" href="/admin/packages">← 返回包裹列表</a>
</section>
