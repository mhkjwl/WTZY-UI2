<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/waybill_missing.php
 * @description 运单不存在或不属于当前仓库
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */
?>
<section class="module-empty">
    <div class="module-empty__icon">🧾</div>
    <h4>运单不存在或不属于本仓库</h4>
    <p>该运单可能已被删除，或归属其它仓库。请返回列表重新检索。</p>
    <a class="btn btn--primary" href="/warehouse/packing">← 返回待打包列表</a>
</section>
