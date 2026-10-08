<?php declare(strict_types=1);
/**
 * @file app/Views/admin/invoices.php
 * @description 发票管理页：开票功能开关与开票申请局部区
 * @author AI
 * @version 0.6
 * @date 2026-09-16
 */

use App\Core\View;

/**
 * @var bool   $enabled
 * @var string $listUrl
 * @var array  $perms
 */
?>
<section class="panel">
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($listUrl) ?>">
        <?= View::partial('admin/invoices_region', get_defined_vars()) ?>
    </div>
</section>

<script>
/** 开票功能开关：关闭后用户端隐藏开票入口并停止接受新申请，已提交的申请不受影响 */
function toggleInvoice() {
    var enabled = <?= $enabled ? 'true' : 'false' ?>;
    var msg = enabled
        ? '关闭后用户端将隐藏开票入口，且无法再提交新的开票申请；已提交的申请不受影响，仍可在此处理。确定关闭吗？'
        : '开启后用户端恢复开票入口与申请提交。确定开启吗？';
    admConfirm(msg, async function () {
        var res = await App.post('/admin/finance/invoices/toggle', { enabled: enabled ? '0' : '1' });
        App.handle(res);
        if (res.success) { admRefresh(); }
    }, { title: enabled ? '关闭开票功能' : '开启开票功能', confirmText: '确认', danger: enabled });
}

/** 状态筛选与关键词、翻页共用一份查询参数，切换条件时回到第一页 */
function invoiceQuery(page) {
    var params = new URLSearchParams();
    var st = document.getElementById('iv-status') ? document.getElementById('iv-status').value : '-1';
    var kw = document.getElementById('iv-kw') ? document.getElementById('iv-kw').value.trim() : '';
    if (st !== '' && st !== '-1') { params.set('iv_status', st); }
    if (kw !== '') { params.set('iv_kw', kw); }
    if (page > 1) { params.set('iv_page', page); }
    var qs = params.toString();
    return '/admin/finance/invoices' + (qs ? '?' + qs : '');
}

function invoiceFilter() { admTable(invoiceQuery(1)); }
function invoicePage(n) { admTable(invoiceQuery(n)); }

/** 驳回需填写原因：用户在发票记录里会看到该说明 */
function openReject(id, no) {
    document.getElementById('rj-id').value = id;
    document.getElementById('rj-no').textContent = no;
    document.getElementById('rj-remark').value = '';
    document.getElementById('dlg-reject').showModal();
}

function saveReject() {
    var remark = val('rj-remark').trim();
    if (!remark) { Notify.warning('请填写驳回原因'); return; }
    admConfirm('确定驳回该开票申请吗？驳回后该笔金额不再占用可开票额度，用户可重新提交。', async function () {
        var res = await App.post('/admin/finance/invoices/handle', {
            id: val('rj-id'), result: 'reject', remark: remark
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-reject').close(); admRefresh(); }
    }, { title: '驳回开票申请', confirmText: '确认驳回', danger: true });
}

/** 发票详情：列表一次取回全部字段，直接展示，无需再请求接口 */
function openInvoiceDetail(d) {
    document.getElementById('ivd-no').textContent = d.no;
    document.getElementById('ivd-user').textContent = d.user !== '' ? d.user : '—';
    document.getElementById('ivd-title').textContent = d.title;
    document.getElementById('ivd-tax').textContent = d.tax !== '' ? d.tax : '—';
    document.getElementById('ivd-type').textContent = d.type;
    document.getElementById('ivd-amount').textContent = '¥' + d.amount;
    document.getElementById('ivd-email').textContent = d.email !== '' ? d.email : '—';
    document.getElementById('ivd-remark').textContent = d.remark !== '' ? d.remark : '—';
    document.getElementById('ivd-created').textContent = d.created;
    document.getElementById('ivd-handled').textContent = d.handled !== '' ? d.handled : '—';
    document.getElementById('ivd-handle-remark').textContent = d.handled_remark !== '' ? d.handled_remark : '—';
    var badge = document.getElementById('ivd-status');
    badge.textContent = d.status;
    badge.className = 'badge badge--' + d.tone;
    document.getElementById('dlg-invoice').showModal();
}
</script>
