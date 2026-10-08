<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/outbound.php
 * @description 出库交接：待出库运单（用户已支付）录入承运单号并确认出库（承运商为只读）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 * @var array  $stats
 * @var array  $perms
 */
?>
<section class="stat-grid">
    <div class="stat-card stat-card--violet"><span class="stat-card__value"><?= (int) ($stats['pending'] ?? 0) ?></span><span class="stat-card__label">待出库</span></div>
    <div class="stat-card stat-card--green"><span class="stat-card__value"><?= (int) ($stats['shipped'] ?? 0) ?></span><span class="stat-card__label">已出库</span></div>
    <div class="stat-card stat-card--blue"><span class="stat-card__value"><?= (int) ($stats['today'] ?? 0) ?></span><span class="stat-card__label">今日出库</span></div>
</section>
<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--violet">出库交接</span><span class="rule"></span>
        <span class="badge badge--gray">共 <?= (int) $total ?> 单</span>
    </h3>
    <div class="adm-toolbar">
        <input class="form-input" id="ob-kw" placeholder="检索：运单号 / 收件人 / 用户编号 / 承运单号" value="<?= View::e($keyword) ?>">
        <button class="btn btn--primary" onclick="admSearch('/warehouse/outbound', {kw: val('ob-kw')})">检索</button>
    </div>
    <div class="table-wrap" data-table-wrap data-url="/warehouse/outbound?kw=<?= urlencode($keyword) ?>">
        <?= View::partial('warehouse/outbound_table', get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog" id="dlg-outbound">
    <div class="adm-dialog__head">出库交接 · <span id="ob-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="ob-id">
        <div class="form-row">
            <div class="wh-field">
                <label class="form-label">承运商</label>
                <input class="form-input" id="ob-carrier-display" readonly>
            </div>
            <div class="wh-field">
                <label class="form-label">承运单号</label>
                <input class="form-input mono" id="ob-tracking" maxlength="64" placeholder="扫描或输入面单号">
            </div>
            <div class="wh-field">
                <label class="form-label">交接备注</label>
                <input class="form-input" id="ob-remark" maxlength="200" placeholder="如：已交 UPS 上门取件">
            </div>
        </div>
        <p class="form-hint">确认出库后运单状态将变为「已出库」，该操作不可撤销。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="submitOutbound()">确认出库</button>
    </div>
</dialog>

<script>
document.getElementById('ob-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { admSearch('/warehouse/outbound', {kw: this.value}); }
});

function openOutbound(id, no, channel) {
    document.getElementById('ob-id').value = id;
    document.getElementById('ob-no').textContent = no;
    document.getElementById('ob-carrier-display').value = channel || '—';
    document.getElementById('ob-tracking').value = '';
    document.getElementById('ob-remark').value = '';
    document.getElementById('dlg-outbound').showModal();
    document.getElementById('ob-tracking').focus();
}

function submitOutbound() {
    var tracking = val('ob-tracking').trim();
    if (!tracking) { Notify.warning('请填写承运单号'); return; }
    admConfirm('确认出库并记录承运单号 ' + tracking + '？出库后运单转为「已出库」，用户端可跟踪物流，且该操作不可撤销。', async function () {
        var res = await App.post('/warehouse/outbound/save', {
            id: val('ob-id'), tracking_no: tracking, remark: val('ob-remark').trim()
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-outbound').close(); admRefresh(); }
    }, { title: '确认出库', confirmText: '确认出库', danger: true, level: 'L2' });
}
</script>
