<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/abnormal.php
 * @description 异常处理：待处理异常件登记与处置（用户申请的退运/销毁在此执行）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 * @var array  $types
 * @var array  $disposals
 * @var array  $perms
 * @var array  $stats
 */
?>
<section class="stat-grid">
    <div class="stat-card stat-card--red"><span class="stat-card__value"><?= (int) ($stats[0] ?? 0) ?></span><span class="stat-card__label">待处理</span></div>
    <div class="stat-card stat-card--blue"><span class="stat-card__value"><?= (int) ($stats[1] ?? 0) ?></span><span class="stat-card__label">处理中</span></div>
    <div class="stat-card stat-card--green"><span class="stat-card__value"><?= (int) ($stats[2] ?? 0) ?></span><span class="stat-card__label">已解决</span></div>
    <div class="stat-card stat-card--purple"><span class="stat-card__value"><?= array_sum($stats) ?></span><span class="stat-card__label">异常总数</span></div>
</section>
<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--red">异常处理</span><span class="rule"></span>
        <span class="badge badge--gray">共 <?= (int) $total ?> 件</span>
    </h3>
    <p class="form-hint" style="margin-bottom:14px">退运 → 转「已退回」；销毁 → 转「已销毁」；仅处理则恢复在库。</p>
    <div class="adm-toolbar">
        <input class="form-input" id="abn-kw" placeholder="检索：包裹编号 / 快递单号 / 用户编号" value="<?= View::e($keyword) ?>">
        <button class="btn btn--primary" onclick="admSearch('/warehouse/abnormal', {kw: val('abn-kw')})">检索</button>
        <?php if (!empty($perms['wh.abnormal'])): ?>
            <button class="btn btn--ghost" style="margin-left:auto" onclick="openRegister()">+ 登记异常</button>
        <?php endif; ?>
    </div>
    <div class="table-wrap" data-table-wrap data-url="/warehouse/abnormal?kw=<?= urlencode($keyword) ?>">
        <?= View::partial('warehouse/abnormal_table', get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog" id="dlg-handle">
    <div class="adm-dialog__head">处理异常 · <span id="hd-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="hd-id">
        <div class="form-row">
            <div class="wh-field">
                <label class="form-label">异常类型</label>
                <input class="form-input" id="hd-type" readonly>
            </div>
            <div class="wh-field">
                <label class="form-label">处置方式</label>
                <select class="form-input" id="hd-disposal">
                <?php foreach ($disposals as $code => $label): ?>
                    <option value="<?= (int) $code ?>"><?= View::e($label) ?></option>
                <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="wh-field">
            <label class="form-label">异常说明</label>
            <textarea class="form-input" id="hd-desc" rows="2" readonly></textarea>
        </div>
        <div class="wh-field" id="hd-photos-wrap" hidden>
            <label class="form-label">异常照片</label>
            <div class="card-grid card-grid--4" id="hd-photos"></div>
        </div>
        <div class="wh-field">
            <label class="form-label">处理结果（用户端可见）</label>
            <textarea class="form-input" id="hd-result" rows="3" maxlength="500" placeholder="如：已与用户确认，缺件按申报价值赔付后重新打包"></textarea>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="submitHandle()">确认处理</button>
    </div>
</dialog>

<dialog class="adm-dialog" id="dlg-register">
    <div class="adm-dialog__head">登记异常</div>
    <div class="adm-dialog__body">
        <div class="form-row">
            <div class="wh-field">
                <label class="form-label">包裹编号</label>
                <input class="form-input mono" id="rg-package-no" placeholder="如 P20260912000000xxxx">
            </div>
            <div class="wh-field">
                <label class="form-label">异常类型</label>
                <select class="form-input" id="rg-type">
                    <?php foreach ($types as $code => $label): ?>
                        <option value="<?= (int) $code ?>"><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="wh-field">
            <label class="form-label">异常说明</label>
            <textarea class="form-input" id="rg-desc" rows="3" maxlength="500" placeholder="如：外箱进水，内件受潮"></textarea>
        </div>
        <div class="wh-field">
            <label class="form-label">异常照片（选填，最多 5 张）</label>
            <div class="adm-toolbar" style="margin-bottom:8px">
                <input class="form-input" type="file" id="rg-file" accept="image/*" style="max-width:240px">
                <button class="btn btn--ghost btn--sm" type="button" onclick="uploadAbnPhoto()">上传照片</button>
            </div>
            <div class="card-grid card-grid--4" id="rg-photos" hidden></div>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="submitRegister()">提交登记</button>
    </div>
</dialog>

<script>
document.getElementById('abn-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { admSearch('/warehouse/abnormal', {kw: this.value}); }
});

/** 本次登记已上传的异常照片（相对 URL），提交时随登记接口一并入库 */
var abnPhotos = [];

function openRegister() {
    document.getElementById('rg-package-no').value = '';
    document.getElementById('rg-desc').value = '';
    abnPhotos = [];
    renderAbnPhotos();
    document.getElementById('dlg-register').showModal();
}

function renderAbnPhotos() {
    var grid = document.getElementById('rg-photos');
    grid.innerHTML = '';
    grid.hidden = abnPhotos.length === 0;
    abnPhotos.forEach(function (url) {
        var card = document.createElement('div');
        card.className = 'card';
        card.style.padding = '8px';
        card.innerHTML = '<img class="zoomable" src="' + url + '" data-zoom="' + url + '"'
            + ' alt="异常照片" style="width:100%;height:110px;object-fit:cover;border-radius:8px;display:block">';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn--ghost btn--sm';
        btn.style.marginTop = '6px';
        btn.textContent = '移除';
        btn.onclick = function () { removeAbnPhoto(url); };
        card.appendChild(btn);
        grid.appendChild(card);
    });
}

function removeAbnPhoto(url) {
    abnPhotos = abnPhotos.filter(function (item) { return item !== url; });
    renderAbnPhotos();
}

/** 照片先落盘再随登记提交：登记前尚无异常单，故按包裹编号定位目标包裹 */
async function uploadAbnPhoto() {
    var input = document.getElementById('rg-file');
    if (!input || !input.files || input.files.length === 0) { Notify.warning('请选择要上传的图片'); return; }
    if (abnPhotos.length >= 5) { Notify.warning('最多上传 5 张照片'); return; }
    var packageNo = val('rg-package-no').trim();
    if (!packageNo) { Notify.warning('请先填写包裹编号'); return; }
    var form = new FormData();
    form.append('file', input.files[0]);
    form.append('package_no', packageNo);
    form.append('_token', App.csrf());
    var handle = Notify.loading('上传中…');
    try {
        var res = await fetch('/warehouse/abnormal/upload', {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': App.csrf() },
            body: form
        });
        var data = await res.json();
        if (handle && handle.close) { handle.close(); }
        if (!data.success) { Notify.error(data.message || '上传失败'); return; }
        abnPhotos.push(data.data.url);
        renderAbnPhotos();
        input.value = '';
        Notify.success('异常照片已上传');
    } catch (err) {
        if (handle && handle.close) { handle.close(); }
        Notify.error('上传失败，请稍后重试');
    }
}

function submitRegister() {
    var packageNo = val('rg-package-no').trim();
    var desc = val('rg-desc').trim();
    if (!packageNo) { Notify.warning('请填写包裹编号'); return; }
    if (desc.length < 4) { Notify.warning('请填写异常说明（至少 4 个字）'); return; }
    admConfirm('确认登记该异常？包裹会被标记为异常件。', async function () {
        var res = await App.post('/warehouse/abnormal/register-by-no', { package_no: packageNo, abnormal_type: val('rg-type'), description: desc, images: JSON.stringify(abnPhotos) });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-register').close(); admRefresh(); }
    }, { title: '登记异常', confirmText: '确认登记', danger: true });
}

function openHandle(id, type, desc, images) {
    document.getElementById('hd-id').value = id;
    document.getElementById('hd-type').value = type;
    document.getElementById('hd-desc').value = desc;
    document.getElementById('hd-result').value = '';
    var list = Array.isArray(images) ? images : [];
    var grid = document.getElementById('hd-photos');
    grid.innerHTML = '';
    list.forEach(function (url) {
        var card = document.createElement('div');
        card.innerHTML = '<img class="zoomable" src="' + url + '" data-zoom="' + url + '"'
            + ' alt="异常照片" style="width:100%;height:110px;object-fit:cover;border-radius:8px;display:block">';
        grid.appendChild(card);
    });
    document.getElementById('hd-photos-wrap').hidden = list.length === 0;
    document.getElementById('dlg-handle').showModal();
    document.getElementById('hd-result').focus();
}
function submitHandle() {
    var result = val('hd-result').trim();
    if (result.length < 4) { Notify.warning('请填写处理结果（至少 4 个字）'); return; }
    var disposal = val('hd-disposal');
    var label = disposal === '1' ? '执行退运' : (disposal === '2' ? '执行销毁' : '处理并恢复在库');
    var danger = disposal !== '0';
    admConfirm('确认按「' + label + '」处理该异常吗？' + (danger ? '包裹将转为终态，不可撤销。' : ''), async function () {
        var res = await App.post('/warehouse/abnormal/handle', { id: val('hd-id'), disposal: disposal, handle_result: result });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-handle').close(); admRefresh(); }
    }, { title: '处理异常', confirmText: '确认处理', danger: danger, level: danger ? 'L2' : 'L1' });
}
</script>
