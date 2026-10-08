<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/storage.php
 * @description 仓储管理：在库检索 / 上架移库 / 无主件指定归属 / 库位占用一览
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
 * @var int    $status
 * @var array  $counts
 * @var array  $unattributed
 * @var int    $unattributedTotal
 * @var array  $locations
 * @var array  $locationStats
 * @var array  $locationList
 * @var array  $locTypes
 * @var array  $locStatus
 * @var array  $statuses
 * @var array  $perms
 */
$inStock = 0;
foreach ($counts as $code => $count) {
    if ((int) $code >= 2 && (int) $code <= 9) {
        $inStock += (int) $count;
    }
}
$initialUrl = '/warehouse/storage?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status],
    static fn($v): bool => $v !== '' && $v !== -1
));
?>
<section class="astat-grid">
    <div class="astat astat--emerald"><div class="astat__icon">🏗️</div>
        <div><div class="astat__value"><?= (int) $inStock ?></div><div class="astat__label">在库包裹</div></div></div>
    <div class="astat astat--orange"><div class="astat__icon">🔔</div>
        <div><div class="astat__value"><?= (int) $unattributedTotal ?></div><div class="astat__label">无主件待归属</div></div></div>
    <div class="astat astat--blue"><div class="astat__icon">📐</div>
        <div><div class="astat__value"><?= (int) $locationStats['free'] ?></div><div class="astat__label">空闲库位</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">📦</div>
        <div><div class="astat__value"><?= (int) $locationStats['used'] ?></div><div class="astat__label">已占用库位</div></div></div>
</section>

<?php if ($unattributed !== []): ?>
<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--orange">无主件待归属</span><span class="rule"></span>
        <span class="badge badge--gray">共 <?= (int) $unattributedTotal ?> 件</span>
    </h3>
    <p class="form-hint" style="margin-bottom:14px">入库时未匹配到预报记录的包裹；指定归属后转为「已到仓」。</p>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>包裹编号</th><th>快递单号</th><th>商品</th><th>重量</th><th>入库时间</th><th class="nowrap">指定归属（编号 / 识别码）</th></tr></thead>
            <tbody>
            <?php foreach ($unattributed as $u): ?>
                <tr data-id="<?= (int) $u['id'] ?>">
                    <td class="mono"><?= View::e($u['package_no']) ?></td>
                    <td class="mono"><?= View::e($u['tracking_no'] ?: '—') ?></td>
                    <td><?= View::e($u['item_name']) ?> ×<?= (int) $u['item_qty'] ?></td>
                    <td class="nowrap"><?= $u['weight'] !== null ? number_format((float) $u['weight'], 3) . ' kg' : '—' ?></td>
                    <td class="nowrap"><?= View::e(substr((string) ($u['inbound_at'] ?? $u['created_at']), 0, 16)) ?></td>
                    <td class="nowrap">
                        <input class="form-input mono" id="at-no-<?= (int) $u['id'] ?>" placeholder="编号或识别码" style="width:150px;display:inline-block">
                        <button class="btn btn--primary btn--sm" onclick="attribute(<?= (int) $u['id'] ?>, '<?= View::e($u['package_no']) ?>')">归属</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($unattributedTotal > count($unattributed)): ?>
        <p class="form-hint" style="margin-top:10px">仅显示最近 15 件，其余 <?= (int) $unattributedTotal - count($unattributed) ?> 件在当前批次处理完后会自动补上。</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="st-kw" placeholder="检索：包裹编号 / 快递单号 / 识别码 / 用户编号" value="<?= View::e($keyword) ?>">
        <select class="form-input" id="st-status">
            <option value="-1">全部在库状态</option>
            <?php foreach ($statuses as $code => $label): ?>
                <option value="<?= (int) $code ?>" <?= $status === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--primary" onclick="admSearch('/warehouse/storage', {kw: val('st-kw'), status: val('st-status')})">检索</button>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('warehouse/storage_table', get_defined_vars()) ?>
    </div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--cyan">库位占用</span><span class="rule"></span>
        <span class="badge badge--gray">本仓共 <?= (int) $locationStats['total'] ?> 个</span>
    </h3>
    <div data-loc-wrap data-url="<?= View::e($locBase) ?>">
        <?= View::partial('warehouse/storage_locations', get_defined_vars()) ?>
    </div>
</section>

<script>
document.getElementById('st-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { admSearch('/warehouse/storage', {kw: this.value, status: val('st-status')}); }
});

/** 无主件指定归属：可用用户编号，也可直接照面单上的识别码录入 */
function attribute(packageId, packageNo) {
    var input = document.getElementById('at-no-' + packageId);
    var account = (input ? input.value : '').trim();
    if (!account) { Notify.warning('请输入用户编号或识别码'); return; }
    admConfirm('确定把包裹 ' + packageNo + ' 归属到「' + account + '」吗？归属后该用户即可在自己的包裹列表中看到并认领。', async function () {
        var res = await App.post('/warehouse/storage/attribute', { id: packageId, user_no: account });
        App.handle(res);
        if (res.success) { admRefresh(); }
    }, { title: '指定归属', confirmText: '确认归属' });
}

/** 上架 / 移库 */
function shelve(packageId, packageNo, current) {
    var options = '<option value="">请选择库位</option>';
    LOCS.forEach(function (l) {
        var label = l.code + '（' + l.type_name + (l.status === 2 && l.id !== current ? '·占用中' : '') + '）';
        options += '<option value="' + l.id + '"' + (l.id === current ? ' selected' : '') + '>' + label + '</option>';
    });
    showShelveDialog(packageId, packageNo, options);
}

var LOCS = <?= json_encode(array_map(static fn(array $l): array => [
    'id'        => (int) $l['id'],
    'code'      => (string) $l['code'],
    'status'    => (int) $l['status'],
    'type_name' => $locTypes[(int) $l['type']] ?? '库位',
], $locations), JSON_UNESCAPED_UNICODE) ?>;
</script>

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

<script>
function showShelveDialog(packageId, packageNo, optionsHtml) {
    document.getElementById('sh-id').value = packageId;
    document.getElementById('sh-no').textContent = packageNo;
    document.getElementById('sh-location').innerHTML = optionsHtml;
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
        if (res.success) { document.getElementById('dlg-shelve').close(); admRefresh(); locRefresh(); }
    }, { title: '上架确认', confirmText: '确认上架' });
}
</script>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-loc">
    <div class="adm-dialog__head">库位详情 · <span id="loc-code"></span></div>
    <div class="adm-dialog__body">
        <div class="hero-address">
            <div class="hero-address__row"><span>库位编码</span><b class="v-code mono" id="loc-code-v"></b></div>
            <div class="hero-address__row"><span>类型</span><span id="loc-type-v"></span></div>
            <div class="hero-address__row"><span>状态</span><span id="loc-status-v"></span></div>
            <div class="hero-address__row"><span>当前包裹</span><span id="loc-pkg-v"></span></div>
            <div class="hero-address__row"><span>商品</span><b id="loc-item-v"></b></div>
            <div class="hero-address__row"><span>更新时间</span><b id="loc-updated-v"></b></div>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--primary" onclick="admCloseDialog(this)">关闭</button>
    </div>
</dialog>

<script>
var LOC_TYPES = <?= json_encode($locTypes, JSON_UNESCAPED_UNICODE) ?>;
var LOC_STATUS = <?= json_encode($locStatus, JSON_UNESCAPED_UNICODE) ?>;

function locBadge(label, tone) {
    return '<span class="badge badge--' + tone + '">' + label + '</span>';
}

/**
 * 库位区块局部刷新：翻页与筛选只替换 [data-loc-wrap]，不影响上面的在库包裹表
 * 权限被回收返回 403、会话失效跳登录页时都保持原内容并提示，避免把错误页塞进列表区
 */
window.locTable = async function (url) {
    var wrap = document.querySelector('[data-loc-wrap]');
    if (!wrap) { location.href = url; return; }
    wrap.style.opacity = '0.55';
    try {
        var res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
        if (!res.ok || res.redirected) {
            Notify.error(res.status === 403 ? '权限已变更，无法加载库位列表，请刷新页面' : '登录状态已失效，请重新登录');
            return;
        }
        wrap.innerHTML = await res.text();
        wrap.dataset.url = url;
    } catch (err) {
        Notify.error('网络异常，库位列表未能刷新');
    } finally {
        wrap.style.opacity = '1';
    }
};

/** 库位区块检索 / 翻页入口 */
window.locSearch = function (params) {
    var qs = new URLSearchParams(params || {}).toString();
    locTable('<?= View::e($locBase) ?>' + (qs ? '?' + qs : ''));
};

/** 刷新库位区块：上架、移库、下架后调用，保持占用状态同步 */
window.locRefresh = function () {
    var wrap = document.querySelector('[data-loc-wrap]');
    if (wrap && wrap.dataset.url) { locTable(wrap.dataset.url); }
};

/** 下架：成功后同时刷新在库列表与库位区块 */
function unshelve(packageId, packageNo) {
    admConfirm('确定把包裹 ' + packageNo + ' 下架吗？库位将释放为空闲。', async function () {
        var res = await App.post('/warehouse/storage/unshelve', { id: packageId });
        App.handle(res);
        if (res.success) { admRefresh(); locRefresh(); }
    }, { title: '确认下架', confirmText: '确认下架' });
}

/** 库位详情弹窗：读行数据渲染，占用包裹可跳转包裹详情 */
function showLocDetail(el) {
    var tr = el.closest('tr');
    var d = tr.dataset;
    var cells = tr.querySelectorAll('td');
    var code = cells[0].textContent.trim();
    var type = LOC_TYPES[d.type] || '库位';
    var status = LOC_STATUS[d.status] || '—';
    var statusTone = d.status === '2' ? 'orange' : (d.status === '1' ? 'green' : 'gray');
    var pkgId = parseInt(d.packageId || '0', 10);
    var pkgNo = cells[3].textContent.trim() || '—';
    var item = cells[4].textContent.trim() || '—';
    var updated = cells[5].textContent.trim();
    var pkgHtml;
    if (pkgId > 0 && pkgNo !== '—') {
        pkgHtml = '<a class="v-code mono" href="/warehouse/storage/package/' + pkgId + '">' + pkgNo + '</a>';
    } else if (d.status === '0') {
        pkgHtml = locBadge('已停用', 'gray');
    } else {
        pkgHtml = locBadge('空闲可用', 'green');
    }
    document.getElementById('loc-code').textContent = code;
    document.getElementById('loc-code-v').textContent = code;
    document.getElementById('loc-type-v').innerHTML = locBadge(type, 'indigo');
    document.getElementById('loc-status-v').innerHTML = locBadge(status, statusTone);
    document.getElementById('loc-pkg-v').innerHTML = pkgHtml;
    document.getElementById('loc-item-v').textContent = item;
    document.getElementById('loc-updated-v').textContent = updated;
    document.getElementById('dlg-loc').showModal();
}
</script>
