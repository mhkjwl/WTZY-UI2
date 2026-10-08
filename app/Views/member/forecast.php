<?php declare(strict_types=1);
/**
 * @file app/Views/member/forecast.php
 * @description 会员中心：包裹预报（列表 + 新增/编辑对话框）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 * @var string $keyword
 * @var array $warehouses
 */
$initialUrl = '/user/forecast?' . http_build_query(array_filter(['kw' => $keyword, 'page' => $page]));
?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">填写指引</span><span class="rule"></span></h3>
    <p class="form-hint" style="line-height:1.9">请把入库识别码填在收件人姓名上，否则包裹无法自动归属。</p>
</section>

<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="f-kw" placeholder="包裹编号 / 快递单号 / 商品名称" value="<?= View::e($keyword) ?>">
        <button class="btn btn--primary" onclick="admSearch('/user/forecast', {kw: val('f-kw')})">检索</button>
        <button class="btn btn--primary" style="margin-left:auto" onclick="openForecast(null)">+ 新增预报</button>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('member/forecast_table', get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog adm-dialog--lg" id="dlg-fc">
    <div class="adm-dialog__head" id="fc-title">新增包裹预报</div>
    <div class="adm-dialog__body">
        <input type="hidden" id="fc-id" value="0">
        <div class="form-row">
            <div class="form-field"><label class="form-label">快递单号</label><input class="form-input mono" id="fc-tracking" maxlength="64" placeholder="海外电商发货单号"></div>
            <div class="form-field"><label class="form-label">快递公司（选填）</label><input class="form-input" id="fc-express" maxlength="50" placeholder="UPS / FedEx / USPS"></div>
            <div class="form-field"><label class="form-label">商品名称</label><input class="form-input" id="fc-item" maxlength="100" placeholder="如：运动鞋"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">数量</label><input class="form-input" type="number" id="fc-qty" min="1" value="1"></div>
            <div class="form-field"><label class="form-label">申报价值</label><input class="form-input" type="number" step="0.01" min="0" id="fc-value" value="0"></div>
            <div class="form-field"><label class="form-label">币种</label>
                <select class="form-input" id="fc-currency">
                    <option value="USD">USD 美元</option><option value="CNY">CNY 人民币</option>
                    <option value="EUR">EUR 欧元</option><option value="GBP">GBP 英镑</option>
                    <option value="JPY">JPY 日元</option>
                </select></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">物品类别</label>
                <select class="form-input" id="fc-category">
                    <?php foreach (Status::PACKAGE_CATEGORY as $code => $label): ?>
                        <option value="<?= $code ?>"><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-field"><label class="form-label">预计到仓日期（选填）</label><input class="form-input" type="date" id="fc-eta"></div>
            <div class="form-field"><label class="form-label">备注（选填）</label><input class="form-input" id="fc-remark" maxlength="500" placeholder="如：易碎请轻拿轻放"></div>
        </div>
        <p class="form-hint">类别会影响可选渠道与是否加收敏感货附加费，请如实选择。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveForecast()">保存</button>
    </div>
</dialog>

<script>
function openForecast(row) {
    var isNew = !row;
    document.getElementById('fc-title').textContent = isNew ? '新增包裹预报' : '修改预报 · ' + row.package_no;
    document.getElementById('fc-id').value = isNew ? 0 : row.id;
    document.getElementById('fc-tracking').value = isNew ? '' : (row.tracking_no || '');
    document.getElementById('fc-express').value = isNew ? '' : (row.express_company || '');
    document.getElementById('fc-item').value = isNew ? '' : row.item_name;
    document.getElementById('fc-qty').value = isNew ? 1 : row.item_qty;
    document.getElementById('fc-value').value = isNew ? '0' : row.declared_value;
    document.getElementById('fc-currency').value = isNew ? 'USD' : row.currency;
    document.getElementById('fc-category').value = isNew ? '1' : row.category;
    document.getElementById('fc-eta').value = isNew ? '' : (row.expected_arrival || '');
    document.getElementById('fc-remark').value = isNew ? '' : (row.remark || '');
    document.getElementById('dlg-fc').showModal();
}

function saveForecast() {
    var tracking = val('fc-tracking').trim();
    var item = val('fc-item').trim();
    if (!tracking) { Notify.warning('请填写快递单号'); return; }
    if (item.length < 2) { Notify.warning('请填写商品名称'); return; }
    admConfirm('确定保存该包裹预报吗？', async function () {
        var res = await App.post('/user/forecast/save', {
            id: val('fc-id'), warehouse_id: 0, tracking_no: tracking, express_company: val('fc-express').trim(),
            item_name: item, item_qty: val('fc-qty'), declared_value: val('fc-value'),
            currency: val('fc-currency'), category: val('fc-category'),
            expected_arrival: val('fc-eta'), remark: val('fc-remark').trim()
        });
        App.handle(res, '预报已保存');
        if (res.success) { document.getElementById('dlg-fc').close(); admRefresh(); }
    }, { title: '保存包裹预报', confirmText: '确认保存' });
}
document.getElementById('f-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { admSearch('/user/forecast', { kw: this.value }); }
});
</script>
