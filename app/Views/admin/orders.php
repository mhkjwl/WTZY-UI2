<?php declare(strict_types=1);
/**
 * @file app/Views/admin/orders.php
 * @description 订单运单管理页：状态统计 + 检索 + 取消/拦截原因对话框
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $rows
 * @var int $page
 * @var int $pages
 * @var string $keyword
 * @var int $status
 * @var array $counts
 * @var array $perms
 * @var string $csrf
 */
$initialUrl = '/admin/orders?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $page],
    static fn($v): bool => $v !== '' && $v !== -1
));
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">🧾</div>
        <div><div class="astat__value"><?= (int) ($counts[1] ?? 0) + (int) ($counts[2] ?? 0) ?></div><div class="astat__label">待称重 / 待支付</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">🚚</div>
        <div><div class="astat__value"><?= (int) ($counts[5] ?? 0) + (int) ($counts[6] ?? 0) ?></div><div class="astat__label">已出库 / 运输中</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">📦</div>
        <div><div class="astat__value"><?= (int) ($counts[9] ?? 0) ?></div><div class="astat__label">已签收</div></div></div>
    <div class="astat astat--red"><div class="astat__icon">⚠️</div>
        <div><div class="astat__value"><?= (int) ($counts[10] ?? 0) + (int) ($counts[12] ?? 0) ?></div><div class="astat__label">售后 / 拦截</div></div></div>
</section>

<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="o-kw" placeholder="运单号 / 承运商单 / 收件人" value="<?= View::e($keyword) ?>">
        <select class="form-input" id="o-status">
            <option value="-1">全部状态</option>
            <?php foreach (Status::WAYBILL as $code => $label): ?>
                <option value="<?= $code ?>" <?= $status === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--primary" onclick="admSearch('<?= View::e($base) ?>', {kw: val('o-kw'), status: val('o-status')})">检索</button>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('admin/orders_table', get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-reason">
    <div class="adm-dialog__head" id="reason-title">操作确认</div>
    <div class="adm-dialog__body">
        <input type="hidden" id="reason-id">
        <input type="hidden" id="reason-type">
        <div class="form-field"><label class="form-label">原因（写入状态流转日志）</label>
            <textarea class="form-input" id="reason-text" rows="3"></textarea></div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--danger" id="reason-btn" onclick="submitReason()">确认执行</button>
    </div>
</dialog>

<dialog class="adm-dialog" id="dlg-track">
    <div class="adm-dialog__head">轨迹录入与状态推进 · <span id="track-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="track-id">
        <div class="form-row">
            <div class="form-field"><label class="form-label">推进到状态</label>
                <select class="form-input" id="track-status"></select></div>
            <div class="form-field"><label class="form-label">轨迹时间</label>
                <input class="form-input" type="datetime-local" id="track-time" value="<?= date('Y-m-d\TH:i') ?>"></div>
        </div>
        <div class="form-field"><label class="form-label">轨迹地点（选填）</label>
            <input class="form-input" id="track-location" placeholder="如：洛杉矶分拨中心"></div>
        <div class="form-field"><label class="form-label">轨迹描述</label>
            <input class="form-input" id="track-desc" placeholder="如：航班已起飞 / 清关放行 / 派送中"></div>
        <p class="form-hint">推进到「已签收」将站内信通知用户并回填签收时间。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="submitTrack()">确认推进</button>
    </div>
</dialog>

<script>
function val(id) { return document.getElementById(id).value; }
function openReason(id, type) {
    document.getElementById('reason-id').value = id;
    document.getElementById('reason-type').value = type;
    document.getElementById('reason-title').textContent = type === 'cancel' ? '取消运单' : '拦截运单';
    document.getElementById('reason-text').value = '';
    document.getElementById('dlg-reason').showModal();
}
function submitReason() {
    var type = val('reason-type');
    var reason = val('reason-text').trim();
    if (!reason) { Notify.warning('请填写原因，将写入状态流转日志'); return; }
    var label = type === 'cancel' ? '取消' : '拦截';
    var url = type === 'cancel' ? '/admin/orders/cancel' : '/admin/orders/intercept';
    admConfirm('确定' + label + '该运单吗？运单状态将变更且会写入流转日志，此操作不可撤销。', async function () {
        var res = await App.post(url, { id: val('reason-id'), reason: reason });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-reason').close(); admRefresh(); }
    }, { title: label + '运单', confirmText: '确认' + label, danger: true });
}
// 运输段推进：从当前状态给出允许的下一状态选项
var TRACK_NEXT = { 5: [[6, '运输中'], [7, '清关中'], [8, '派送中'], [9, '已签收']], 6: [[7, '清关中'], [8, '派送中'], [9, '已签收']], 7: [[8, '派送中'], [9, '已签收']], 8: [[9, '已签收']] };
var TRACK_WAYBILL_ID = 0;
function openTrack(id, status, no) {
    TRACK_WAYBILL_ID = id;
    var sel = document.getElementById('track-status');
    sel.innerHTML = '';
    (TRACK_NEXT[status] || []).forEach(function (item) {
        var opt = document.createElement('option');
        opt.value = item[0];
        opt.textContent = item[0] + ' · ' + item[1];
        sel.appendChild(opt);
    });
    document.getElementById('track-no').textContent = no;
    document.getElementById('track-desc').value = '';
    document.getElementById('track-location').value = '';
    document.getElementById('dlg-track').showModal();
}
async function submitTrack() {
    var desc = val('track-desc').trim();
    if (!desc) { Notify.warning('请填写轨迹描述'); return; }
    var res = await App.post('/admin/orders/advance', {
        id: TRACK_WAYBILL_ID, to_status: val('track-status'),
        status_desc: desc, location: val('track-location'), event_time: val('track-time')
    });
    App.handle(res);
    if (res.success) { document.getElementById('dlg-track').close(); admRefresh(); }
}
document.getElementById('o-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { admSearch('/admin/orders', { kw: this.value, status: val('o-status') }); }
});
</script>
