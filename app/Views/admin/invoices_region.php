<?php declare(strict_types=1);
/**
 * @file app/Views/admin/invoices_region.php
 * @description 发票管理局部区：功能开关、统计、开票申请列表（筛选/翻页/处理后局部替换，规范 §2.5）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-16
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\InvoiceModel;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var int    $ivStatus
 * @var string $ivKeyword
 * @var int    $pendingCount
 * @var int    $issuedCount
 * @var float  $issuedAmount
 * @var bool   $enabled
 * @var array  $perms
 */
$canManage = !empty($perms['finance.manage']);
?>
<?php if ($canManage): ?>
<h3 class="section-head">
    <span class="badge badge--<?= $enabled ? 'green' : 'gray' ?>">开票功能<?= $enabled ? '已开启' : '已关闭' ?></span>
    <span class="rule"></span>
    <button class="btn btn--<?= $enabled ? 'ghost' : 'primary' ?> btn--sm" type="button" onclick="toggleInvoice()"><?= $enabled ? '关闭开票功能' : '开启开票功能' ?></button>
</h3>
<p class="form-hint"><?= $enabled
    ? '用户端可提交开票申请。'
    : '用户端已隐藏开票入口，且不再接受新的申请；已提交的申请仍可在此处理。' ?></p>
<?php endif; ?>

<section class="astat-grid">
    <div class="astat astat--amber"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) $pendingCount ?></div><div class="astat__label">待开票</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">✅</div>
        <div><div class="astat__value"><?= (int) $issuedCount ?></div><div class="astat__label">已开票</div></div></div>
    <div class="astat astat--blue"><div class="astat__icon">💴</div>
        <div><div class="astat__value">¥<?= number_format((float) $issuedAmount, 2) ?></div><div class="astat__label">已开票金额</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">📄</div>
        <div><div class="astat__value"><?= (int) $total ?></div><div class="astat__label">检索结果</div></div></div>
</section>

<h3 class="section-head"><span class="badge badge--blue">开票申请</span><span class="rule"></span>
    <select class="form-input" style="min-width:130px" id="iv-status" onchange="invoiceFilter()">
        <option value="-1">全部状态</option>
        <?php foreach (Status::INVOICE as $k => $v): ?>
            <option value="<?= $k ?>" <?= $ivStatus === $k ? 'selected' : '' ?>><?= View::e($v) ?></option>
        <?php endforeach; ?>
    </select>
    <input class="form-input" style="min-width:190px" id="iv-kw" value="<?= View::e($ivKeyword) ?>"
           placeholder="单号 / 抬头 / 用户编号" onkeydown="if(event.key==='Enter'){invoiceFilter();}">
    <button class="btn btn--ghost btn--sm" type="button" onclick="invoiceFilter()">检索</button>
</h3>
<div class="table-scroll">
<table class="table">
    <thead><tr>
        <th>申请单号</th><th>用户</th><th>抬头</th><th>类型</th><th>金额</th>
        <th>接收邮箱</th><th>状态</th><th>处理意见</th><th class="nowrap">申请时间</th><th class="nowrap">操作</th>
    </tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="10"><div class="empty-state">暂无开票申请。用户端提交申请后在此处理。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php $ivItem = [
            'no'             => (string) $r['invoice_no'],
            'user'           => trim((string) ($r['user_no'] ?? '') . ' ' . (string) ($r['nickname'] ?? '')),
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
            <td class="mono"><b><?= View::e($r['invoice_no']) ?></b></td>
            <td><?= View::e($r['user_no'] ?: '—') ?> <small><?= View::e($r['nickname'] ?: '') ?></small></td>
            <td><?= View::e($r['title']) ?><?= $r['tax_no'] ? '<br><small class="mono">' . View::e((string) $r['tax_no']) . '</small>' : '' ?></td>
            <td><?= (int) $r['type'] === 2 ? '专用发票' : '普通发票' ?></td>
            <td class="nowrap">¥<?= number_format((float) $r['amount'], 2) ?></td>
            <td><?= View::e($r['email'] ?: '—') ?></td>
            <td><span class="badge badge--<?= Status::invoiceTone((int) $r['status']) ?>"><?= View::e(Status::invoice((int) $r['status'])) ?></span></td>
            <td><?= View::e((string) ($r['handle_remark'] ?? '—')) ?></td>
            <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
            <td class="nowrap">
                <button class="btn btn--ghost btn--sm" type="button"
                        onclick='openInvoiceDetail(<?= View::e(json_encode($ivItem, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP)) ?>)'>详情</button>
                <?php if ((int) $r['status'] === InvoiceModel::PENDING && $canManage): ?>
                    <button class="btn btn--primary btn--sm"
                            onclick="admAction('/admin/finance/invoices/handle', {id: <?= (int) $r['id'] ?>, result: 'issue'}, '确认已为该用户开具 ¥<?= number_format((float) $r['amount'], 2) ?> 的发票吗？', {confirmText: '确认开票', title: '标记已开票'})">开票</button>
                    <button class="btn btn--ghost btn--sm"
                            onclick="openReject(<?= (int) $r['id'] ?>, <?= json_encode((string) $r['invoice_no'], JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>)">驳回</button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($pages > 1): ?>
    <nav class="pager">
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:invoicePage(<?= $i ?>)"><?= $i ?></a>
        <?php endfor; ?>
        <span class="is-disabled">共 <?= (int) $total ?> 条</span>
    </nav>
<?php endif; ?>

<?php if ($canManage): ?>
<dialog class="adm-dialog adm-dialog--sm" id="dlg-reject">
    <div class="adm-dialog__head">驳回开票申请 · <span class="mono" id="rj-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="rj-id">
        <div class="form-field">
            <label class="form-label">驳回原因</label>
            <input class="form-input" id="rj-remark" maxlength="500" placeholder="如：抬头信息有误，请核对后重新提交">
        </div>
        <p class="form-hint">驳回后该笔金额不再占用可开票额度，用户可重新提交申请。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" type="button" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--danger" type="button" onclick="saveReject()">确认驳回</button>
    </div>
</dialog>
<?php endif; ?>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-invoice">
    <div class="adm-dialog__head">开票申请 · <span class="mono" id="ivd-no"></span></div>
    <div class="adm-dialog__body">
        <div class="form-row">
            <div class="form-field"><label class="form-label">状态</label>
                <div><span class="badge" id="ivd-status"></span></div></div>
            <div class="form-field"><label class="form-label">开票金额</label>
                <div class="mono" id="ivd-amount"></div></div>
        </div>
        <div class="form-field"><label class="form-label">申请人</label>
            <div id="ivd-user"></div></div>
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
        <div class="form-field"><label class="form-label">用户备注</label>
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
