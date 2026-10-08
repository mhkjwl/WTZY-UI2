<?php declare(strict_types=1);
/**
 * @file app/Views/member/claim_detail.php
 * @description 会员中心：理赔详情
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $claim
 * @var array $types
 * @var array $statusMap
 * @var array $images
 */
$status = (int) $claim['status'];
?>
<div class="adm-toolbar">
    <a class="btn btn--ghost btn--sm" href="/user/claims">‹ 返回列表</a>
    <span class="badge badge--<?= Status::claimTone($status) ?>"><?= View::e($statusMap[$status] ?? '未知') ?></span>
    <span class="badge badge--gray"><?= View::e($types[(int) $claim['claim_type']] ?? '其他') ?></span>
</div>

<div class="site-grid">
    <section class="panel">
        <h3 class="section-head"><span class="badge badge--blue">申请信息</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>理赔单号</span><b class="v-code mono"><?= View::e($claim['claim_no']) ?></b></div>
            <div class="hero-address__row"><span>关联运单</span><b class="v-code mono"><?= View::e((string) ($claim['waybill_no'] ?? '—')) ?></b></div>
            <div class="hero-address__row"><span>关联包裹</span><b class="v-code mono"><?= View::e((string) ($claim['package_no'] ?? '—')) ?></b></div>
            <div class="hero-address__row"><span>申请金额</span><b class="v-money">¥<?= number_format((float) $claim['amount'], 2) ?></b></div>
            <div class="hero-address__row"><span>核定金额</span><b class="v-money"><?= $claim['approved_amount'] !== null ? '¥' . number_format((float) $claim['approved_amount'], 2) : '待核定' ?></b></div>
            <div class="hero-address__row"><span>提交时间</span><b><?= View::e(substr((string) $claim['created_at'], 0, 16)) ?></b></div>
            <div class="hero-address__row"><span>处理时间</span><b><?= View::e($claim['handled_at'] ? substr((string) $claim['handled_at'], 0, 16) : '未处理') ?></b></div>
        </div>
    </section>

    <section class="panel">
        <h3 class="section-head"><span class="badge badge--violet">处理意见</span><span class="rule"></span></h3>
        <?php if (($claim['handle_remark'] ?? '') === ''): ?>
            <div class="empty-state">客服尚未给出处理意见，请耐心等待。</div>
        <?php else: ?>
            <p style="line-height:1.9"><?= nl2br(View::e((string) $claim['handle_remark'])) ?></p>
        <?php endif; ?>
        <p class="form-hint" style="margin-top:12px">赔付通过后退回账户余额。</p>
        <div class="adm-toolbar" style="margin-bottom:0">
            <a class="btn btn--ghost btn--sm" href="/user/finance/flows">查看资金流水</a>
            <a class="btn btn--ghost btn--sm" href="/user/tickets/create">联系客服</a>
        </div>
    </section>
</div>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--orange">问题描述</span><span class="rule"></span></h3>
    <p style="line-height:1.9"><?= nl2br(View::e((string) $claim['description'])) ?></p>

    <?php if ($images !== []): ?>
        <div class="card-grid card-grid--4" style="margin-top:14px">
            <?php foreach ($images as $url): ?>
                <article class="card" style="padding:12px">
                    <img class="zoomable" src="<?= View::e((string) $url) ?>" data-zoom="<?= View::e((string) $url) ?>"
                         alt="理赔凭证" style="width:100%;height:140px;object-fit:cover;border-radius:8px">
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
