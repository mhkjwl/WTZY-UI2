<?php declare(strict_types=1);
/**
 * @file app/Views/admin/channel_detail_content.php
 * @description 渠道详情局部内容：渠道概要 / 分区 / 首重续重价格 / 附加费维护（操作后整区刷新）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $channel
 * @var array $zones
 * @var array $prices    每行额外带 state：active=生效中 / scheduled=待生效
 * @var array $surcharges
 * @var array $perms
 */
$taxModes = [1 => '到付', 2 => '预付', 3 => '双清包税'];
$feeTypes = [1 => '燃油', 2 => '偏远', 3 => '敏感货', 4 => '操作处理', 5 => '住宅派送', 6 => '其他'];
$calcModes = [1 => '固定金额', 2 => '每 kg', 3 => '按运费 %', 4 => '按货值 %'];
$canManage = !empty($perms['channel.manage']);

// 已有配置按分区/按类型索引，供保存前提示「将覆盖哪一条」
$priceByZone = [];
foreach ($prices as $p) {
    $priceByZone[(int) $p['zone_id']][($p['state'] ?? 'active') === 'scheduled' ? 'scheduled' : 'active'] = [
        'first_weight'   => $p['first_weight'],
        'first_fee'      => $p['first_fee'],
        'effective_from' => $p['effective_from'],
    ];
}
$surchargeByType = [];
foreach ($surcharges as $s) {
    $surchargeByType[(int) $s['fee_type']] = ['name' => $s['name'], 'fee_value' => $s['fee_value'], 'calc_mode' => $s['calc_mode']];
}
?>
<section class="module-hero">
    <div class="module-hero__icon" style="background:#0891b2">🚛</div>
    <div>
        <div class="module-hero__title"><?= View::e($channel['name']) ?>
            <span class="badge badge--<?= (int) $channel['status'] === 1 ? 'green' : 'gray' ?>" style="margin-left:8px"><?= (int) $channel['status'] === 1 ? '启用' : '停用' ?></span>
        </div>
        <div class="module-hero__desc">
            <span class="mono"><?= View::e($channel['channel_code']) ?></span> ·
            承运商 <?= View::e($channel['carrier']) ?> ·
            <?= (int) $channel['goods_type'] === 1 ? '普货' : '敏感货' ?> ·
            体积重 ÷<?= (int) $channel['volume_divisor'] ?> ·
            <?= View::e($channel['aging_desc'] ?: '时效未配置') ?> ·
            <?= View::e($taxModes[(int) $channel['tax_mode']]) ?>
        </div>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">分区</span><span class="rule"></span></h3>
    <div class="table-scroll">
    <table class="table">
        <thead><tr><th>分区</th><th>国家码</th></tr></thead>
        <tbody>
        <?php if ($zones === []): ?><tr><td colspan="2"><div class="empty-state">暂无分区，请先创建分区（如 1 区：US,CA）再配置价格。</div></td></tr><?php endif; ?>
        <?php foreach ($zones as $z): ?>
            <tr><td><b><?= View::e($z['zone_name']) ?></b></td>
                <td class="mono"><?= View::e(implode(', ', json_decode((string) $z['countries'], true) ?: [])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($canManage): ?>
        <div class="adm-toolbar" style="margin-top:10px">
            <input class="form-input" id="z-name" placeholder="分区名（如 1区）">
            <input class="form-input" id="z-countries" placeholder="国家码，逗号分隔：US,CA">
            <button class="btn btn--primary btn--sm" onclick="postForm('/admin/channels/zone', {channel_id: <?= (int) $channel['id'] ?>, zone_name: val('z-name'), countries: val('z-countries')})">+ 新增分区</button>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--green">首重续重价格</span><span class="rule"></span></h3>
    <div class="table-scroll">
    <table class="table">
        <thead><tr><th>分区</th><th>状态</th><th>首重 (kg)</th><th>首重费</th><th>续重步进 (kg)</th><th>每步费用</th><th>生效日期</th><th>失效日期</th></tr></thead>
        <tbody>
        <?php if ($prices === []): ?><tr><td colspan="8"><div class="empty-state">暂无生效价格：上方每个分区都还没有价格。运费试算 = 首重费 + ceil(计费重-首重 / 步进) × 步进费 + 附加费。</div></td></tr><?php endif; ?>
        <?php foreach ($prices as $p): ?>
            <?php $scheduled = ($p['state'] ?? 'active') === 'scheduled'; ?>
            <tr>
                <td><?= View::e($p['zone_name'] ?? '已删除分区') ?></td>
                <td><span class="badge badge--<?= $scheduled ? 'amber' : 'green' ?>"><?= $scheduled ? '待生效' : '生效中' ?></span></td>
                <td><?= View::e((string) $p['first_weight']) ?></td>
                <td>¥<?= number_format((float) $p['first_fee'], 2) ?></td>
                <td><?= View::e((string) $p['step_weight']) ?></td>
                <td>¥<?= number_format((float) $p['step_fee'], 2) ?></td>
                <td class="nowrap"><?= View::e($p['effective_from']) ?></td>
                <td class="nowrap"><?= View::e($p['effective_to'] ?: '长期') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="form-hint">每个分区最多两条：<b>生效中</b>的那条正在计费，<b>待生效</b>的那条是预约调价、到生效日自动接替。重新保存同一分区即覆盖对应那条，不再堆积历史价格。</p>
    <?php if ($canManage): ?>
        <div class="adm-toolbar" style="margin:12px 0 0">
            <select class="form-input" id="p-zone"><?php foreach ($zones as $z): ?><option value="<?= (int) $z['id'] ?>"><?= View::e($z['zone_name']) ?></option><?php endforeach; ?></select>
            <input class="form-input" type="number" step="0.001" id="p-fw" placeholder="首重kg" style="width:110px">
            <input class="form-input" type="number" step="0.01" id="p-ff" placeholder="首重费" style="width:100px">
            <input class="form-input" type="number" step="0.001" id="p-sw" placeholder="步进kg" style="width:100px" value="0.5">
            <input class="form-input" type="number" step="0.01" id="p-sf" placeholder="步进费" style="width:100px">
            <input class="form-input" type="date" id="p-from" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" style="width:150px">
            <button class="btn btn--primary btn--sm" onclick="savePrice()">保存该分区价格</button>
        </div>
        <p class="form-hint">生效日期选今天就立即生效；选将来的日期则作为预约调价，到当天自动接替当前价格（当前价格在生效日前继续沿用）。</p>
    <?php endif; ?>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--orange">附加费</span><span class="rule"></span></h3>
    <div class="table-scroll">
    <table class="table">
        <thead><tr><th>类型</th><th>名称</th><th>计算方式</th><th>基准值</th></tr></thead>
        <tbody>
        <?php if ($surcharges === []): ?><tr><td colspan="4"><div class="empty-state">暂无附加费（燃油 / 偏远 / 敏感货等在此配置）。</div></td></tr><?php endif; ?>
        <?php foreach ($surcharges as $s): ?>
            <tr>
                <td><span class="badge badge--orange"><?= View::e($feeTypes[(int) $s['fee_type']]) ?></span></td>
                <td><?= View::e($s['name']) ?></td>
                <td><?= View::e($calcModes[(int) $s['calc_mode']]) ?></td>
                <td><?= View::e((string) $s['fee_value']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="form-hint">这里只显示<b>正在计费</b>的附加费。同一类型只保留一条，重新保存同一类型即覆盖原设置，避免同类型多条被叠加计算、对用户重复收费。</p>
    <?php if ($canManage): ?>
        <div class="adm-toolbar" style="margin:12px 0 0">
            <select class="form-input" id="s-type"><?php foreach ($feeTypes as $k => $v): ?><option value="<?= $k ?>"><?= View::e($v) ?></option><?php endforeach; ?></select>
            <input class="form-input" id="s-name" placeholder="名称">
            <select class="form-input" id="s-mode"><?php foreach ($calcModes as $k => $v): ?><option value="<?= $k ?>"><?= View::e($v) ?></option><?php endforeach; ?></select>
            <input class="form-input" type="number" step="0.01" id="s-value" placeholder="基准值" style="width:100px">
            <button class="btn btn--primary btn--sm" onclick="saveSurcharge()">保存该类型附加费</button>
        </div>
    <?php endif; ?>
</section>

<script>
var ZONE_PRICES = <?= json_encode($priceByZone, JSON_UNESCAPED_UNICODE) ?>;
var TYPE_SURCHARGES = <?= json_encode($surchargeByType, JSON_UNESCAPED_UNICODE) ?>;

function optionText(id) {
    var sel = document.getElementById(id);
    return sel && sel.selectedOptions[0] ? sel.selectedOptions[0].textContent.trim() : '';
}

function savePrice() {
    var zoneId = val('p-zone');
    var zoneText = optionText('p-zone');
    var from = val('p-from');
    var today = '<?= date('Y-m-d') ?>';
    var saved = ZONE_PRICES[zoneId] || {};
    var message;
    if (from > today) {
        message = '「' + zoneText + '」的预约价格将于 ' + from + ' 起自动生效'
            + (saved.scheduled ? '，将覆盖已预约的那条。' : '，当前价格继续沿用到生效日前一天。');
    } else if (saved.active) {
        message = '「' + zoneText + '」已有价格（首重 ' + saved.active.first_weight + ' kg / ¥'
            + Number(saved.active.first_fee).toFixed(2) + '），保存后将覆盖原设置并立即参与运费试算。';
    } else {
        message = '「' + zoneText + '」暂无生效价格，保存后立即参与运费试算。';
    }
    postForm('/admin/channels/price', {
        channel_id: <?= (int) $channel['id'] ?>, zone_id: zoneId, first_weight: val('p-fw'), first_fee: val('p-ff'),
        step_weight: val('p-sw'), step_fee: val('p-sf'), effective_from: from, effective_to: ''
    }, message, '保存分区价格');
}

function saveSurcharge() {
    var type = val('s-type');
    var typeText = optionText('s-type');
    var old = TYPE_SURCHARGES[type];
    var message = old
        ? '该渠道已有「' + typeText + '」附加费（' + old.name + '），保存后将覆盖原设置并立即参与运费试算。'
        : '确认新增「' + typeText + '」附加费吗？保存后立即参与运费试算。';
    postForm('/admin/channels/surcharge', {
        channel_id: <?= (int) $channel['id'] ?>, fee_type: type, name: val('s-name'),
        calc_mode: val('s-mode'), fee_value: val('s-value')
    }, message, '保存附加费');
}

function postForm(url, data, message, title) {
    Confirm.show({
        title: title || '确认提交', message: message || '确认提交该配置吗？', confirmText: '提交',
        onConfirm: async function () {
            var res = await App.post(url, data);
            App.handle(res);
            if (res.success) { admRefresh(); }
        }
    });
}
</script>
