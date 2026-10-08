<?php declare(strict_types=1);
/**
 * @file app/Views/member/addresses.php
 * @description 会员中心：海外仓专属收货地址（下单时填写，凭识别码归属包裹）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array  $addresses
 * @var string $userCode
 */
?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">填写指引</span><span class="rule"></span></h3>
    <p class="form-hint" style="line-height:1.9">
        下单时把下方地址原样填写为<b>收货地址</b>，
        并在 <b>收件人姓名</b>末尾加上 <b class="mono" style="color:#4f46e5"><?= View::e($userCode) ?></b>，
        包裹到仓后自动归属到您的账户。
    </p>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--purple">我的海外仓地址</span><span class="rule"></span>
        <span class="badge badge--gray">共 <?= count($addresses) ?> 个仓库</span>
    </h3>

    <?php if ($addresses === []): ?>
        <div class="empty-state">尚未分配海外仓地址。请联系客服确认账户状态。</div>
    <?php endif; ?>

    <div class="tile-grid" style="grid-template-columns:repeat(2,1fr)">
        <?php foreach ($addresses as $addr): ?>
            <article class="tile tc-<?= (int) $addr['is_default'] === 1 ? 'blue' : 'slate' ?>" style="cursor:default">
                <div class="tile__title" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <?= View::e($addr['warehouse_name']) ?>
                    <span class="badge badge--gray mono"><?= View::e($addr['warehouse_code']) ?></span>
                    <?php if ((int) $addr['is_default'] === 1): ?><span class="badge badge--green">默认</span><?php endif; ?>
                    <?php if ((int) $addr['status'] !== 1): ?><span class="badge badge--red">已失效</span><?php endif; ?>
                </div>

                <div class="hero-address" data-copy-target="addr-<?= (int) $addr['id'] ?>">
                    <div class="hero-address__row"><span>收件人</span><b><?= View::e($addr['full_name']) ?></b></div>
                    <div class="hero-address__row"><span>识别码</span><b class="v-code hero-address__code"><?= View::e($addr['user_code']) ?></b></div>
                    <div class="hero-address__row"><span>电话</span><b><?= View::e($addr['phone']) ?></b></div>
                    <div class="hero-address__row"><span>地址</span><b><?= View::e($addr['address']) ?></b></div>
                    <div class="hero-address__row"><span>城市</span><b><?= View::e($addr['city'] . ', ' . $addr['state'] . ' ' . $addr['zip_code']) ?></b></div>
                    <div class="hero-address__row"><span>国家</span><b><?= View::e($addr['country']) ?></b></div>
                </div>
                <pre id="addr-<?= (int) $addr['id'] ?>" hidden><?= View::e($addr['full_name'] . "\n" . $addr['user_code'] . "\n" . $addr['address'] . "\n" . $addr['city'] . ', ' . $addr['state'] . ' ' . $addr['zip_code'] . "\n" . $addr['country'] . "\n" . $addr['phone']) ?></pre>

                <div class="tile__foot">
                    <button class="btn btn--ghost btn--sm" onclick="copyAddress(<?= (int) $addr['id'] ?>)">复制地址</button>
                    <span class="tile__arrow">·</span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<script>
async function copyAddress(id) {
    var text = document.getElementById('addr-' + id).textContent;
    try {
        await navigator.clipboard.writeText(text);
        Notify.success('地址已复制到剪贴板');
    } catch (err) {
        // 剪贴板权限不可用时退化为选中文本，用户可手动复制
        var range = document.createRange();
        range.selectNodeContents(document.getElementById('addr-' + id));
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        Notify.warning('已选中地址文本，请按 Ctrl+C 复制');
    }
}
</script>
