<?php declare(strict_types=1);
/**
 * @file app/Views/admin/warehouse.php
 * @description 仓库管理页：仓库/库位管理 + 编辑对话框
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\WarehouseModel;

/**
 * @var array $warehouses
 * @var int $wid
 * @var array $perms
 * @var string $csrf
 */
$timezones = ['Asia/Shanghai', 'UTC', 'America/Los_Angeles', 'America/New_York', 'Europe/London', 'Europe/Berlin'];
?>
<section class="panel">
    <div class="table-wrap" data-table-wrap data-url="/admin/warehouse?wid=<?= (int) $wid ?>&lpage=1">
        <?= View::partial('admin/warehouse_content', get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog adm-dialog--lg" id="dlg-wh">
    <div class="adm-dialog__head" id="wh-title">新建仓库</div>
    <div class="adm-dialog__body">
        <input type="hidden" id="wh-id" value="0">
        <div class="form-row">
            <div class="form-field"><label class="form-label">仓库编号</label><input class="form-input" id="wh-no" placeholder="WH002"></div>
            <div class="form-field"><label class="form-label">仓库代码</label><input class="form-input" id="wh-code" placeholder="CA02"></div>
            <div class="form-field"><label class="form-label">仓库名称</label><input class="form-input" id="wh-name" placeholder="美国加州二仓"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">国家码</label><input class="form-input" id="wh-country" placeholder="US"></div>
            <div class="form-field"><label class="form-label">州/省</label><input class="form-input" id="wh-state"></div>
            <div class="form-field"><label class="form-label">城市</label><input class="form-input" id="wh-city"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">详细地址</label><input class="form-input" id="wh-address"></div>
            <div class="form-field"><label class="form-label">邮编</label><input class="form-input" id="wh-zip"></div>
            <div class="form-field"><label class="form-label">联系电话</label><input class="form-input" id="wh-phone"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">时区</label>
                <select class="form-input" id="wh-tz"><?php foreach ($timezones as $tz): ?><option><?= View::e($tz) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label class="form-label">免费仓储天数</label><input class="form-input" type="number" id="wh-free" value="30"></div>
            <div class="form-field"><label class="form-label">排序</label><input class="form-input" type="number" id="wh-sort" value="0"></div>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveWh()">保存</button>
    </div>
</dialog>

<dialog class="adm-dialog" id="dlg-loc">
    <div class="adm-dialog__head">新增库位</div>
    <div class="adm-dialog__body">
        <div class="form-row">
            <div class="form-field"><label class="form-label">所属仓库</label>
                <select class="form-input" id="loc-wid-new">
                    <?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['id'] ?>"><?= View::e($w['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-field"><label class="form-label">类型</label>
                <select class="form-input" id="loc-type">
                    <option value="1">入库暂存</option><option value="2" selected>常规存储</option>
                    <option value="3">打包区</option><option value="4">出库暂存</option><option value="5">异常件区</option>
                </select></div>
            <div class="form-field"><label class="form-label">生成方式</label>
                <select class="form-input" id="loc-mode" onchange="locModeChange()">
                    <option value="single">单个新增</option>
                    <option value="batch">批量生成</option>
                </select></div>
        </div>
        <div class="form-row" id="loc-single-row">
            <div class="form-field"><label class="form-label">库位编码（区-架-层）</label><input class="form-input" id="loc-code" placeholder="A-01-03-02"></div>
        </div>
        <div id="loc-batch" hidden>
            <div class="form-row">
                <div class="form-field"><label class="form-label">编码前缀</label>
                    <input class="form-input" id="lb-prefix" maxlength="16" placeholder="A-01-" oninput="locPreview()"></div>
                <div class="form-field"><label class="form-label">起始序号</label>
                    <input class="form-input" id="lb-start" maxlength="4" placeholder="1" oninput="locPreview()"></div>
                <div class="form-field"><label class="form-label">结束序号</label>
                    <input class="form-input" id="lb-end" maxlength="4" placeholder="20" oninput="locPreview()"></div>
                <div class="form-field"><label class="form-label">随机数量</label>
                    <input class="form-input" id="lb-count" type="number" min="1" max="200" value="10" oninput="locPreview()"></div>
            </div>
            <p class="form-hint">起止序号都填则顺序编号（补零位数按填写的位数，如 01~20 得两位）；序号留空则用前缀拼 4 位随机码。单次最多 200 个，已存在的编码自动跳过。</p>
            <p class="form-hint" id="lb-preview" hidden></p>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveLoc()">保存</button>
    </div>
</dialog>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-loc-detail">
    <div class="adm-dialog__head">库位详情 · <span class="mono" id="ld-code"></span></div>
    <div class="adm-dialog__body">
        <div class="hero-address" id="ld-info"></div>
        <div id="ld-pkg"></div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">关闭</button>
    </div>
</dialog>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-loc-edit">
    <div class="adm-dialog__head">编辑库位 · <span class="mono" id="le-code-title"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="le-id">
        <div class="form-row">
            <div class="form-field"><label class="form-label">所属仓库（不可修改）</label>
                <input class="form-input" id="le-wh-name" readonly></div>
            <div class="form-field"><label class="form-label">库位编码（区-架-层）</label><input class="form-input" id="le-code" placeholder="A-01-03-02"></div>
            <div class="form-field"><label class="form-label">类型</label>
                <select class="form-input" id="le-type">
                    <option value="1">入库暂存</option><option value="2">常规存储</option>
                    <option value="3">打包区</option><option value="4">出库暂存</option><option value="5">异常件区</option>
                </select></div>
        </div>
        <p class="form-hint">所属仓库不可变更。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveLocEdit()">保存</button>
    </div>
</dialog>

<script>
function openWh(id, data) {
    document.getElementById('wh-title').textContent = id > 0 ? '编辑仓库' : '新建仓库';
    document.getElementById('wh-id').value = id;
    document.getElementById('wh-no').value = data ? data.warehouse_no : '';
    document.getElementById('wh-code').value = data ? data.code : '';
    document.getElementById('wh-name').value = data ? data.name : '';
    document.getElementById('wh-country').value = data ? data.country : '';
    document.getElementById('wh-state').value = data ? data.state : '';
    document.getElementById('wh-city').value = data ? data.city : '';
    document.getElementById('wh-address').value = data ? data.address : '';
    document.getElementById('wh-zip').value = data ? data.zip_code : '';
    document.getElementById('wh-phone').value = data ? (data.contact_phone || '') : '';
    document.getElementById('wh-tz').value = data ? data.timezone : 'Asia/Shanghai';
    document.getElementById('wh-free').value = data ? data.free_storage_days : 30;
    document.getElementById('wh-sort').value = data ? data.sort : 0;
    document.getElementById('dlg-wh').showModal();
}
function saveWh() {
    var name = val('wh-name').trim();
    if (!name) { Notify.warning('请填写仓库名称'); return; }
    var isEdit = parseInt(val('wh-id'), 10) > 0;
    admConfirm((isEdit ? '确定保存对仓库「' : '确定创建仓库「') + name + '」吗？', async function () {
        var res = await App.post('/admin/warehouse/save', {
            id: val('wh-id'), warehouse_no: val('wh-no'), code: val('wh-code'), name: name,
            country: val('wh-country'), state: val('wh-state'), city: val('wh-city'), address: val('wh-address'),
            zip_code: val('wh-zip'), contact_phone: val('wh-phone'), timezone: val('wh-tz'),
            free_storage_days: val('wh-free'), sort: val('wh-sort'), status: 1
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-wh').close(); admRefresh(); }
    }, { title: isEdit ? '保存仓库信息' : '创建仓库', confirmText: '确认保存' });
}
/** 打开新增库位弹窗：重置字段，默认选中当前库位列表筛选的仓库 */
function openLocAdd() {
    document.getElementById('loc-mode').value = 'single';
    document.getElementById('loc-code').value = '';
    document.getElementById('lb-prefix').value = '';
    document.getElementById('lb-start').value = '';
    document.getElementById('lb-end').value = '';
    document.getElementById('lb-count').value = '10';
    if (LIST_WID > 0) { document.getElementById('loc-wid-new').value = String(LIST_WID); }
    locModeChange();
    document.getElementById('dlg-loc').showModal();
}

/** 生成方式切换：单个填完整编码，批量填前缀与序号 */
function locModeChange() {
    var batch = val('loc-mode') === 'batch';
    document.getElementById('loc-single-row').hidden = batch;
    document.getElementById('loc-batch').hidden = !batch;
    locPreview();
}

/** 按位数左补零，与后端 str_pad 口径一致 */
function padNum(n, width) {
    var s = String(n);
    while (s.length < width) { s = '0' + s; }
    return s;
}

/** 批量预览：与后端同一套位数规则，仅作展示，实际以后端校验结果为准 */
function locPreview() {
    var box = document.getElementById('lb-preview');
    if (!box) { return; }
    var prefix = val('lb-prefix').trim().toUpperCase();
    var start = val('lb-start').trim();
    var end = val('lb-end').trim();
    if (prefix === '') { box.hidden = true; return; }
    if (start !== '' && end !== '') {
        var from = parseInt(start, 10);
        var to = parseInt(end, 10);
        if (!(from >= 0) || !(to >= 0) || from > to) { box.hidden = true; return; }
        var width = Math.max(start.length, end.length);
        box.textContent = '预计生成 ' + (to - from + 1) + ' 个：' + prefix + padNum(from, width) + ' ~ ' + prefix + padNum(to, width);
    } else {
        var count = parseInt(val('lb-count'), 10) || 0;
        box.textContent = '预计生成 ' + count + ' 个：' + prefix + ' + 4 位随机码';
    }
    box.hidden = false;
}

function saveLoc() {
    var wid = val('loc-wid-new');
    var type = val('loc-type');
    if (val('loc-mode') === 'batch') {
        var prefix = val('lb-prefix').trim().toUpperCase();
        var start = val('lb-start').trim();
        var end = val('lb-end').trim();
        if (!prefix) { Notify.warning('请填写编码前缀'); return; }
        if ((start === '') !== (end === '')) { Notify.warning('起始与结束序号需同时填写，或都留空走随机生成'); return; }
        var tip = start !== ''
            ? '确定按序号批量生成库位吗？编码为前缀加补零序号，已存在的自动跳过。'
            : '确定随机生成库位吗？编码为前缀加 4 位随机码，已存在的自动跳过。';
        admConfirm(tip, async function () {
            var res = await App.post('/admin/warehouse/location/batch', {
                warehouse_id: wid, type: type, prefix: prefix,
                start: start, end: end, count: val('lb-count')
            });
            App.handle(res);
            if (res.success) { document.getElementById('dlg-loc').close(); admRefresh(); }
        }, { title: '批量生成库位', confirmText: '确认生成' });
        return;
    }
    var code = val('loc-code').trim();
    if (!code) { Notify.warning('请填写库位编码'); return; }
    admConfirm('确定新增库位「' + code + '」吗？', async function () {
        var res = await App.post('/admin/warehouse/location', {
            warehouse_id: wid, code: code, type: type
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-loc').close(); admRefresh(); }
    }, { title: '新增库位', confirmText: '确认新增' });
}

/* 库位详情：数据来自列表行，无需再请求接口 */
var LIST_WID = <?= (int) $wid ?>;
var LOC_TYPES = <?= json_encode(WarehouseModel::LOCATION_TYPES, JSON_UNESCAPED_UNICODE) ?>;
var LOC_STATUS = <?= json_encode(WarehouseModel::LOCATION_STATUS, JSON_UNESCAPED_UNICODE) ?>;
var PKG_STATUS = <?= json_encode(Status::PACKAGE, JSON_UNESCAPED_UNICODE) ?>;

/** 编辑库位：所属仓库只读回填，编码与类型可改 */
function openLocEdit(loc) {
    document.getElementById('le-id').value = loc.id;
    document.getElementById('le-code-title').textContent = loc.code || '';
    document.getElementById('le-wh-name').value = loc.warehouse_name || '—';
    document.getElementById('le-code').value = loc.code || '';
    document.getElementById('le-type').value = String(loc.type || 2);
    document.getElementById('dlg-loc-edit').showModal();
}

function saveLocEdit() {
    var code = val('le-code').trim();
    if (!code) { Notify.warning('请填写库位编码'); return; }
    admConfirm('确定保存库位「' + code + '」的修改吗？', async function () {
        var res = await App.post('/admin/warehouse/location/update', {
            id: val('le-id'), code: code, type: val('le-type')
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-loc-edit').close(); admRefresh(); }
    }, { title: '编辑库位', confirmText: '确认保存' });
}

/** 详情弹窗内文本一律转义后插入（库位编码等虽有限制，仍按不可信处理） */
function ldEsc(v) {
    var d = document.createElement('div');
    d.textContent = v === null || v === undefined ? '' : String(v);
    return d.innerHTML;
}

function ldRow(label, value, mono) {
    return '<div class="hero-address__row"><span>' + ldEsc(label) + '</span><b'
        + (mono ? ' class="mono"' : '') + '>' + ldEsc(value) + '</b></div>';
}

function openLocDetail(loc) {
    document.getElementById('ld-code').textContent = loc.code || '';
    document.getElementById('ld-info').innerHTML =
        ldRow('所属仓库', loc.warehouse_name || '—') +
        ldRow('库位类型', LOC_TYPES[loc.type] || '未知') +
        ldRow('当前状态', LOC_STATUS[loc.status] || '未知') +
        ldRow('更新时间', loc.updated_at || '—', true);
    var box = document.getElementById('ld-pkg');
    if (loc.package_no) {
        box.innerHTML = '<h4 style="margin:16px 0 8px">占用包裹</h4>'
            + '<div class="hero-address">'
            + ldRow('包裹编号', loc.package_no, true)
            + ldRow('商品', loc.item_name || '—')
            + ldRow('包裹状态', PKG_STATUS[loc.package_status] || '未知')
            + ldRow('重量', loc.weight ? loc.weight + ' kg' : '—', true)
            + '</div>'
            + '<p class="form-hint">库位上的包裹需先完成下架或移库，才能停用该库位。</p>';
    } else {
        box.innerHTML = '<p class="form-hint" style="margin-top:16px">该库位当前未存放包裹。</p>';
    }
    document.getElementById('dlg-loc-detail').showModal();
}

/** 启用 / 停用库位：占用中的库位后端会拒绝停用并给出原因 */
function toggleLoc(id, status, code) {
    var next = status === 1 ? '停用' : '启用';
    var msg = '确定' + next + '库位「' + code + '」吗？'
        + (status === 1 ? '停用后仓库端不能再选它上架。' : '启用后仓库端可正常上架。');
    admAction('/admin/warehouse/location/toggle', { id: id }, msg,
        { title: next + '库位', confirmText: '确认' + next });
}
</script>
