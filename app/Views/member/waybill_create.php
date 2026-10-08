<?php declare(strict_types=1);
/**
 * @file app/Views/member/waybill_create.php
 * @description 会员中心：新建转运（选包裹 → 选地址 → 选渠道 → 增值服务 → 试算 → 提交）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $packages
 * @var int   $packageTotal
 * @var array $addresses
 * @var array $channels
 * @var array $services
 * @var array $priceModes
 * @var int   $preselect
 * @var float $insuranceRate
 * @var float $balance
 */
?>
<?php if ($packages === []): ?>
    <section class="panel">
        <div class="empty-state" style="padding:48px 24px">
            <div style="font-size:40px;margin-bottom:12px">📦</div>
            <h3 style="font-size:18px;margin-bottom:8px">暂无可转运的包裹</h3>
            <p>包裹到仓后需要先<b>认领</b>，认领完成的包裹才能提交转运。</p>
            <p style="margin-top:16px"><a class="btn btn--primary" href="/user/packages">去我的包裹认领</a></p>
        </div>
    </section>
<?php else: ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">1 · 选择包裹</span><span class="rule"></span>
        <span class="form-hint">已选 <b id="sel-count">0</b> 个 · 计费重量 <b id="sel-weight">0.000</b> kg</span>
    </h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th style="width:44px"></th><th>包裹编号</th><th>商品</th><th>实重</th><th>体积重</th><th>申报价值</th></tr></thead>
            <tbody>
            <?php foreach ($packages as $p): ?>
                <tr>
                    <td><input type="checkbox" class="pk" value="<?= (int) $p['id'] ?>"
                               data-weight="<?= (float) ($p['weight'] ?? 0) ?>"
                               data-volume="<?= (float) ($p['volume_weight'] ?? 0) ?>"
                               data-value="<?= (float) $p['declared_value'] ?>"
                               onchange="recalc()" <?= $preselect === (int) $p['id'] ? 'checked' : '' ?>></td>
                    <td class="mono"><?= View::e($p['package_no']) ?></td>
                    <td><?= View::e($p['item_name']) ?> ×<?= (int) $p['item_qty'] ?></td>
                    <td class="nowrap"><?= $p['weight'] !== null ? number_format((float) $p['weight'], 3) . ' kg' : '—' ?></td>
                    <td class="nowrap"><?= $p['volume_weight'] !== null ? number_format((float) $p['volume_weight'], 3) . ' kg' : '—' ?></td>
                    <td class="nowrap">¥<?= number_format((float) $p['declared_value'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($packageTotal > count($packages)): ?>
        <p class="form-hint" style="margin-top:10px">待转运包裹共 <?= (int) $packageTotal ?> 件，当前显示最近 <?= count($packages) ?> 件；其余包裹请待本单提交后再来合并。</p>
    <?php endif; ?>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">2 · 转运方式与收货地址</span><span class="rule"></span></h3>
    <?php if ($addresses === []): ?>
        <div class="empty-state">还没有收货地址。<a href="/user/shipping">先去添加</a>，再回来提交转运。</div>
    <?php else: ?>
        <div class="form-row">
            <div class="form-field"><label class="form-label">转运方式</label>
                <select class="form-input" id="wb-type">
                    <option value="1">原箱转运（单包裹原箱直发）</option>
                    <option value="2" selected>合箱转运（多包裹合并一箱）</option>
                </select></div>
            <div class="form-field"><label class="form-label">收货地址</label>
                <select class="form-input" id="wb-address" onchange="recalc()">
                    <?php foreach ($addresses as $a): ?>
                        <option value="<?= (int) $a['id'] ?>" data-country="<?= View::e($a['country']) ?>">
                            <?= View::e($a['receiver_name']) ?> · <?= View::e($a['country']) ?> <?= View::e($a['city']) ?><?= (int) $a['is_default'] === 1 ? '（默认）' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select></div>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">3 · 物流渠道</span><span class="rule"></span></h3>
    <?php if ($channels === []): ?>
        <div class="empty-state">暂无可用渠道，请联系客服。</div>
    <?php else: ?>
        <div class="form-row">
            <div class="form-field" style="max-width:420px">
                <label class="form-label">选择渠道</label>
                <select class="form-input" id="wb-channel" onchange="recalc()">
                    <option value="">请选择渠道</option>
                    <?php foreach ($channels as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" data-countries="<?= View::e(implode(',', $c['countries'])) ?>">
                            <?= View::e($c['name']) ?> · <?= View::e($c['carrier']) ?> · <?= View::e($c['aging_desc'] ?: '时效待定') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field" style="max-width:220px">
                <label class="form-label">保价金额（选填）</label>
                <input class="form-input" type="number" step="0.01" min="0" id="wb-insurance" value="0" onchange="recalc()">
                <p class="form-hint">费率 <?= $insuranceRate ?>%，按申报价值赔付</p>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">4 · 增值服务</span><span class="rule"></span></h3>
    <?php if ($services === []): ?>
        <div class="empty-state">当前暂无可选的增值服务，如需拍照、加固、换箱等操作请在备注中说明，客服会与你确认。</div>
    <?php else: ?>
    <div class="card-grid card-grid--4">
        <?php foreach ($services as $s): ?>
            <label class="card" style="cursor:pointer;padding:14px">
                <div style="display:flex;align-items:center;gap:8px">
                    <input type="checkbox" class="svc" value="<?= (int) $s['id'] ?>" onchange="recalc()">
                    <b><?= View::e($s['name']) ?></b>
                </div>
                <p class="form-hint" style="margin-top:6px"><?= View::e($s['description']) ?></p>
                <span class="badge badge--purple">¥<?= number_format((float) $s['price'], 2) ?>
                    <?= View::e($priceModes[(int) $s['price_mode']] ?? '') ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">5 · 报关明细</span><span class="rule"></span>
        <button class="btn btn--ghost" type="button" onclick="addDeclareRow()">+ 添加一行</button>
    </h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>品名（中文）</th><th>品名（英文）</th><th>HS 编码</th><th>数量</th><th>单位</th><th>申报单价</th><th>币种</th><th>品牌</th><th></th></tr></thead>
            <tbody id="declare-body"></tbody>
        </table>
    </div>
    <p class="form-hint">用于目的国清关申报，中英文品名必填；申报价值需与包裹申报一致，虚报低报导致的清关损失由用户承担。</p>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--emerald">6 · 费用明细与提交</span><span class="rule"></span></h3>
    <div id="quote-box">
        <div class="empty-state">选择包裹与渠道后，这里会实时显示运费明细。</div>
    </div>
    <div class="form-field" style="margin-top:14px">
        <label class="form-label">备注（选填）</label>
        <input class="form-input" id="wb-remark" maxlength="500" placeholder="如：请保留原包装">
    </div>
    <div class="adm-toolbar" style="margin-bottom:0" id="wb-submit-bar">
        <button class="btn btn--ghost" type="button" onclick="recalc()">重新试算</button>
        <button class="btn btn--primary" type="button" id="wb-submit" onclick="submitWaybill()" disabled>提交转运订单</button>
        <span class="form-hint" style="margin-left:auto">账户余额 ¥<?= number_format($balance, 2) ?></span>
    </div>
</section>
<?php endif; ?>

<script>
var CHANNELS = <?= json_encode(array_map(static fn($c) => ['id' => (int) $c['id'], 'countries' => $c['countries']], $channels), JSON_UNESCAPED_UNICODE) ?>;
var BALANCE = <?= (float) $balance ?>;

// 报关明细行管理
function addDeclareRow() {
    var tbody = document.getElementById('declare-body');
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input class="form-input dc-cn" style="min-width:140px"></td>'
        + '<td><input class="form-input dc-en" style="min-width:140px"></td>'
        + '<td><input class="form-input dc-hs" style="min-width:90px"></td>'
        + '<td><input class="form-input dc-qty" type="number" min="1" value="1" style="width:74px"></td>'
        + '<td><input class="form-input dc-unit" value="件" style="width:70px"></td>'
        + '<td><input class="form-input dc-price" type="number" step="0.01" min="0" value="0" style="width:90px"></td>'
        + '<td><input class="form-input dc-currency" value="USD" style="width:70px"></td>'
        + '<td><input class="form-input dc-brand" style="min-width:90px"></td>'
        + '<td><button class="btn btn--ghost btn--sm" type="button" onclick="this.closest(\'tr\').remove()">删</button></td>';
    tbody.appendChild(tr);
}
function collectDeclare() {
    var rows = document.querySelectorAll('#declare-body tr');
    var out = { declare_name_cn: [], declare_name_en: [], declare_hs_code: [], declare_qty: [], declare_unit: [], declare_unit_price: [], declare_currency: [], declare_brand: [] };
    rows.forEach(function (tr) {
        out.declare_name_cn.push(tr.querySelector('.dc-cn').value.trim());
        out.declare_name_en.push(tr.querySelector('.dc-en').value.trim());
        out.declare_hs_code.push(tr.querySelector('.dc-hs').value.trim());
        out.declare_qty.push(tr.querySelector('.dc-qty').value || 1);
        out.declare_unit.push(tr.querySelector('.dc-unit').value.trim() || '件');
        out.declare_unit_price.push(tr.querySelector('.dc-price').value || 0);
        out.declare_currency.push(tr.querySelector('.dc-currency').value.trim() || 'USD');
        out.declare_brand.push(tr.querySelector('.dc-brand').value.trim());
    });
    return out;
}

function selectedPackages() {
    return Array.prototype.filter.call(document.querySelectorAll('.pk'), function (b) { return b.checked; });
}
function selectedServices() {
    var map = {};
    Array.prototype.forEach.call(document.querySelectorAll('.svc'), function (b) {
        if (b.checked) { map[b.value] = 1; }
    });
    return map;
}
function currentQuote() { return window.__quote || null; }

function recalc() {
    var boxes = selectedPackages();
    var weight = 0, volume = 0, value = 0;
    boxes.forEach(function (b) {
        weight += parseFloat(b.dataset.weight || 0);
        volume += parseFloat(b.dataset.volume || 0);
        value += parseFloat(b.dataset.value || 0);
    });
    var billable = Math.max(weight, volume);
    document.getElementById('sel-count').textContent = boxes.length;
    document.getElementById('sel-weight').textContent = billable.toFixed(3);
    document.getElementById('wb-submit').disabled = true;

    var channelId = val('wb-channel');
    var addrSel = document.getElementById('wb-address');
    var country = addrSel && addrSel.selectedOptions[0] ? addrSel.selectedOptions[0].dataset.country : '';
    window.__quote = null;

    if (boxes.length === 0 || !channelId || !country || billable <= 0) {
        document.getElementById('quote-box').innerHTML = '<div class="empty-state">'
            + (billable <= 0 && boxes.length > 0 ? '所选包裹尚未称重，请等待仓库完成称重。' : '选择包裹与渠道后，这里会实时显示运费明细。')
            + '</div>';
        return;
    }

    App.post('/user/waybills/estimate', {
        channel_id: channelId, dest_country: country, weight: billable, declared_value: value,
        services: selectedServices(), insurance_amount: val('wb-insurance') || 0
    }).then(function (res) {
        if (!res.success) {
            document.getElementById('quote-box').innerHTML = '<div class="empty-state">' + (res.message || '试算失败') + '</div>';
            return;
        }
        window.__quote = res.data;
        renderQuote(res.data);
        document.getElementById('wb-submit').disabled = false;
    });
}

function money(n) { return '¥' + Number(n).toFixed(2); }

function renderQuote(q) {
    var rows = [
        ['运费（首重 ' + q.first_weight + 'kg ' + money(q.first_fee) + '，续重 ' + q.step_count + ' × ' + money(q.step_fee) + '）', q.freight_amount],
        ['燃油附加费', q.fuel_fee], ['偏远附加费', q.remote_fee], ['敏感货附加费', q.sensitive_fee],
        ['其他附加费', q.other_surcharge_fee],
        ['增值服务', q.service_amount], ['保价费', q.insurance_fee]
    ];
    var html = '<div class="hero-address">';
    rows.forEach(function (r) {
        if (Number(r[1]) > 0) {
            html += '<div class="hero-address__row"><span>' + r[0] + '</span><b>' + money(r[1]) + '</b></div>';
        }
    });
    if (Number(q.discount_amount) > 0) {
        html += '<div class="hero-address__row"><span>会员折扣（' + (q.discount_rate * 10).toFixed(1) + ' 折）</span><b class="v-money">- ' + money(q.discount_amount) + '</b></div>';
    }
    html += '<div class="hero-address__row"><span><b>应付合计</b></span><b style="color:#4f46e5;font-size:18px">' + money(q.total_amount) + '</b></div>';
    html += '</div>';
    document.getElementById('quote-box').innerHTML = html;
}

async function submitWaybill() {
    var boxes = selectedPackages();
    var ids = boxes.map(function (b) { return Number(b.value); });
    if (ids.length === 0) { Notify.warning('请至少选择一个包裹'); return; }
    var addrSel = document.getElementById('wb-address');
    if (!addrSel || !addrSel.value) { Notify.warning('请选择收货地址'); return; }
    var q = currentQuote();
    if (!q) { Notify.warning('请先完成运费试算'); return; }

    // 提交即扣款：余额不足直接引导充值
    if (BALANCE < Number(q.total_amount)) {
        Confirm.show({
            title: '余额不足',
            message: '应付 ' + money(q.total_amount) + '，当前余额 ' + money(BALANCE) + '。请先充值后再提交转运。',
            confirmText: '去充值',
            onConfirm: function () { location.href = '/user/finance/recharge'; }
        });
        return;
    }
    // 报关明细前置校验：至少一行且中英文品名成对
    var rows = document.querySelectorAll('#declare-body tr');
    var hasValid = false;
    rows.forEach(function (tr) {
        var cn = tr.querySelector('.dc-cn').value.trim();
        var en = tr.querySelector('.dc-en').value.trim();
        if (cn !== '' || en !== '') { hasValid = true; }
    });
    if (!hasValid) { Notify.warning('请至少填写一行报关明细（中文品名 + 英文品名）'); return; }

    admConfirm('本单合计 ' + money(q.total_amount) + '，提交时将从余额扣除运费，包裹进入打包流程。确定提交吗？', async function () {
        var payload = Object.assign({
            package_ids: ids, address_id: addrSel.value, bill_type: val('wb-type'),
            channel_id: val('wb-channel'), services: selectedServices(),
            insurance_amount: val('wb-insurance') || 0, remark: val('wb-remark')
        }, collectDeclare());
        var res = await App.post('/user/waybills/save', payload);
        if (res.success) {
            Notify.success(res.message || '转运订单已提交');
            if (res.data && res.data.id) {
                // 就地切换为提交成功态并给出前往运单的入口，不整页跳转（规范 §2.5）
                var bar = document.getElementById('wb-submit-bar');
                if (bar) {
                    bar.innerHTML = '<span class="badge badge--emerald">提交成功</span>'
                        + '<span class="form-hint">运费已从余额扣除，包裹已进入打包流程。</span>'
                        + '<a class="btn btn--primary" href="/user/waybills/' + res.data.id + '">查看运单详情</a>'
                        + '<a class="btn btn--ghost" href="/user/tracking?waybill=' + res.data.id + '">跟踪物流</a>'
                        + '<a class="btn btn--ghost" href="/user/waybills">返回转运订单</a>';
                }
            }
            return;
        }
        if (res.message && res.message.indexOf('余额不足') >= 0) {
            Confirm.show({
                title: '余额不足',
                message: res.message,
                confirmText: '去充值',
                onConfirm: function () { location.href = '/user/finance/recharge'; }
            });
            return;
        }
        Notify.error(res.message || '提交失败');
    }, { title: '提交转运订单', confirmText: '确认提交并支付' });
}

addDeclareRow();
recalc();
</script>
