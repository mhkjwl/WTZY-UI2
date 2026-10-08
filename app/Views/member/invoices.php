<?php declare(strict_types=1);
/**
 * @file app/Views/member/invoices.php
 * @description 会员中心：发票管理（开票申请 + 进度；翻页与提交后局部刷新，规范 §2.5）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 * @var float $invoiceable
 * @var bool  $enabled
 */
?>
<?php if (!$enabled): ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--gray">开票功能已关闭</span><span class="rule"></span></h3>
    <p class="form-hint">平台当前暂停受理开票申请，如有开票需求请联系客服。</p>
</section>
<?php else: ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">申请开票</span><span class="rule"></span></h3>
    <p class="form-hint">开票金额以可开票额度为上限（当前可开 ¥<?= number_format($invoiceable, 2) ?>，已扣除已申请的发票金额）。</p>
    <div class="form-row">
        <div class="form-field"><label class="form-label">发票抬头</label><input class="form-input" id="iv-title" maxlength="100" placeholder="个人或公司名称"></div>
        <div class="form-field"><label class="form-label">纳税人识别号（专票必填）</label><input class="form-input mono" id="iv-tax" maxlength="40"></div>
    </div>
    <div class="form-row">
        <div class="form-field"><label class="form-label">发票类型</label>
            <select class="form-input" id="iv-type" onchange="toggleTax()">
                <option value="1">增值税普通发票</option>
                <option value="2">增值税专用发票</option>
            </select></div>
        <div class="form-field"><label class="form-label">开票金额</label>
            <input class="form-input" type="number" step="0.01" min="0.01" id="iv-amount" placeholder="不超过 ¥<?= number_format($invoiceable, 2) ?>"></div>
        <div class="form-field"><label class="form-label">接收邮箱（选填）</label><input class="form-input" id="iv-email" maxlength="128"></div>
    </div>
    <div class="form-field"><label class="form-label">备注（选填）</label><input class="form-input" id="iv-remark" maxlength="200"></div>
    <button class="btn btn--primary" type="button" onclick="createInvoice()">提交开票申请</button>
</section>

<div id="iv-region">
    <?= View::partial('member/invoices_region', get_defined_vars()) ?>
</div>

<script>
/** 当前可开票额度：从统计卡读取，区域刷新后自动同步，避免用页面加载时的旧值校验 */
function invoiceQuota() {
    var el = document.getElementById('iv-available');
    return el ? parseFloat(el.dataset.quota || '0') : 0;
}

/**
 * 局部加载发票统计与申请记录（翻页 / 提交后共用），不整页刷新（规范 §2.5）
 */
async function ivReload(page, push) {
    var region = document.getElementById('iv-region');
    if (!region) { return; }
    region.style.opacity = '0.55';
    try {
        var res = await fetch('/user/finance/invoices?page=' + page, { headers: { 'X-Requested-With': 'fetch' } });
        if (!res.ok || res.redirected) {
            Notify.error('登录状态已失效，请重新登录');
            return;
        }
        region.innerHTML = await res.text();
        if (push !== false) { history.pushState({}, '', '/user/finance/invoices?page=' + page); }
    } catch (err) {
        Notify.error('网络异常，列表未能刷新');
    } finally {
        region.style.opacity = '1';
    }
}

function ivPage(n) { ivReload(n); }

window.addEventListener('popstate', function () {
    var n = parseInt(new URLSearchParams(location.search).get('page'), 10) || 1;
    ivReload(n, false);
});

function toggleTax() {
    var isSpecial = val('iv-type') === '2';
    document.getElementById('iv-tax').placeholder = isSpecial ? '专用发票必填' : '选填';
}

function createInvoice() {
    var title = val('iv-title').trim();
    var amount = parseFloat(val('iv-amount'));
    if (!title) { Notify.warning('请填写发票抬头'); return; }
    if (!(amount > 0)) { Notify.warning('请填写开票金额'); return; }
    if (amount > invoiceQuota() + 0.001) { Notify.warning('开票金额不得超过可开票额度'); return; }
    if (val('iv-type') === '2' && !val('iv-tax').trim()) { Notify.warning('专用发票必须填写纳税人识别号'); return; }

    admConfirm('确定提交 ¥' + amount.toFixed(2) + ' 的开票申请吗？提交后将占用等额可开票额度。', async function () {
        var res = await App.post('/user/finance/invoices/create', {
            title: title, tax_no: val('iv-tax').trim(), type: val('iv-type'),
            amount: amount, email: val('iv-email').trim(), remark: val('iv-remark').trim()
        });
        App.handle(res, '开票申请已提交');
        if (res.success) {
            ['iv-title', 'iv-tax', 'iv-amount', 'iv-email', 'iv-remark'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.value = ''; }
            });
            // 刷新统计与列表区域：同步新申请行、可开票额度与本页计数（规范 §2.5.4 关联区域同步更新）
            ivReload(1);
        }
    }, { title: '提交开票申请', confirmText: '确认提交' });
}
</script>
<?php endif; ?>
