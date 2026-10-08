<?php declare(strict_types=1);
/**
 * @file app/Views/member/claim_missing.php
 * @description 理赔单不存在时的引导内容
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */
?>
<section class="panel">
    <div class="empty-state" style="padding:56px 24px">
        <div style="font-size:44px;margin-bottom:12px">🔍</div>
        <h3 style="font-size:19px;margin-bottom:8px">理赔单不存在</h3>
        <p>该理赔单可能已被删除，或不属于当前账号。</p>
        <p style="margin-top:16px"><a class="btn btn--primary" href="/user/claims">返回理赔列表</a></p>
    </div>
</section>
