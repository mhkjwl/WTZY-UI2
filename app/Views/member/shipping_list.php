<?php declare(strict_types=1);
/**
 * @file app/Views/member/shipping_list.php
 * @description 收货地址卡片列表局部渲染（保存/删除后仅替换本区域）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array $addresses
 */
?>
<?php if ($addresses === []): ?>
    <div class="empty-state">还没有收货地址。添加后在下单转运时可直接选用。</div>
<?php endif; ?>
<div class="tile-grid" style="grid-template-columns:repeat(3,1fr)">
    <?php foreach ($addresses as $a): ?>
        <article class="tile tc-<?= (int) $a['is_default'] === 1 ? 'emerald' : 'slate' ?>" style="cursor:default" data-addr="<?= (int) $a['id'] ?>">
            <div class="tile__title" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <?= View::e($a['receiver_name']) ?>
                <?php if ((int) $a['is_default'] === 1): ?><span class="badge badge--green">默认</span><?php endif; ?>
            </div>
            <div class="tile__desc">
                <?= View::e($a['receiver_phone']) ?><br>
                <?= View::e($a['address1']) ?><?= $a['address2'] ? '<br>' . View::e($a['address2']) : '' ?><br>
                <?= View::e($a['city']) ?><?= $a['district'] ? ' ' . View::e($a['district']) : '' ?>, <?= View::e($a['state']) ?> <?= View::e($a['zip_code']) ?><br>
                <?= View::e($a['country']) ?>
            </div>
            <div class="tile__foot">
                <button class="btn btn--ghost btn--sm"
                        onclick='openAddr(<?= json_encode($a, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS) ?>)'>编辑</button>
                <button class="btn btn--ghost btn--sm" onclick="deleteAddr(<?= (int) $a['id'] ?>)">删除</button>
            </div>
        </article>
    <?php endforeach; ?>
</div>
