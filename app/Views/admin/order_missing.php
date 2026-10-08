<?php declare(strict_types=1);
/**
 * @file app/Views/admin/order_missing.php
 * @description 后台运单不存在提示（编号错误或已删除）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */
?>
<section class="module-empty">
    <div class="module-empty__icon">🧾</div>
    <h4>运单不存在或已删除</h4>
    <p>该运单可能已被删除，或链接中的编号有误。请返回列表重新检索。</p>
    <a class="btn btn--primary" href="/admin/orders">← 返回订单运单</a>
</section>
