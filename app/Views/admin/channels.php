<?php declare(strict_types=1);
/**
 * @file app/Views/admin/channels.php
 * @description 物流渠道列表页 + 渠道编辑对话框
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var string $keyword
 * @var array $perms
 * @var string $csrf
 */
?>
<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="c-kw" placeholder="渠道代码 / 名称 / 承运商" value="<?= View::e($keyword) ?>">
        <button class="btn btn--primary" onclick="admSearch('<?= View::e($base) ?>', {kw: val('c-kw')})">检索</button>
        <?php if (!empty($perms['channel.manage'])): ?>
            <button class="btn btn--primary" style="margin-left:auto" onclick="openCh()">+ 新建渠道</button>
        <?php endif; ?>
    </div>
    <div class="table-wrap" data-table-wrap data-url="/admin/channels?<?= http_build_query(array_filter(['kw' => $keyword], static fn($v): bool => $v !== '')) ?>">
        <?= View::partial('admin/channels_table', get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog adm-dialog--lg" id="dlg-ch">
    <div class="adm-dialog__head" id="ch-title">新建渠道</div>
    <div class="adm-dialog__body">
        <input type="hidden" id="ch-id" value="0">
        <div class="form-row">
            <div class="form-field"><label class="form-label">渠道代码</label><input class="form-input" id="ch-code" placeholder="US-DHL-EXP"></div>
            <div class="form-field"><label class="form-label">承运商/线路</label><input class="form-input" id="ch-carrier" placeholder="DHL"></div>
            <div class="form-field"><label class="form-label">渠道名称</label><input class="form-input" id="ch-name" placeholder="美国DHL特快专线"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">货物类型</label>
                <select class="form-input" id="ch-goods"><option value="1">普货</option><option value="2">敏感货</option></select></div>
            <div class="form-field"><label class="form-label">体积重系数</label><input class="form-input" type="number" id="ch-divisor" value="5000"></div>
            <div class="form-field"><label class="form-label">关税模式</label>
                <select class="form-input" id="ch-tax"><option value="1">到付</option><option value="2">预付</option><option value="3">双清包税</option></select></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">时效说明</label><input class="form-input" id="ch-aging" placeholder="7-12 个工作日"></div>
            <div class="form-field"><label class="form-label">面单模板（可选）</label><input class="form-input" id="ch-label"></div>
            <div class="form-field"><label class="form-label">轨迹接口标识（可选）</label><input class="form-input" id="ch-track"></div>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveCh()">保存</button>
    </div>
</dialog>

<script>
function openCh(id, data) {
    document.getElementById('ch-title').textContent = id > 0 ? '编辑渠道' : '新建渠道';
    document.getElementById('ch-id').value = id > 0 ? id : 0;
    document.getElementById('ch-code').value = data ? data.channel_code : '';
    document.getElementById('ch-name').value = data ? data.name : '';
    document.getElementById('ch-carrier').value = data ? (data.carrier || '') : '';
    document.getElementById('ch-goods').value = data ? data.goods_type : 1;
    document.getElementById('ch-divisor').value = data ? data.volume_divisor : 5000;
    document.getElementById('ch-tax').value = data ? data.tax_mode : 1;
    document.getElementById('ch-aging').value = data ? (data.aging_desc || '') : '';
    document.getElementById('ch-label').value = data ? (data.label_template || '') : '';
    document.getElementById('ch-track').value = data ? (data.track_api_code || '') : '';
    document.getElementById('dlg-ch').showModal();
}
function saveCh() {
    var code = val('ch-code').trim();
    var name = val('ch-name').trim();
    if (!code) { Notify.warning('请填写渠道代码'); return; }
    if (!name) { Notify.warning('请填写渠道名称'); return; }
    var isEdit = parseInt(val('ch-id'), 10) > 0;
    admConfirm((isEdit ? '确定保存对渠道「' : '确定创建渠道「') + name + '」吗？', async function () {
        var res = await App.post('/admin/channels/save', {
            id: val('ch-id'), channel_code: code, name: name, carrier: val('ch-carrier'),
            goods_type: val('ch-goods'), volume_divisor: val('ch-divisor'), tax_mode: val('ch-tax'),
            aging_desc: val('ch-aging'), label_template: val('ch-label'), track_api_code: val('ch-track'),
            status: 1, sort: 0
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-ch').close(); admRefresh(); }
    }, { title: isEdit ? '保存渠道信息' : '创建渠道', confirmText: '确认保存' });
}
</script>
