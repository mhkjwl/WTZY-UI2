<?php declare(strict_types=1);
/**
 * @file app/Views/member/coupons.php
 * @description 会员中心：我的优惠券与领券中心
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $mine
 * @var array $receivable
 * @var array $counts
 */
$typeMap = [1 => '满减券', 2 => '折扣券', 3 => '运费券'];
$friendly = static function (?string $value): string {
    if ($value === null || $value === '') { return '长期有效'; }
    $ts = strtotime($value);
    if ($ts === false) { return $value; }
    return time() > $ts ? '已过期 ' . date('Y-m-d', $ts) : '有效至 ' . date('Y-m-d', $ts);
};
?>
<section class="astat-grid">
    <div class="astat astat--emerald"><div class="astat__icon">🎫</div>
        <div><div class="astat__value"><?= (int) ($counts[0] ?? 0) ?></div><div class="astat__label">未使用</div></div></div>
    <div class="astat astat--blue"><div class="astat__icon">✅</div>
        <div><div class="astat__value"><?= (int) ($counts[1] ?? 0) ?></div><div class="astat__label">已使用</div></div></div>
    <div class="astat astat--gray"><div class="astat__icon">⌛</div>
        <div><div class="astat__value"><?= (int) ($counts[2] ?? 0) ?></div><div class="astat__label">已过期</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">🎁</div>
        <div><div class="astat__value"><?= count($receivable) ?></div><div class="astat__label">可领取</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--green">我的券包</span><span class="rule"></span>
        <span class="badge badge--gray" id="usable-count">可用 <?= (int) ($counts[0] ?? 0) ?> 张</span>
    </h3>
    <div id="coupon-mine">
        <?= View::partial('member/coupons_mine', ['mine' => $mine]) ?>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--violet">领券中心</span><span class="rule"></span></h3>
    <?php if ($receivable === []): ?>
        <div class="empty-state">暂无可领取的优惠券，请留意公告。</div>
    <?php else: ?>
        <div class="tile-grid" style="grid-template-columns:repeat(3,1fr)">
            <?php foreach ($receivable as $c): ?>
                <?php $limitReached = (int) $c['mine_count'] >= (int) $c['per_user_limit']; ?>
                <article class="tile tc-violet" style="cursor:default">
                    <div class="tile__title"><?= View::e($c['name']) ?></div>
                    <div class="tile__desc">
                        <div style="font-size:20px;font-weight:800;color:#4f46e5">
                            <?php if ((int) $c['type'] === 2): ?>
                                <?= number_format((float) $c['value'] * 10, 1) ?> 折
                            <?php else: ?>
                                ¥<?= number_format((float) $c['value'], 2) ?>
                            <?php endif; ?>
                        </div>
                        <div><?= View::e($typeMap[(int) $c['type']] ?? '优惠券') ?> · 满 ¥<?= number_format((float) $c['min_amount'], 2) ?> 可用</div>
                        <div><?= View::e($c['end_at'] ? $friendly((string) $c['end_at']) : '长期有效') ?></div>
                        <div>每人限领 <?= (int) $c['per_user_limit'] ?> 张，已领 <?= (int) $c['mine_count'] ?> 张</div>
                    </div>
                    <div class="tile__foot">
                        <?php if ($limitReached): ?>
                            <span class="badge badge--gray">已领取</span>
                        <?php else: ?>
                            <button class="btn btn--primary btn--sm" onclick='receiveCoupon(<?= (int) $c['id'] ?>, <?= json_encode((string) $c['name'], JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>, this)'>立即领取</button>
                        <?php endif; ?>
                        <span class="tile__arrow">·</span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
async function refreshCouponMine() {
    var el = document.getElementById('coupon-mine');
    if (!el) { return; }
    try {
        var res = await fetch('/user/member/coupons/mine', { headers: { 'X-Requested-With': 'fetch' } });
        // 校验响应有效性，避免会话过期/出错时把登录页或错误页塞进券包区
        if (!res.ok || res.redirected) {
            Notify.error('登录状态已失效，请重新登录');
            return;
        }
        el.innerHTML = await res.text();
    } catch (err) {
        Notify.error('网络异常，券包未能刷新');
    }
}

function receiveCoupon(id, name, btn) {
    admConfirm('确定领取「' + name + '」吗？', async function () {
        var res = await App.post('/user/member/coupons/receive', { id: id });
        App.handle(res, '领取成功');
        if (!res.success) { return; }
        // 原地标记已领取并刷新券包区域，不整页刷新
        if (btn) {
            btn.replaceWith(Object.assign(document.createElement('span'), { className: 'badge badge--gray', textContent: '已领取' }));
        }
        var counter = document.getElementById('usable-count');
        if (counter) {
            var now = parseInt(counter.textContent.replace(/\D/g, ''), 10) || 0;
            counter.textContent = '可用 ' + (now + 1) + ' 张';
        }
        refreshCouponMine();
    }, { title: '领取优惠券', confirmText: '确认领取' });
}
</script>
