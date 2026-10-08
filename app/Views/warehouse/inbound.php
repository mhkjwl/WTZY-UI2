<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/inbound.php
 * @description 收货入库：扫码定位 → 匹配归属 → 称重量尺拍照上架 → 确认入库（未匹配可登记无主件）
 * @author AI
 * @version 0.6
 * @date 2026-09-16
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 * @var array  $locations
 * @var array  $locTypes
 * @var array  $categories
 * @var int    $pendingInbound
 * @var array  $perms
 */
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">📥</div>
        <div><div class="astat__value"><?= (int) $pendingInbound ?></div><div class="astat__label">待收货（已预报）</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">🏷️</div>
        <div><div class="astat__value"><?= count($locations) ?></div><div class="astat__label">可用库位</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">📦</div>
        <div><div class="astat__value"><?= (int) $total ?></div><div class="astat__label">待收货列表</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--blue">扫描收货</span><span class="rule"></span>
        <?php if (!empty($perms['wh.inbound'])): ?>
            <button class="btn btn--primary" type="button" onclick="openManual()">+ 手动添加包裹</button>
        <?php endif; ?>
    </h3>
    <div class="wh-scan">
        <div class="wh-scan__field">
            <label class="form-label" for="wh-scan-input">快递单号（或包裹编号 / 预报单号）</label>
            <input class="wh-scan__input" id="wh-scan-input" autocomplete="off" autofocus
                   placeholder="扫描面单条码后回车">
        </div>
        <button class="btn btn--primary wh-btn-lg" type="button" onclick="doLookup()">查询匹配</button>
    </div>
    <p class="wh-scan__hint">
        面单无法扫码或需事后补录时，用「手动添加包裹」：填识别码自动归属用户，识别码匹配不到再确认是否登记无主件。
    </p>
    <div id="wh-lookup-result"></div>
</section>

<section class="panel" id="wh-unattributed-panel" hidden>
    <h3 class="section-head"><span class="badge badge--orange">登记无主件</span><span class="rule"></span></h3>
    <p class="form-hint" style="margin-bottom:14px">该单号无预报记录，将按无主件入库，可在「仓储管理」指定归属用户。</p>
    <div class="wh-field">
        <label class="form-label">快递单号</label>
        <input class="form-input" id="un-tracking" readonly>
    </div>
    <div class="form-row">
        <div class="wh-field" style="max-width:320px">
            <label class="form-label">快递公司</label>
            <input class="form-input" id="un-express" maxlength="50" placeholder="如 USPS / UPS">
        </div>
        <div class="wh-field" style="max-width:320px">
            <label class="form-label">商品名称</label>
            <input class="form-input" id="un-item" maxlength="100" placeholder="如 衣物 / 日用百货">
        </div>
        <div class="wh-field" style="max-width:160px">
            <label class="form-label">数量</label>
            <input class="form-input wh-num" type="number" id="un-qty" value="1" min="1" max="999">
        </div>
        <div class="wh-field" style="max-width:200px">
            <label class="form-label">物品类别</label>
            <select class="form-input" id="un-category">
                <?php foreach ($categories as $code => $label): ?>
                    <option value="<?= (int) $code ?>"><?= View::e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="wh-field" style="max-width:180px">
            <label class="form-label">实际重量 kg</label>
            <input class="form-input wh-num" type="number" step="0.001" min="0.001" id="un-weight" placeholder="0.000">
        </div>
        <div class="wh-field" style="max-width:160px">
            <label class="form-label">长 cm</label>
            <input class="form-input wh-num" type="number" step="0.1" min="0.1" id="un-length" placeholder="0.0">
        </div>
        <div class="wh-field" style="max-width:160px">
            <label class="form-label">宽 cm</label>
            <input class="form-input wh-num" type="number" step="0.1" min="0.1" id="un-width" placeholder="0.0">
        </div>
        <div class="wh-field" style="max-width:160px">
            <label class="form-label">高 cm</label>
            <input class="form-input wh-num" type="number" step="0.1" min="0.1" id="un-height" placeholder="0.0">
        </div>
    </div>
    <div class="wh-field">
        <label class="form-label">备注（面单信息、外观等）</label>
        <input class="form-input" id="un-remark" maxlength="200" placeholder="如：面单无识别码 / 外箱破损">
    </div>
    <div class="wh-field">
        <label class="form-label">包裹照片（可选，最多 5 张）</label>
        <input class="form-input" type="file" id="un-file" accept="image/jpeg,image/png,image/webp" capture="environment" onchange="pickPhotos('un')">
        <p class="form-hint">提交后自动上传；入库后仍可在仓储详情补传。</p>
        <div class="card-grid card-grid--4" id="un-photos" style="margin-top:10px" hidden></div>
    </div>
    <button class="btn btn--primary wh-btn-lg" type="button" onclick="submitUnattributed()">登记为无主件</button>
</section>

<dialog class="adm-dialog adm-dialog--lg" id="dlg-manual">
    <div class="adm-dialog__head">手动添加包裹</div>
    <div class="adm-dialog__body">
        <p class="form-hint">填识别码可直接归属到用户；识别码为空或匹配不到用户时，确认后按无主件登记。</p>
        <div class="form-row">
            <div class="wh-field"><label class="form-label">入库识别码</label>
                <input class="form-input mono" id="mn-code" maxlength="16" placeholder="面单上的识别码"></div>
            <div class="wh-field"><label class="form-label">快递单号 <b>*</b></label>
                <input class="form-input mono" id="mn-tracking" maxlength="64" placeholder="面单上的快递单号"></div>
            <div class="wh-field"><label class="form-label">快递公司</label>
                <input class="form-input" id="mn-express" maxlength="50" placeholder="如 USPS / UPS"></div>
        </div>
        <div class="form-row">
            <div class="wh-field"><label class="form-label">商品名称 <b>*</b></label>
                <input class="form-input" id="mn-item" maxlength="100" placeholder="如 衣物 / 日用百货"></div>
            <div class="wh-field" style="max-width:160px"><label class="form-label">件数</label>
                <input class="form-input wh-num" type="number" id="mn-qty" value="1" min="1" max="999"></div>
            <div class="wh-field"><label class="form-label">物品类别</label>
                <select class="form-input" id="mn-category">
                    <?php foreach ($categories as $code => $label): ?>
                        <option value="<?= (int) $code ?>"><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <div class="form-row">
            <div class="wh-field" style="max-width:180px"><label class="form-label">实际重量 kg <b>*</b></label>
                <input class="form-input wh-num" type="number" step="0.001" min="0.001" id="mn-weight" placeholder="0.000"></div>
            <div class="wh-field" style="max-width:130px"><label class="form-label">长 cm <b>*</b></label>
                <input class="form-input wh-num" type="number" step="0.1" min="0.1" id="mn-length"></div>
            <div class="wh-field" style="max-width:130px"><label class="form-label">宽 cm <b>*</b></label>
                <input class="form-input wh-num" type="number" step="0.1" min="0.1" id="mn-width"></div>
            <div class="wh-field" style="max-width:130px"><label class="form-label">高 cm <b>*</b></label>
                <input class="form-input wh-num" type="number" step="0.1" min="0.1" id="mn-height"></div>
        </div>
        <div class="wh-kv"><span>体积重（自动）</span><b id="mn-volume">待录入箱规</b></div>
        <div class="form-row" style="margin-top:10px">
            <div class="wh-field"><label class="form-label">上架库位（可稍后在仓储管理上架）</label>
                <select class="form-input" id="mn-location">
                    <option value="0">暂不上架（放入入库暂存区）</option>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= (int) $l['id'] ?>"><?= View::e((string) $l['code']) ?>（<?= View::e($locTypes[(int) $l['type']] ?? '库位') ?><?= (int) $l['status'] === 2 ? '·占用中' : '' ?>）</option>
                    <?php endforeach; ?>
                </select></div>
            <div class="wh-field"><label class="form-label">备注</label>
                <input class="form-input" id="mn-remark" maxlength="200" placeholder="如：面单磨损 / 外箱轻微压痕"></div>
        </div>
        <div class="wh-field">
            <label class="form-label">包裹照片（可选，最多 5 张）</label>
            <input class="form-input" type="file" id="mn-file" accept="image/jpeg,image/png,image/webp" capture="environment" onchange="pickPhotos('mn')">
            <p class="form-hint">提交后自动上传；入库后仍可在仓储详情补传。</p>
            <div class="card-grid card-grid--4" id="mn-photos" style="margin-top:10px" hidden></div>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="submitManual()">确认添加</button>
    </div>
</dialog>

<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="in-kw" placeholder="检索待收货：快递单号 / 包裹编号 / 用户编号" value="<?= View::e($keyword) ?>">
        <button class="btn btn--primary" onclick="admSearch('/warehouse/inbound', {kw: val('in-kw')})">检索</button>
        <span class="form-hint">仅列出已预报未收货的包裹</span>
    </div>
    <div class="table-wrap" data-table-wrap data-url="/warehouse/inbound?kw=<?= urlencode($keyword) ?>">
        <?= View::partial('warehouse/inbound_table', get_defined_vars()) ?>
    </div>
</section>

<script>
/** 当前扫描命中的包裹（入库完成后卡片回显单号与归属用） */
var INBOUND_PKG = null;

// 扫码枪以回车结束，回车即触发查询
document.getElementById('wh-scan-input').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); doLookup(); }
});
document.getElementById('in-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { admSearch('/warehouse/inbound', {kw: this.value}); }
});

/** 体积重实时预览：长×宽×高 ÷ 5000，与后端换算口径一致 */
function bindVolumePreview(prefix) {
    ['length', 'width', 'height'].forEach(function (f) {
        var el = document.getElementById(prefix + '-' + f);
        if (el) { el.addEventListener('input', function () { updateVolume(prefix); }); }
    });
}
function updateVolume(prefix) {
    var l = parseFloat(val(prefix + '-length')) || 0;
    var w = parseFloat(val(prefix + '-width')) || 0;
    var h = parseFloat(val(prefix + '-height')) || 0;
    var box = document.getElementById(prefix + '-volume');
    if (!box) { return; }
    box.textContent = (l > 0 && w > 0 && h > 0) ? (l * w * h / 5000).toFixed(3) + ' kg' : '待录入箱规';
}
bindVolumePreview('inb');
bindVolumePreview('un');
bindVolumePreview('mn');

async function doLookup() {
    var keyword = val('wh-scan-input').trim();
    if (!keyword) { Notify.warning('请先扫描或输入单号'); return; }
    var result = document.getElementById('wh-lookup-result');
    result.innerHTML = '<div class="wh-flash wh-flash--warn">查询中…</div>';

    var res = await App.post('/warehouse/inbound/lookup', { keyword: keyword });
    if (!res.success) {
        result.innerHTML = '';
        Notify.error(res.message || '查询失败');
        return;
    }
    if (!res.data.matched) {
        // 未匹配：引导登记无主件
        result.innerHTML = '<div class="wh-flash wh-flash--warn">⚠️ ' + res.data.message + '</div>';
        document.getElementById('wh-unattributed-panel').hidden = false;
        document.getElementById('un-tracking').value = keyword;
        return;
    }

    var p = res.data.package;
    INBOUND_PKG = p;
    document.getElementById('wh-unattributed-panel').hidden = true;
    clearPendingPhotos('un');
    result.innerHTML = ''
        + '<div class="wh-flash wh-flash--ok">✅ 已匹配预报记录，请称重量尺后确认入库</div>'
        + '<div class="site-grid">'
        + '  <div class="wh-card">'
        + '    <div class="wh-card__title">包裹与归属</div>'
        + '    <div class="wh-kv"><span>包裹编号</span><b class="v-code">' + esc(p.package_no) + '</b></div>'
        + '    <div class="wh-kv"><span>快递单号</span><b class="v-code">' + esc(p.tracking_no || '—') + '</b></div>'
        + '    <div class="wh-kv"><span>归属用户</span><b>' + esc(p.user_no) + ' ' + esc(p.user_name) + '</b></div>'
        + '    <div class="wh-kv"><span>入库识别码</span><b class="v-code">' + esc(p.user_code) + '</b></div>'
        + '    <div class="wh-kv"><span>联系电话</span><b>' + esc(p.phone || '未绑定') + '</b></div>'
        + '    <div class="wh-kv"><span>商品</span><b>' + esc(p.item_name) + ' ×' + p.item_qty + '</b></div>'
        + '    <div class="wh-kv"><span>申报价值</span><b class="v-money">' + esc(p.currency) + ' ' + Number(p.declared).toFixed(2) + '</b></div>'
        + '  </div>'
        + '  <div class="wh-card">'
        + '    <div class="wh-card__title">称重量尺（入库必填）</div>'
        + '    <input type="hidden" id="inb-id" value="' + p.id + '">'
        + '    <div class="wh-field"><label class="form-label">实际重量 kg</label>'
        + '      <input class="form-input wh-num" type="number" step="0.001" min="0.001" id="inb-weight" placeholder="0.000"></div>'
        + '    <div class="form-row">'
        + '      <div class="wh-field" style="max-width:150px"><label class="form-label">长 cm</label><input class="form-input wh-num" type="number" step="0.1" id="inb-length"></div>'
        + '      <div class="wh-field" style="max-width:150px"><label class="form-label">宽 cm</label><input class="form-input wh-num" type="number" step="0.1" id="inb-width"></div>'
        + '      <div class="wh-field" style="max-width:150px"><label class="form-label">高 cm</label><input class="form-input wh-num" type="number" step="0.1" id="inb-height"></div>'
        + '    </div>'
        + '    <div class="wh-kv"><span>体积重（自动）</span><b id="inb-volume">待录入箱规</b></div>'
        + '    <div class="wh-field" style="margin-top:10px"><label class="form-label">上架库位（可稍后在仓储管理上架）</label>'
        + '      <select class="form-input" id="inb-location">' + locationOptions() + '</select></div>'
        + '    <div class="wh-field"><label class="form-label">备注</label>'
        + '      <input class="form-input" id="inb-remark" maxlength="200" placeholder="如：外箱轻微压痕"></div>'
        + '    <div class="wh-field"><label class="form-label">包裹照片（可选，入库前先拍）</label>'
        + '      <div class="form-row" style="align-items:flex-end">'
        + '        <div class="wh-field" style="max-width:420px"><input class="form-input" type="file" id="inb-file" accept="image/jpeg,image/png,image/webp" capture="environment"></div>'
        + '        <div class="wh-field"><button class="btn btn--ghost btn--sm" type="button" onclick="uploadInboundPhoto(\'inb\', ' + Number(p.id) + ')">上传照片</button></div>'
        + '      </div>'
        + '      <div class="card-grid card-grid--4" id="inb-grid" style="margin-top:12px" hidden></div>'
        + '    </div>'
        + '    <button class="btn btn--primary wh-btn-lg" type="button" onclick="submitInbound()">确认入库</button>'
        + '  </div>'
        + '</div>';
    bindVolumePreview('inb');
    var w = document.getElementById('inb-weight');
    if (w) { w.focus(); }
}

function locationOptions() {
    var html = '<option value="0">暂不上架（放入入库暂存区）</option>';
    LOCATIONS.forEach(function (l) {
        var label = l.code + '（' + (l.type_name || '库位') + (l.status === 2 ? '·占用中' : '') + '）';
        html += '<option value="' + l.id + '">' + esc(label) + '</option>';
    });
    return html;
}

var LOCATIONS = <?= json_encode(array_map(static fn(array $l): array => [
    'id'        => (int) $l['id'],
    'code'      => (string) $l['code'],
    'status'    => (int) $l['status'],
    'type_name' => $locTypes[(int) $l['type']] ?? '库位',
], $locations), JSON_UNESCAPED_UNICODE) ?>;

function esc(s) {
    var d = document.createElement('div');
    d.textContent = s === null || s === undefined ? '' : String(s);
    return d.innerHTML;
}

function submitInbound() {
    var id = val('inb-id');
    var weight = parseFloat(val('inb-weight')) || 0;
    var l = parseFloat(val('inb-length')) || 0;
    var w = parseFloat(val('inb-width')) || 0;
    var h = parseFloat(val('inb-height')) || 0;
    if (!(weight > 0)) { Notify.warning('请填写实际重量'); return; }
    if (!(l > 0 && w > 0 && h > 0)) { Notify.warning('请填写长宽高'); return; }

    admConfirm('确认入库并记录重量 ' + weight + 'kg？入库后包裹状态变为「已到仓」，用户即可查看与认领。', async function () {
        var res = await App.post('/warehouse/inbound/save', {
            id: id, weight: weight, length: l, width: w, height: h,
            location_id: val('inb-location'), remark: val('inb-remark').trim()
        });
        App.handle(res, '入库完成');
        if (!res.success) { return; }
        renderDoneCard({
            id: id,
            package_no: res.data.package_no,
            tracking_no: INBOUND_PKG ? INBOUND_PKG.tracking_no : '',
            user_no: INBOUND_PKG ? INBOUND_PKG.user_no : '',
            user_name: INBOUND_PKG ? INBOUND_PKG.user_name : '',
            volume_weight: res.data.volume_weight,
            title: '✅ 入库完成，包裹已置为「已到仓」'
        });
        document.getElementById('wh-scan-input').value = '';
        document.getElementById('wh-scan-input').focus();
        admRefresh();
    }, { title: '确认入库', confirmText: '确认入库' });
}

function submitUnattributed() {
    var tracking = val('un-tracking');
    var item = val('un-item').trim();
    var weight = parseFloat(val('un-weight')) || 0;
    var l = parseFloat(val('un-length')) || 0;
    var w = parseFloat(val('un-width')) || 0;
    var h = parseFloat(val('un-height')) || 0;
    if (!item) { Notify.warning('请填写商品名称'); return; }
    if (!(weight > 0)) { Notify.warning('请填写实际重量'); return; }
    if (!(l > 0 && w > 0 && h > 0)) { Notify.warning('请填写长宽高'); return; }

    admConfirm('确认把单号 ' + tracking + ' 登记为无主件？登记后需在「仓储管理」指定归属用户，包裹才会归属到用户账户。', async function () {
        var res = await App.post('/warehouse/inbound/unattributed', {
            tracking_no: tracking, express_company: val('un-express').trim(),
            item_name: item, item_qty: val('un-qty'), category: val('un-category'),
            weight: weight, length: l, width: w, height: h, remark: val('un-remark').trim()
        });
        App.handle(res);
        if (!res.success) { return; }
        document.getElementById('wh-unattributed-panel').hidden = true;
        renderDoneCard({
            id: res.data.id,
            package_no: res.data.package_no,
            tracking_no: tracking,
            unattributed: true,
            title: '📦 无主件已登记，请到「仓储管理」指定归属用户'
        });
        flushPendingPhotos('un', res.data.id);
        document.getElementById('wh-scan-input').value = '';
        document.getElementById('wh-scan-input').focus();
        refreshTodoBadges();
        admRefresh();
    }, { title: '登记无主件', confirmText: '确认登记' });
}

/** 打开手动添加对话框：扫码不可用时的补录入口（每次打开都重置待传照片，避免上次取消后的残留） */
function openManual() {
    document.getElementById('dlg-manual').showModal();
    clearPendingPhotos('mn');
    updateVolume('mn');
}

function clearManualForm() {
    ['mn-code', 'mn-tracking', 'mn-express', 'mn-item', 'mn-weight', 'mn-length', 'mn-width', 'mn-height', 'mn-remark'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.value = ''; }
    });
    document.getElementById('mn-qty').value = '1';
    document.getElementById('mn-location').value = '0';
    clearPendingPhotos('mn');
    document.getElementById('mn-code').focus();
    updateVolume('mn');
}

function manualPayload(extra) {
    var data = {
        user_code: val('mn-code').trim(),
        tracking_no: val('mn-tracking').trim(),
        express_company: val('mn-express').trim(),
        item_name: val('mn-item').trim(),
        item_qty: val('mn-qty'),
        category: val('mn-category'),
        weight: parseFloat(val('mn-weight')) || 0,
        length: parseFloat(val('mn-length')) || 0,
        width: parseFloat(val('mn-width')) || 0,
        height: parseFloat(val('mn-height')) || 0,
        location_id: val('mn-location'),
        remark: val('mn-remark').trim()
    };
    Object.keys(extra || {}).forEach(function (k) { data[k] = extra[k]; });
    return data;
}

/**
 * 提交手动添加
 * 识别码匹配不到用户、或单号已存在时后端不落库，返回 needConfirm 由收货员当场决定：
 * unattributed → 确认后按无主件登记；duplicate → L2 确认后强制新建；forecast → 转去给已有预报包裹入库
 */
async function submitManual(extra) {
    var data = manualPayload(extra);
    if (!data.tracking_no) { Notify.warning('请填写快递单号'); return; }
    if (!data.item_name) { Notify.warning('请填写商品名称'); return; }
    if (!(data.weight > 0)) { Notify.warning('请填写实际重量'); return; }
    if (!(data.length > 0 && data.width > 0 && data.height > 0)) { Notify.warning('请填写长宽高'); return; }

    var res = await App.post('/warehouse/inbound/manual', data);
    if (res.success) {
        document.getElementById('dlg-manual').close();
        Notify.success(res.message);
        renderDoneCard({
            id: res.data.id,
            package_no: res.data.package_no,
            tracking_no: data.tracking_no,
            user_no: res.data.user_no,
            user_name: res.data.user_name,
            volume_weight: res.data.volume_weight,
            unattributed: !!res.data.is_unattributed,
            title: res.data.is_unattributed
                ? '📦 无主件已登记，请到「仓储管理」指定归属用户'
                : '✅ 包裹已入库并归属到 ' + (res.data.user_no + ' ' + res.data.user_name) + (res.data.notified ? '（已通知用户）' : '')
        });
        flushPendingPhotos('mn', res.data.id);
        clearManualForm();
        refreshTodoBadges();
        return;
    }

    var need = res.data && res.data.needConfirm;
    if (need === 'unattributed') {
        Confirm.show({
            title: '登记无主件确认',
            message: res.message,
            confirmText: '登记为无主件',
            onConfirm: function () { return submitManual({ as_unattributed: 1 }); }
        });
        return;
    }
    if (need === 'duplicate') {
        Confirm.show({
            title: '单号重复确认',
            level: 'L2',
            message: res.message + '。确认仍要新建一条包裹吗？',
            checkboxText: '我已核对面单，确认这不是同一件包裹',
            confirmText: '仍要新建',
            danger: true,
            onConfirm: function () { return submitManual({ force: 1 }); }
        });
        return;
    }
    if (need === 'forecast') {
        Confirm.show({
            title: '已有预报记录',
            message: res.message + '。转到扫描收货为该包裹办理入库？已选照片要在该处重新拍摄上传。',
            confirmText: '去入库该包裹',
            onConfirm: function () {
                document.getElementById('dlg-manual').close();
                clearPendingPhotos('mn');
                document.getElementById('wh-scan-input').value = data.tracking_no;
                return doLookup();
            }
        });
        return;
    }
    Notify.error(res.message || '添加失败');
}

/**
 * 作业完成卡：包裹编号 + 归属 + 入库拍照留档
 * 拍照后只把新照片插进网格，不整页刷新（规范 §2.5）
 */
function renderDoneCard(info) {
    var html = '<div class="wh-flash ' + (info.unattributed ? 'wh-flash--warn' : 'wh-flash--ok') + '">' + esc(info.title || '已完成') + '</div>'
        + '<div class="site-grid">'
        + '  <div class="wh-card">'
        + '    <div class="wh-card__title">' + esc(info.package_no || '') + '</div>'
        + '    <div class="wh-kv"><span>快递单号</span><b class="v-code">' + esc(info.tracking_no || '—') + '</b></div>'
        + '    <div class="wh-kv"><span>归属用户</span><b>' + (info.unattributed ? '无主件（待指定归属）' : esc((info.user_no || '') + ' ' + (info.user_name || ''))) + '</b></div>'
        + '    <div class="wh-kv"><span>体积重</span><b>' + (info.volume_weight ? esc(info.volume_weight) + ' kg' : '—') + '</b></div>'
        + '  </div>'
        + '  <div class="wh-card">'
        + '    <div class="wh-card__title">入库拍照（可选，入库后可补拍）</div>'
        + '    <div class="wh-field"><input class="form-input" type="file" id="done-file" accept="image/jpeg,image/png,image/webp" capture="environment"></div>'
        + '    <button class="btn btn--primary btn--sm" type="button" onclick="uploadInboundPhoto(\'done\', ' + Number(info.id) + ')">上传照片</button>'
        + '    <div class="card-grid card-grid--4" id="done-grid" style="margin-top:12px" hidden></div>'
        + '  </div>'
        + '</div>'
        + (info.unattributed ? '<a class="btn btn--ghost btn--sm" href="/warehouse/storage">去仓储管理指定归属 →</a>' : '');
    document.getElementById('wh-lookup-result').innerHTML = html;
}

/**
 * 上传入库照片：入库前先拍（inb）与入库后补拍（done）共用
 * 两张卡片的文件框与缩略图网格以 prefix 区分，互不干扰
 */
async function uploadInboundPhoto(prefix, packageId) {
    var input = document.getElementById(prefix + '-file');
    if (!input || !input.files || !input.files[0]) { Notify.warning('请先选择图片'); return; }
    if (input.files[0].size > 5 * 1024 * 1024) { Notify.warning('图片不能超过 5MB'); input.value = ''; return; }

    var form = new FormData();
    form.append('file', input.files[0]);
    form.append('package_id', packageId);
    form.append('_token', App.csrf());

    var handle = Notify.loading('上传中…');
    try {
        var res = await fetch('/warehouse/inbound/upload', {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': App.csrf() },
            body: form
        });
        var data = await res.json();
        if (handle && handle.close) { handle.close(); }
        if (!data.success) { Notify.error(data.message || '上传失败'); return; }
        appendPhotoCard(prefix, data.data.url);
        input.value = '';
        Notify.success('入库照片已上传');
    } catch (err) {
        if (handle && handle.close) { handle.close(); }
        Notify.error('上传失败，请稍后重试');
    }
}

/** 只把新照片插进网格，不整页刷新（规范 §2.5）；点击走全站弹层放大预览 */
function appendPhotoCard(prefix, url) {
    var grid = document.getElementById(prefix + '-grid');
    if (!grid) { return; }
    grid.hidden = false;
    var card = document.createElement('div');
    card.className = 'card';
    card.style.padding = '8px';
    var img = document.createElement('img');
    img.className = 'zoomable';
    img.src = url;
    img.alt = '入库照';
    img.setAttribute('data-zoom', url);
    img.style.cssText = 'width:100%;border-radius:10px;display:block';
    card.appendChild(img);
    grid.appendChild(card);
}

/**
 * 手动入库（手动添加 / 登记无主件）在提交前包裹尚未创建，无法先上传
 * 故把所选文件暂存在浏览器端，提交成功拿到包裹ID后逐张补传
 */
var PENDING_PHOTOS = {};

function pickPhotos(prefix) {
    var input = document.getElementById(prefix + '-file');
    if (!input || !input.files || !input.files[0]) { return; }
    var file = input.files[0];
    var list = PENDING_PHOTOS[prefix] || [];
    if (list.length >= 5) { Notify.warning('最多上传 5 张照片'); input.value = ''; return; }
    if (file.size > 5 * 1024 * 1024) { Notify.warning('图片不能超过 5MB'); input.value = ''; return; }
    list.push(file);
    PENDING_PHOTOS[prefix] = list;
    input.value = '';
    renderPendingPhotos(prefix);
}

function renderPendingPhotos(prefix) {
    var box = document.getElementById(prefix + '-photos');
    if (!box) { return; }
    var list = PENDING_PHOTOS[prefix] || [];
    box.innerHTML = '';
    box.hidden = list.length === 0;
    list.forEach(function (file, index) {
        var url = URL.createObjectURL(file);
        var card = document.createElement('div');
        card.className = 'card';
        card.style.padding = '6px';
        var img = document.createElement('img');
        img.className = 'zoomable';
        img.src = url;
        img.setAttribute('data-zoom', url);
        img.alt = '待上传照片';
        img.style.cssText = 'width:100%;height:80px;object-fit:cover;border-radius:8px;display:block';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn--ghost btn--sm';
        btn.style.cssText = 'width:100%;margin-top:6px';
        btn.textContent = '移除';
        btn.addEventListener('click', function () { removePendingPhoto(prefix, index); });
        card.appendChild(img);
        card.appendChild(btn);
        box.appendChild(card);
    });
}

function removePendingPhoto(prefix, index) {
    (PENDING_PHOTOS[prefix] || []).splice(index, 1);
    renderPendingPhotos(prefix);
}

function clearPendingPhotos(prefix) {
    PENDING_PHOTOS[prefix] = [];
    renderPendingPhotos(prefix);
}

/** 补传暂存照片：逐张上传，成功一张就往完成卡的照片网格插一张 */
async function flushPendingPhotos(prefix, packageId) {
    var list = (PENDING_PHOTOS[prefix] || []).slice();
    clearPendingPhotos(prefix);
    var done = 0;
    for (var i = 0; i < list.length; i++) {
        var form = new FormData();
        form.append('file', list[i]);
        form.append('package_id', packageId);
        form.append('_token', App.csrf());
        try {
            var res = await fetch('/warehouse/inbound/upload', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': App.csrf() },
                body: form
            });
            var data = await res.json();
            if (data.success) { done++; appendPhotoCard('done', data.data.url); }
            else { Notify.error(data.message || '照片上传失败'); }
        } catch (err) {
            Notify.error('照片上传失败，请稍后重试');
        }
    }
    if (done > 0) { Notify.success(done + ' 张包裹照片已上传'); }
}
</script>
