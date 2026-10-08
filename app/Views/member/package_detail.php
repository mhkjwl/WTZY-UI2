<?php declare(strict_types=1);
/**
 * @file app/Views/member/package_detail.php
 * @description 会员中心：包裹详情（信息 + 称重尺寸 + 照片 + 异常记录 + 凭证上传）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-16
 */

use App\Core\View;
use App\Enums\Status;
use App\Services\UploadService;

/**
 * @var array $package
 * @var array|null $waybill 包裹最近一次关联的转运运单（无则未提交转运）
 * @var array $photos
 * @var array $abnormals
 * @var array|null $pendingDisposal 进行中的退运/销毁申请
 * @var array $disposalTypes
 * @var array|null $storageInfo 免费仓储与超期信息
 */
$s = (int) $package['status'];
$canClaim = in_array($s, [2, 3], true);
$canTransfer = $s === 4;
$canDispose = $s >= 4 && $s <= 9 && $pendingDisposal === null;
$paidAt = ($waybill !== null && ($waybill['paid_at'] ?? null)) ? substr((string) $waybill['paid_at'], 0, 16) : '';
?>
<div class="adm-toolbar">
    <a class="btn btn--ghost btn--sm" href="/user/packages">‹ 返回列表</a>
    <span class="badge badge--<?= Status::packageTone($s) ?>"><?= View::e(Status::package($s)) ?></span>
    <?php if ($pendingDisposal !== null): ?>
        <span class="badge badge--<?= Status::abnormalTone((int) $pendingDisposal['abnormal_type']) ?>"><?= Status::ABNORMAL_TYPE[(int) $pendingDisposal['abnormal_type']] ?? '处置申请' ?> · 审核中</span>
    <?php elseif ((int) $package['is_abnormal'] === 1): ?><span class="badge badge--orange">异常件</span><?php endif; ?>
    <?php if ($canClaim): ?>
        <button class="btn btn--primary btn--sm" style="margin-left:auto"
                onclick="admAction('/user/packages/claim', {id: <?= (int) $package['id'] ?>}, '确认认领该包裹？', {confirmText: '确认认领'})">认领包裹</button>
    <?php elseif ($canTransfer): ?>
        <a class="btn btn--primary btn--sm" style="margin-left:auto" href="/user/waybills/create?package=<?= (int) $package['id'] ?>">提交转运</a>
    <?php endif; ?>
    <?php if ($pendingDisposal !== null): ?>
        <button class="btn btn--ghost btn--sm" <?= $canClaim || $canTransfer ? '' : 'style="margin-left:auto"' ?>
                onclick="cancelDisposal(<?= (int) $package['id'] ?>, '<?= View::e(Status::ABNORMAL_TYPE[(int) $pendingDisposal['abnormal_type']] ?? '处置') ?>')">取消申请</button>
    <?php endif; ?>
</div>

<div class="site-grid">
    <section class="panel">
        <h3 class="section-head"><span class="badge badge--blue">包裹信息</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>包裹编号</span><b class="v-code mono"><?= View::e($package['package_no']) ?></b></div>
            <div class="hero-address__row"><span>预报单号</span><b class="v-code mono"><?= View::e((string) ($package['forecast_no'] ?? '—')) ?></b></div>
            <div class="hero-address__row"><span>快递单号</span><b class="v-code mono"><?= View::e($package['tracking_no'] ?: '—') ?></b></div>
            <div class="hero-address__row"><span>快递公司</span><b><?= View::e($package['express_company'] ?: '—') ?></b></div>
            <div class="hero-address__row"><span>商品</span><b><?= View::e($package['item_name']) ?> ×<?= (int) $package['item_qty'] ?></b></div>
            <div class="hero-address__row"><span>类别</span><?= View::badge(Status::PACKAGE_CATEGORY[(int) $package['category']] ?? '其他', 'purple') ?></div>
            <div class="hero-address__row"><span>申报价值</span><b class="v-money"><?= View::e((string) $package['currency']) ?> <?= number_format((float) $package['declared_value'], 2) ?></b></div>
            <div class="hero-address__row"><span>所属仓库</span><b><?= View::e((string) ($package['warehouse_name'] ?? '—')) ?></b></div>
            <div class="hero-address__row"><span>库位</span><b><?= View::e((string) ($package['location_code'] ?? '未上架')) ?></b></div>
            <div class="hero-address__row"><span>转运运单</span>
                <?php if ($waybill !== null): ?>
                    <b><a class="v-code mono" href="/user/waybills/<?= (int) $waybill['id'] ?>"><?= View::e((string) $waybill['waybill_no']) ?></a></b>
                <?php else: ?>
                    <b>未提交转运</b>
                <?php endif; ?>
            </div>
            <div class="hero-address__row"><span>支付时间</span><b><?= View::e($paidAt !== '' ? $paidAt : '未支付') ?></b></div>
            <div class="hero-address__row"><span>备注</span><b><?= View::e((string) ($package['remark'] ?? '—')) ?></b></div>
        </div>
    </section>

    <section class="panel">
        <h3 class="section-head"><span class="badge badge--violet">称重与计量</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>实际重量</span><b class="v-measure"><?= $package['weight'] !== null ? number_format((float) $package['weight'], 3) . ' kg' : '未称重' ?></b></div>
            <div class="hero-address__row"><span>尺寸</span><b class="v-measure">
                <?= $package['length'] !== null
                    ? number_format((float) $package['length'], 1) . ' × ' . number_format((float) $package['width'], 1) . ' × ' . number_format((float) $package['height'], 1) . ' cm'
                    : '未测量' ?></b></div>
            <div class="hero-address__row"><span>体积重</span><b class="v-measure"><?= $package['volume_weight'] !== null ? number_format((float) $package['volume_weight'], 3) . ' kg' : '—' ?></b></div>
            <div class="hero-address__row"><span>仓储费</span><b class="v-money">¥<?= number_format((float) $package['storage_fee'], 2) ?></b></div>
            <?php if ($storageInfo !== null): ?>
                <div class="hero-address__row"><span>免费仓储至</span>
                    <b class="<?= (int) $storageInfo['overdue_days'] > 0 ? 'v-money' : '' ?>">
                        <?= View::e($storageInfo['due_at']) ?>（<?= (int) $storageInfo['free_days'] ?> 天）
                        <?php if ((int) $storageInfo['overdue_days'] > 0): ?>
                            · 已超期 <?= (int) $storageInfo['overdue_days'] ?> 天
                        <?php endif; ?>
                    </b></div>
                <?php if ((float) $storageInfo['est_fee'] > 0): ?>
                    <div class="hero-address__row"><span>预估超期仓储费</span><b class="v-money">¥<?= number_format((float) $storageInfo['est_fee'], 2) ?></b></div>
                <?php endif; ?>
            <?php endif; ?>
            <div class="hero-address__row"><span>到仓时间</span><b><?= View::e($package['inbound_at'] ? substr((string) $package['inbound_at'], 0, 16) : '未到仓') ?></b></div>
            <div class="hero-address__row"><span>认领时间</span><b><?= View::e($package['claimed_at'] ? substr((string) $package['claimed_at'], 0, 16) : '未认领') ?></b></div>
        </div>
        <p class="form-hint">计费重量取实重与体积重较大者；超出免费仓储期（含会员加成）后按件/天计收超期费用。</p>
    </section>
</div>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--emerald">包裹照片与凭证</span><span class="rule"></span>
        <label class="btn btn--ghost" style="cursor:pointer">
            上传凭证
            <input type="file" id="pf-file" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="uploadPhoto()">
        </label>
    </h3>
    <?php if ($photos === []): ?>
        <div class="empty-state" id="photo-empty">暂无照片。包裹入库后仓库会上传称重与开箱照片；您也可以上传发票或订单截图。</div>
    <?php endif; ?>
    <div class="card-grid card-grid--4" id="photo-grid" <?= $photos === [] ? 'hidden' : '' ?>>
        <?php foreach ($photos as $photo): ?>
            <article class="card" style="padding:12px">
                <img class="zoomable" src="<?= View::e($photo['url']) ?>" data-zoom="<?= View::e($photo['url']) ?>"
                     alt="包裹照片" style="width:100%;height:140px;object-fit:cover;border-radius:8px">
                <p class="form-hint" style="margin-top:8px">
                    <?= View::e(UploadService::photoTypeLabel((int) $photo['photo_type'])) ?>
                    · <?= View::e(substr((string) $photo['created_at'], 0, 10)) ?>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--orange">异常与处置记录</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>类型</th><th>说明</th><th>状态</th><th>处理结果</th><th class="nowrap">提交时间</th></tr></thead>
            <tbody id="abnormal-body">
            <?php if ($abnormals === []): ?>
                <tr><td colspan="5"><div class="empty-state">暂无异常记录。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($abnormals as $a): ?>
                <tr>
                    <td><?= View::badge(Status::ABNORMAL_TYPE[(int) $a['abnormal_type']] ?? '其他', Status::abnormalTone((int) $a['abnormal_type'])) ?></td>
                    <td><?= View::e($a['description']) ?></td>
                    <td><span class="badge badge--<?= Status::abnormalStatusTone((int) $a['status']) ?>"><?= View::e(Status::ABNORMAL_STATUS[(int) $a['status']] ?? '未知') ?></span></td>
                    <td><?= View::e((string) ($a['handle_result'] ?? '—')) ?></td>
                    <td class="nowrap"><?= View::e(substr((string) $a['created_at'], 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($s >= 4 && $s <= 9): ?>
        <h4 class="section-head" style="margin-top:18px"><span class="badge badge--gray">发起处置申请</span><span class="rule"></span></h4>
        <div class="form-row" id="dp-form" <?= $pendingDisposal === null ? '' : 'hidden' ?>>
            <div class="form-field"><label class="form-label">申请类型</label>
                <select class="form-input" id="dp-type">
                    <?php foreach ($disposalTypes as $code => $label): ?>
                        <option value="<?= $code ?>"><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-field"><label class="form-label">申请原因</label>
                <input class="form-input" id="dp-reason" maxlength="200" placeholder="请说明原因"></div>
        </div>
        <?php if ($pendingDisposal !== null): ?>
            <p class="form-hint" id="dp-pending-note">已有<?= View::e(Status::ABNORMAL_TYPE[(int) $pendingDisposal['abnormal_type']] ?? '处置') ?>审核中，如需变更请先取消原申请。</p>
        <?php endif; ?>
        <button class="btn btn--primary" type="button" id="dp-submit" <?= $pendingDisposal === null ? '' : 'hidden' ?> onclick="submitDisposal()">提交申请</button>
    <?php endif; ?>
</section>
<script>
async function uploadPhoto() {
    var input = document.getElementById('pf-file');
    if (!input.files || !input.files[0]) { return; }
    var file = input.files[0];
    if (file.size > 5 * 1024 * 1024) { Notify.warning('图片不能超过 5MB'); input.value = ''; return; }

    var form = new FormData();
    form.append('file', file);
    form.append('package_id', '<?= (int) $package['id'] ?>');
    form.append('_token', (document.querySelector('meta[name=csrf-token]') || {}).content || '');

    var handle = Notify.loading('上传中…');
    try {
        var res = await fetch('/user/forecast/upload', {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': (document.querySelector('meta[name=csrf-token]') || {}).content || '' },
            body: form
        });
        var data = await res.json();
        if (handle && handle.close) { handle.close(); }
        if (data.success) {
            // 只把新卡片插入照片区，不整页刷新
            var grid = document.getElementById('photo-grid');
            var empty = document.getElementById('photo-empty');
            if (empty) { empty.remove(); }
            if (grid) {
                grid.hidden = false;
                var card = document.createElement('article');
                card.className = 'card';
                card.style.padding = '12px';
                card.innerHTML = '<img class="zoomable" src="' + data.data.url + '" data-zoom="' + data.data.url + '"'
                    + ' alt="包裹照片" style="width:100%;height:140px;object-fit:cover;border-radius:8px">'
                    + '<p class="form-hint" style="margin-top:8px">用户凭证 · 刚刚</p>';
                grid.appendChild(card);
            }
            Notify.success('凭证已上传');
        } else {
            Notify.error(data.message || '上传失败');
        }
    } catch (err) {
        if (handle && handle.close) { handle.close(); }
        Notify.error('上传失败，请稍后重试');
    }
    input.value = '';
}

function cancelDisposal(id, label) {
    admConfirm('确定取消「' + label + '」申请吗？取消后仓库将不再处理该申请。', async function () {
        var res = await App.post('/user/packages/disposal/cancel', { id: id });
        App.handle(res, '申请已取消');
        if (!res.success) { return; }
        // 局部更新：撤掉审核中徽标与取消按钮、恢复申请表单、记录行就地标注已关闭（规范 §2.5）
        var badges = document.querySelectorAll('.adm-toolbar .badge');
        badges.forEach(function (b) {
            if (b.textContent.indexOf('审核中') >= 0) { b.remove(); }
        });
        var cancelBtn = document.querySelector('.adm-toolbar button[onclick^="cancelDisposal"]');
        if (cancelBtn) { cancelBtn.remove(); }
        var form = document.getElementById('dp-form');
        var submit = document.getElementById('dp-submit');
        if (form) { form.hidden = false; }
        if (submit) { submit.hidden = false; }
        var note = document.getElementById('dp-pending-note');
        if (note) { note.remove(); }
        var body = document.getElementById('abnormal-body');
        if (body) {
            var emptyRow = body.querySelector('td[colspan]');
            if (emptyRow) { emptyRow.closest('tr').remove(); }
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + label + '</td><td></td>'
                + '<td><span class="badge badge--gray">已关闭</span></td><td>用户取消申请</td>'
                + '<td class="nowrap">刚刚</td>';
            tr.children[1].textContent = '用户取消';
            body.insertBefore(tr, body.firstChild);
        }
    }, { title: '取消' + label + '申请', confirmText: '确认取消', danger: false });
}

function submitDisposal() {
    var reason = val('dp-reason').trim();
    if (reason.length < 4) { Notify.warning('请填写申请原因（至少 4 个字）'); return; }
    var type = val('dp-type');
    var label = type === '6' ? '退运' : '销毁';
    admConfirm('确定提交「' + label + '」申请吗？提交后由仓库处理，'
        + (type === '7' ? '销毁后包裹将被废弃且不可恢复。' : '退运后包裹将退回寄件方。'), async function () {
        var res = await App.post('/user/packages/disposal', { id: <?= (int) $package['id'] ?>, type: type, reason: reason });
        App.handle(res, '申请已提交');
        if (!res.success) { return; }
        // 局部更新：插审核中徽标、换取消按钮、收起申请表单、异常表插记录（规范 §2.5）
        var toolbar = document.querySelector('.adm-toolbar');
        if (toolbar) {
            var badge = document.createElement('span');
            badge.className = 'badge badge--' + (type === '7' ? 'rose' : 'violet');
            badge.textContent = '申请' + label + ' · 审核中';
            toolbar.insertBefore(badge, toolbar.querySelector('[style*="margin-left:auto"], button, a:last-child'));
            var back = toolbar.querySelector('a[href$="/user/packages"]');
            if (back) { toolbar.insertBefore(badge, back.nextSibling); }
            var cancelBtn = document.createElement('button');
            cancelBtn.className = 'btn btn--ghost btn--sm';
            cancelBtn.style.marginLeft = 'auto';
            cancelBtn.textContent = '取消申请';
            cancelBtn.onclick = function () { cancelDisposal(<?= (int) $package['id'] ?>, label); };
            toolbar.appendChild(cancelBtn);
        }
        var form = document.getElementById('dp-form');
        var submitBtn = document.getElementById('dp-submit');
        if (form) { form.hidden = true; }
        if (submitBtn) { submitBtn.hidden = true; }
        var note = document.getElementById('dp-pending-note');
        if (note) { note.remove(); }
        var noteEl = document.createElement('p');
        noteEl.className = 'form-hint';
        noteEl.id = 'dp-pending-note';
        noteEl.textContent = '已有申请' + label + '审核中，如需变更请先取消原申请。';
        if (submitBtn && submitBtn.parentNode) { submitBtn.parentNode.insertBefore(noteEl, submitBtn); }
        var body = document.getElementById('abnormal-body');
        if (body) {
            var emptyRow = body.querySelector('td[colspan]');
            if (emptyRow) { emptyRow.closest('tr').remove(); }
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + label + '</td><td></td>'
                + '<td><span class="badge badge--orange">处理中</span></td><td>—</td>'
                + '<td class="nowrap">刚刚</td>';
            tr.children[1].textContent = reason;
            body.insertBefore(tr, body.firstChild);
        }
        var input = document.getElementById('dp-reason');
        if (input) { input.value = ''; }
    }, { title: label + '申请', confirmText: '确认提交', danger: true, level: 'L2' });
}
</script>
