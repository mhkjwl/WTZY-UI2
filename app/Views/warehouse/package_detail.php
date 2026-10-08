<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/package_detail.php
 * @description 仓库端包裹详情：信息/计量/照片/异常记录 + 上架移库下架登记异常 + 补录照片（局部刷新）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-16
 */

use App\Core\View;

/**
 * @var array  $package
 * @var array  $photos
 * @var array  $abnormals
 * @var float  $storage_fee_est
 * @var array  $locations
 * @var array  $locTypes
 * @var array  $perms
 */
$packageId = (int) $package['id'];
$detailUrl = '/warehouse/storage/package/' . $packageId;
?>
<div class="adm-toolbar">
    <a class="btn btn--ghost btn--sm" href="/warehouse/storage">‹ 返回仓储管理</a>
</div>

<div class="table-wrap" data-table-wrap data-url="<?= View::e($detailUrl) ?>">
    <?= View::partial('warehouse/package_detail_table', get_defined_vars()) ?>
</div>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-shelve">
    <div class="adm-dialog__head">上架 / 移库 · <span id="sh-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="sh-id">
        <div class="wh-field">
            <label class="form-label">目标库位</label>
            <select class="form-input" id="sh-location"></select>
            <p class="form-hint">常规存储位与包裹一对一，占用中的库位不可选（除非是当前库位）。也可由系统随机分配空闲库位。</p>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--ghost" onclick="submitShelve(true)">随机分配</button>
        <button class="btn btn--primary" onclick="submitShelve(false)">确认上架</button>
    </div>
</dialog>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-abnormal">
    <div class="adm-dialog__head">登记异常 · <span id="ab-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="ab-package-id">
        <div class="wh-field">
            <label class="form-label">异常类型</label>
            <select class="form-input" id="ab-type">
                <?php foreach (\App\Enums\Status::ABNORMAL_TYPE as $code => $label): ?>
                    <option value="<?= (int) $code ?>"><?= View::e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="wh-field">
            <label class="form-label">异常说明</label>
            <textarea class="form-input" id="ab-desc" rows="3" maxlength="500" placeholder="如：外箱左侧凹陷，内含玻璃制品需开箱确认"></textarea>
        </div>
        <p class="form-hint">登记后包裹标记为异常件并进入「异常处理」待办。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="submitAbnormal()">提交登记</button>
    </div>
</dialog>

<script>
var LOCS = <?= json_encode(array_map(static fn(array $l): array => [
    'id'        => (int) $l['id'],
    'code'      => (string) $l['code'],
    'status'    => (int) $l['status'],
    'type_name' => $locTypes[(int) $l['type']] ?? '库位',
], $locations), JSON_UNESCAPED_UNICODE) ?>;

/** 上架 / 移库：从详情页直接发起，成功后局部刷新详情区 */
function shelve(packageId, packageNo, current) {
    var options = '<option value="">请选择库位</option>';
    LOCS.forEach(function (l) {
        var label = l.code + '（' + l.type_name + (l.status === 2 && l.id !== current ? '·占用中' : '') + '）';
        options += '<option value="' + l.id + '"' + (l.id === current ? ' selected' : '') + '>' + label + '</option>';
    });
    document.getElementById('sh-id').value = packageId;
    document.getElementById('sh-no').textContent = packageNo;
    document.getElementById('sh-location').innerHTML = options;
    document.getElementById('dlg-shelve').showModal();
}

function submitShelve(randomMode) {
    var locationId = val('sh-location');
    if (!randomMode && !locationId) { Notify.warning('请选择目标库位（或使用「随机分配」）'); return; }
    var payload = randomMode ? { id: val('sh-id'), random: 1 } : { id: val('sh-id'), location_id: locationId };
    var tip = randomMode ? '确认随机占用一个空闲库位吗？若常规库位已满将无法提交。' : '确认上架到该库位吗？库位占用状态会同步更新。';
    admConfirm(tip, async function () {
        var res = await App.post('/warehouse/storage/shelve', payload);
        App.handle(res);
        if (res.success) { document.getElementById('dlg-shelve').close(); admRefresh(); }
    }, { title: '上架确认', confirmText: '确认上架' });
}

/** 登记异常：成功后局部刷新详情区 */
function openAbnormal(packageId, packageNo) {
    document.getElementById('ab-package-id').value = packageId;
    document.getElementById('ab-no').textContent = packageNo;
    document.getElementById('ab-desc').value = '';
    document.getElementById('dlg-abnormal').showModal();
}

function submitAbnormal() {
    var desc = val('ab-desc').trim();
    if (desc.length < 4) { Notify.warning('请填写异常说明（至少 4 个字）'); return; }
    admConfirm('确认登记该异常吗？包裹会被标记为异常件并进入异常处理待办。', async function () {
        var res = await App.post('/warehouse/abnormal/save', {
            package_id: val('ab-package-id'), abnormal_type: val('ab-type'), description: desc
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-abnormal').close(); admRefresh(); }
    }, { title: '登记异常', confirmText: '确认登记', danger: true });
}

/** 补录包裹照片：成功后局部刷新详情区，新照片随重渲染出现（规范 §2.5 无整页刷新） */
async function uploadPackagePhoto(packageId) {
    var input = document.getElementById('pd-file');
    if (!input || !input.files || !input.files[0]) { Notify.warning('请先选择图片'); return; }
    if (input.files[0].size > 5 * 1024 * 1024) { Notify.warning('图片不能超过 5MB'); input.value = ''; return; }

    var form = new FormData();
    form.append('file', input.files[0]);
    form.append('package_id', packageId);
    form.append('_token', App.csrf());

    var handle = Notify.loading('上传中…');
    try {
        var res = await fetch('/warehouse/storage/upload', {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': App.csrf() },
            body: form
        });
        var data = await res.json();
        if (handle && handle.close) { handle.close(); }
        if (!data.success) { Notify.error(data.message || '上传失败'); return; }
        input.value = '';
        Notify.success('包裹照片已上传');
        admRefresh();
    } catch (err) {
        if (handle && handle.close) { handle.close(); }
        Notify.error('上传失败，请稍后重试');
    }
}
</script>
