<?php declare(strict_types=1);
/**
 * @file app/Views/member/coupons_mine.php
 * @description 我的券包局部渲染（领券后仅替换本区域）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array $mine
 */
$typeMap = [1 => '满减券', 2 => '折扣券', 3 => '运费券'];
$statusMap = [0 => ['未使用', 'green'], 1 => ['已使用', 'gray'], 2 => ['已过期', 'gray']];
$friendly = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '长期有效';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }
    return time() > $ts ? '已过期 ' . date('Y-m-d', $ts) : '有效至 ' . date('Y-m-d', $ts);
};
?>
<?php if ($mine === []): ?>
    <div class="empty-state">券包空空如也。下方「领券中心」可以领取当前开放的优惠券。</div>
<?php else: ?>
    <div class="tile-grid" style="grid-template-columns:repeat(3,1fr)">
        <?php foreach ($mine as $c): ?>
            <?php $st = $statusMap[(int) $c['status']] ?? ['未知', 'gray']; ?>
            <article class="tile tc-<?= (int) $c['status'] === 0 ? 'emerald' : 'slate' ?>" style="cursor:default">
                <div class="tile__title" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <?= View::e($c['name']) ?>
                    <span class="badge badge--<?= $st[1] ?>"><?= View::e($st[0]) ?></span>
                </div>
                <div class="tile__desc">
                    <div style="font-size:20px;font-weight:800;color:#4f46e5">
                        <?php if ((int) $c['type'] === 2): ?>
                            <?= number_format((float) $c['value'] * 10, 1) ?> 折
                        <?php else: ?>
                            ¥<?= number_format((float) $c['value'], 2) ?>
                        <?php endif; ?>
                    </div>
                    <div><?= View::e($typeMap[(int) $c['type']] ?? '优惠券') ?> · 满 ¥<?= number_format((float) $c['min_amount'], 2) ?> 可用</div>
                    <div><?= View::e($friendly($c['end_at'] === null ? null : (string) $c['end_at'])) ?></div>
                </div>
                <div class="tile__foot">
                    <span class="badge badge--gray mono"><?= View::e($c['code']) ?></span>
                    <?php if ((int) $c['status'] === 0): ?>
                        <a class="btn btn--primary btn--sm" href="/user/waybills/create">去使用</a>
                    <?php else: ?>
                        <span class="tile__arrow">·</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
