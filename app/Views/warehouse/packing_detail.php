<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/packing_detail.php
 * @description 打包作业页：包裹清单与库位（拣货核对）→ 录入实重箱规 → 确认打包并重算运费
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $waybill
 * @var array $packages
 * @var float $sumActual
 * @var float $sumVolume
 * @var float $volumeDivisor 体积重系数（取自渠道，前端预览与后端结算保持一致）
 * @var array $perms
 */
$addr = json_decode((string) $waybill['dest_address'], true) ?: [];
$addrLine = trim(implode(' ', array_filter([
    (string) ($addr['address1'] ?? ''), (string) ($addr['address2'] ?? ''),
    (string) ($addr['city'] ?? ''), (string) ($addr['state'] ?? ''), (string) ($addr['zip_code'] ?? ''),
])));
$status = (int) $waybill['status'];
$step = Status::waybillStep($status);
?>
<div class="adm-toolbar">
    <a class="btn btn--ghost btn--sm" href="/warehouse/packing">‹ 返回列表</a>
    <span class="badge badge--<?= Status::waybillTone($status) ?>" id="pk-status-badge"><?= View::e(Status::waybill($status)) ?></span>
    <?= View::badge([1 => '原箱转运', 2 => '合箱转运', 3 => '分箱转运'][(int) $waybill['bill_type']] ?? '—', 'indigo') ?>
    <?= View::badge($waybill['waybill_no'], 'purple', 'mono') ?>
</div>

<div class="wh-steps" id="pk-steps">
    <?php foreach (Status::WAYBILL_STEPS as $i => $label): ?>
        <span class="wh-step<?= $i < $step ? ' is-done' : ($i === $step ? ' is-active' : '') ?>" data-step="<?= $i ?>"><?= View::e($label) ?></span>
    <?php endforeach; ?>
</div>

<div class="site-grid">
    <section class="panel">
        <h3 class="section-head"><span class="badge badge--blue">运单信息</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>运单号</span><b class="v-code mono"><?= View::e($waybill['waybill_no']) ?></b></div>
            <div class="hero-address__row"><span>用户</span><b><?= View::e((string) $waybill['user_no']) ?> <?= View::e((string) ($waybill['username'] ?? $waybill['nickname'] ?? '')) ?></b></div>
            <div class="hero-address__row"><span>收件人</span><b><?= View::e((string) $waybill['receiver_name']) ?>（<?= View::e((string) $waybill['receiver_phone']) ?>）</b></div>
            <div class="hero-address__row"><span>目的国</span><?= View::badge((string) $waybill['dest_country'], 'cyan', 'mono') ?></div>
            <div class="hero-address__row"><span>收货地址</span><b><?= View::e($addrLine !== '' ? $addrLine : '—') ?></b></div>
            <div class="hero-address__row"><span>物流渠道</span><b><?= View::e((string) ($waybill['channel_name'] ?? '—')) ?></b></div>
            <div class="hero-address__row"><span>运费</span><b class="v-money"><?= $waybill['total_amount'] !== null ? '¥' . number_format((float) $waybill['total_amount'], 2) : '未计费' ?></b></div>
            <div class="hero-address__row"><span>支付时间</span><b id="pk-paid-at"><?= View::e($waybill['paid_at'] !== null ? substr((string) $waybill['paid_at'], 0, 16) : '未支付') ?></b></div>
            <div class="hero-address__row"><span>用户备注</span><b><?= View::e((string) ($waybill['remark'] ?? '') ?: '—') ?></b></div>
        </div>
    </section>

    <section class="panel">
        <h3 class="section-head"><span class="badge badge--orange">包裹清单（<?= count($packages) ?> 件）</span><span class="rule"></span></h3>
        <?php if ($packages === []): ?>
            <div class="empty-state">该运单没有关联包裹，无法打包。</div>
        <?php else: ?>
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th>包裹编号</th><th>商品</th><th>库位（拣货）</th><th>重量</th><th>体积重</th></tr></thead>
                    <tbody>
                    <?php foreach ($packages as $p): ?>
                        <tr>
                            <td class="mono"><?= View::e($p['package_no']) ?></td>
                            <td><?= View::e($p['item_name']) ?> ×<?= (int) $p['item_qty'] ?>
                                <br><small><?= View::e(Status::PACKAGE_CATEGORY[(int) $p['category']] ?? '') ?></small></td>
                            <td><?= View::e((string) ($p['location_code'] ?? '') ?: '未上架') ?></td>
                            <td class="nowrap"><?= $p['weight'] !== null ? number_format((float) $p['weight'], 3) . ' kg' : '—' ?></td>
                            <td class="nowrap"><?= $p['volume_weight'] !== null ? number_format((float) $p['volume_weight'], 3) . ' kg' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="form-hint">
                包裹重量合计 <b class="v-measure"><?= number_format($sumActual, 3) ?> kg</b> ·
                体积重合计 <b class="v-measure"><?= number_format($sumVolume, 3) ?> kg</b>
                （仅供参考，最终以本次打包实测为准）
            </p>
        <?php endif; ?>
    </section>
</div>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--cyan">打包拍照</span><span class="rule"></span></h3>
    <?php if (!empty($perms['wh.packing'])): ?>
        <div class="form-row" style="align-items:flex-end">
            <div class="wh-field" style="max-width:420px">
                <label class="form-label">选择图片（JPG / PNG / WebP，单张不超过 5MB）</label>
                <input class="form-input" type="file" id="pk-file" accept="image/jpeg,image/png,image/webp" capture="environment">
            </div>
            <div class="wh-field">
                <button class="btn btn--primary" type="button" onclick="uploadPackingPhoto()">上传打包照</button>
            </div>
        </div>
    <?php endif; ?>
    <div class="card-grid card-grid--4" id="pk-photo-grid" style="margin-top:12px" <?= $photos === [] ? 'hidden' : '' ?>>
        <?php foreach ($photos as $photo): ?>
            <div class="card" style="padding:8px">
                <img class="zoomable" src="<?= View::e($photo['url']) ?>" data-zoom="<?= View::e($photo['url']) ?>"
                     alt="打包照" style="width:100%;border-radius:10px;display:block">
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($status === 3 && !empty($perms['wh.packing']) && $packages !== []): ?>
<section class="panel" id="pk-panel" data-panel-role="packing">
    <h3 class="section-head"><span class="badge badge--emerald">打包测量</span><span class="rule"></span></h3>
    <p class="form-hint" style="margin-bottom:14px">
        按「实重与体积重取大」重算运费，差额自动多退少补，并转入「待出库」。
    </p>
    <div class="form-row">
        <div class="wh-field" style="max-width:200px">
            <label class="form-label">打包实重 kg</label>
            <input class="form-input wh-num" type="number" step="0.001" min="0.001" id="pk-weight" placeholder="0.000">
        </div>
        <div class="wh-field" style="max-width:170px">
            <label class="form-label">箱规长 cm</label>
            <input class="form-input wh-num" type="number" step="0.1" min="0.1" id="pk-length" placeholder="0.0">
        </div>
        <div class="wh-field" style="max-width:170px">
            <label class="form-label">箱规宽 cm</label>
            <input class="form-input wh-num" type="number" step="0.1" min="0.1" id="pk-width" placeholder="0.0">
        </div>
        <div class="wh-field" style="max-width:170px">
            <label class="form-label">箱规高 cm</label>
            <input class="form-input wh-num" type="number" step="0.1" min="0.1" id="pk-height" placeholder="0.0">
        </div>
        <div class="wh-field" style="max-width:200px">
            <label class="form-label">打包体积重（自动）</label>
            <div class="form-input" style="height:50px;line-height:50px;background:#f8fafc" id="pk-volume">待录入箱规</div>
        </div>
    </div>
    <div class="wh-field">
        <label class="form-label">打包备注（写入运单后台备注）</label>
        <input class="form-input" id="pk-remark" maxlength="200" placeholder="如：加固纸箱 / 合并原箱">
    </div>
    <button class="btn btn--primary wh-btn-lg" type="button" onclick="submitPacking()">确认打包并计算运费</button>
</section>
<?php else: ?>
<section class="panel">
    <div class="empty-state"><?= View::e(Status::packingBlockedNote($status, (float) ($waybill['due_amount'] ?? 0))) ?></div>
</section>
<?php endif; ?>

<script>
/**
 * 运单状态探针：用户支付运费后本页不必整页刷新，进度条、状态徽标与作业区自动跟进
 * 仅在状态变化时改 DOM，未变化不做任何写入，避免干扰正在录入的箱规
 */
var PK_ID = <?= (int) $waybill['id'] ?>;
var PK_STATUS = <?= $status ?>;

function applyPackingProgress(d) {
    var badge = document.getElementById('pk-status-badge');
    if (badge) { badge.textContent = d.status_text; badge.className = 'badge badge--' + d.tone; }
    document.querySelectorAll('#pk-steps .wh-step').forEach(function (el) {
        var i = parseInt(el.dataset.step, 10) || 0;
        el.classList.toggle('is-done', i < d.step);
        el.classList.toggle('is-active', i === d.step);
    });
    var paid = document.getElementById('pk-paid-at');
    if (paid) { paid.textContent = d.paid_at || '未支付'; }
    var panel = document.querySelector('#pk-panel[data-panel-role="packing"]');
    if (panel && !d.can_pack) {
        panel.innerHTML = '<h3 class="section-head"><span class="badge badge--emerald">打包测量</span><span class="rule"></span></h3>'
            + '<div class="empty-state"></div>';
        panel.querySelector('.empty-state').textContent = d.panel_note;
    }
}

async function pollPackingStatus() {
    try {
        var res = await App.get('/warehouse/packing/status?id=' + PK_ID);
        if (!res || !res.success || res.data.status === PK_STATUS) { return; }
        PK_STATUS = res.data.status;
        applyPackingProgress(res.data);
        Notify.info('运单状态已更新为「' + res.data.status_text + '」');
        refreshTodoBadges();
    } catch (err) {
        // 探测失败不影响作业，下一轮继续
    }
}

setInterval(pollPackingStatus, 5000);
// 后台标签页切回前台时立即探测一次，避免显示停留在离开前的旧状态
document.addEventListener('visibilitychange', function () {
    if (!document.hidden) { pollPackingStatus(); }
});

['length', 'width', 'height'].forEach(function (f) {
    var el = document.getElementById('pk-' + f);
    if (el) { el.addEventListener('input', updatePkVolume); }
});
function updatePkVolume() {
    var l = parseFloat(val('pk-length')) || 0;
    var w = parseFloat(val('pk-width')) || 0;
    var h = parseFloat(val('pk-height')) || 0;
    document.getElementById('pk-volume').textContent =
        (l > 0 && w > 0 && h > 0) ? (l * w * h / <?= (float) $volumeDivisor ?>).toFixed(3) + ' kg' : '待录入箱规';
}

/** 上传打包照：挂到箱内每个包裹上，成功后只插入新卡片，不刷新页面（规范 §2.5） */
async function uploadPackingPhoto() {
    var input = document.getElementById('pk-file');
    if (!input || !input.files || !input.files[0]) { Notify.warning('请先选择图片'); return; }
    var file = input.files[0];
    if (file.size > 5 * 1024 * 1024) { Notify.warning('图片不能超过 5MB'); input.value = ''; return; }

    var form = new FormData();
    form.append('file', file);
    form.append('waybill_id', <?= (int) $waybill['id'] ?>);
    form.append('_token', App.csrf());

    var handle = Notify.loading('上传中…');
    try {
        var res = await fetch('/warehouse/packing/upload', {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': App.csrf() },
            body: form
        });
        var data = await res.json();
        if (handle && handle.close) { handle.close(); }
        if (!data.success) { Notify.error(data.message || '上传失败'); return; }
        var grid = document.getElementById('pk-photo-grid');
        grid.hidden = false;
        var card = document.createElement('div');
        card.className = 'card';
        card.style.padding = '8px';
        card.innerHTML = '<img class="zoomable" src="' + data.data.url + '" data-zoom="' + data.data.url + '"'
            + ' alt="打包照" style="width:100%;border-radius:10px;display:block">';
        grid.appendChild(card);
        input.value = '';
        Notify.success('打包照片已上传');
    } catch (err) {
        if (handle && handle.close) { handle.close(); }
        Notify.error('上传失败，请稍后重试');
    }
}

function submitPacking() {    var weight = parseFloat(val('pk-weight')) || 0;
    var l = parseFloat(val('pk-length')) || 0;
    var w = parseFloat(val('pk-width')) || 0;
    var h = parseFloat(val('pk-height')) || 0;
    if (!(weight > 0)) { Notify.warning('请填写打包实重'); return; }
    if (!(l > 0 && w > 0 && h > 0)) { Notify.warning('请填写箱规长宽高'); return; }

    var billable = Math.max(weight, l * w * h / 5000).toFixed(3);
    admConfirm('确认打包完成？计费重量 ' + billable + ' kg（实重与体积重取大）。系统将按此重算运费，'
        + '与预扣不一致时会自动从用户余额补扣或退还差额，完成后运单转为「待出库」。',
        async function () {
            var res = await App.post('/warehouse/packing/save', {
                id: <?= (int) $waybill['id'] ?>, actual_weight: weight,
                length: l, width: w, height: h, remark: val('pk-remark').trim()
            });
            App.handle(res);
            if (!res.success) { return; }
            // 就地切换为「已完成打包」，不跳回列表整页刷新（规范 §2.5）
            // 同步本地状态：打包后或转「待补款」或转「待出库」，避免下一轮探测再覆盖此处的作业结果
            PK_STATUS = res.data.pending_pay ? 2 : 4;
            var panel = document.getElementById('pk-panel');
            if (panel) {
                panel.innerHTML = '<h3 class="section-head"><span class="badge badge--emerald">打包测量</span><span class="rule"></span></h3>'
                    + '<div class="empty-state"></div>';
                panel.querySelector('.empty-state').textContent = res.data.pending_pay
                    ? '该运单已完成打包测重，需向用户补收差额 ¥' + Number(res.data.due_amount).toFixed(2) + '，用户补缴后即可出库。'
                    : '该运单已完成打包测重，计费重量 ' + res.data.billable_weight + ' kg，运费 ¥'
                        + Number(res.data.total_amount).toFixed(2) + '，运单已转「待出库」，可继续办理出库交接。';
            }
        }, { title: '确认打包', confirmText: '确认打包', danger: true, level: 'L2' });
}
</script>
