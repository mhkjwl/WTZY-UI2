<?php declare(strict_types=1);
/**
 * @file app/Views/member/invoices_region.php
 * @description 会员中心：发票统计与申请记录区域（翻页/提交后局部替换，规范 §2.5）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 * @var float $invoiceable
 */
$pending = 0;
foreach ($rows as $r) {
    if ((int) $r['status'] === 0) {
        $pending++;
    }
}
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">🧮</div>
        <div><div class="astat__value" id="iv-available" data-quota="<?= number_format($invoiceable, 2, '.', '') ?>">¥<?= number_format($invoiceable, 2) ?></div><div class="astat__label">可开票额度</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) $pending ?></div><div class="astat__label">本页待开票</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">✅</div>
        <div><div class="astat__value"><?= count($rows) - (int) $pending ?></div><div class="astat__label">本页已处理</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">📄</div>
        <div><div class="astat__value"><?= (int) $total ?></div><div class="astat__label">申请记录</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--gray">申请记录</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>申请单号</th><th>抬头</th><th>类型</th><th>金额</th><th>状态</th><th>处理意见</th><th class="nowrap">申请时间</th><th class="nowrap">操作</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="8"><div class="empty-state">暂无开票申请。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php $ivItem = [
                    'no'             => (string) $r['invoice_no'],
                    'title'          => (string) $r['title'],
                    'tax'            => (string) ($r['tax_no'] ?? ''),
                    'type'           => (int) $r['type'] === 2 ? '增值税专用发票' : '增值税普通发票',
                    'amount'         => number_format((float) $r['amount'], 2),
                    'email'          => (string) ($r['email'] ?? ''),
                    'remark'         => (string) ($r['remark'] ?? ''),
                    'status'         => Status::invoice((int) $r['status']),
                    'tone'           => Status::invoiceTone((int) $r['status']),
                    'created'        => substr((string) $r['created_at'], 0, 16),
                    'handled'        => $r['handled_at'] ? substr((string) $r['handled_at'], 0, 16) : '',
                    'handled_remark' => (string) ($r['handle_remark'] ?? ''),
                ]; ?>
                <tr>
                    <td class="mono"><?= View::e($r['invoice_no']) ?></td>
                    <td><?= View::e($r['title']) ?><?= $r['tax_no'] ? '<br><small class="mono">' . View::e((string) $r['tax_no']) . '</small>' : '' ?></td>
                    <td><?= (int) $r['type'] === 2 ? '专用发票' : '普通发票' ?></td>
                    <td class="nowrap">¥<?= number_format((float) $r['amount'], 2) ?></td>
                    <td><span class="badge badge--<?= Status::invoiceTone((int) $r['status']) ?>"><?= View::e(Status::invoice((int) $r['status'])) ?></span></td>
                    <td><?= View::e((string) ($r['handle_remark'] ?? '—')) ?></td>
                    <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
                    <td class="nowrap">
                        <button class="btn btn--ghost btn--sm" type="button"
                                onclick='openInvoiceDetail(<?= View::e(json_encode($ivItem, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP)) ?>)'>详情</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="pager">
            <?php if ($page > 1): ?><a href="javascript:ivPage(<?= $page - 1 ?>)">‹ 上一页</a><?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:ivPage(<?= $i ?>)"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a href="javascript:ivPage(<?= $page + 1 ?>)">下一页 ›</a><?php endif; ?>
            <span class="is-disabled">共 <?= (int) $total ?> 条</span>
        </nav>
    <?php endif; ?>
</section>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-invoice">
    <div class="adm-dialog__head">开票申请 · <span class="mono" id="ivd-no"></span></div>
    <div class="adm-dialog__body">
        <div class="form-row">
            <div class="form-field"><label class="form-label">状态</label>
                <div><span class="badge" id="ivd-status"></span></div></div>
            <div class="form-field"><label class="form-label">开票金额</label>
                <div class="mono" id="ivd-amount"></div></div>
        </div>
        <div class="form-field"><label class="form-label">发票抬头</label>
            <div id="ivd-title"></div></div>
        <div class="form-field"><label class="form-label">纳税人识别号</label>
            <div class="mono" id="ivd-tax"></div></div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">发票类型</label>
                <div id="ivd-type"></div></div>
            <div class="form-field"><label class="form-label">接收邮箱</label>
                <div id="ivd-email"></div></div>
        </div>
        <div class="form-field"><label class="form-label">备注</label>
            <div id="ivd-remark"></div></div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">申请时间</label>
                <div id="ivd-created"></div></div>
            <div class="form-field"><label class="form-label">处理时间</label>
                <div id="ivd-handled"></div></div>
        </div>
        <div class="form-field"><label class="form-label">处理意见</label>
            <div id="ivd-handle-remark"></div></div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" type="button" onclick="admCloseDialog(this)">关闭</button>
    </div>
</dialog>

<script>
/** 发票详情：列表一次取回全部字段，直接展示，无需再请求接口 */
function openInvoiceDetail(d) {
    document.getElementById('ivd-no').textContent = d.no;
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
