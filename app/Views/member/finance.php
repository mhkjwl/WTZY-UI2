<?php declare(strict_types=1);
/**
 * @file app/Views/member/finance.php
 * @description 会员中心：余额概览与最近资金流水
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $account
 * @var array $flows
 * @var int   $pending
 */
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">💰</div>
        <div><div class="astat__value">¥<?= number_format((float) $account['balance'], 2) ?></div><div class="astat__label">可用余额</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">🔒</div>
        <div><div class="astat__value">¥<?= number_format((float) $account['frozen_amount'], 2) ?></div><div class="astat__label">冻结金额</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">⬆️</div>
        <div><div class="astat__value">¥<?= number_format((float) $account['total_recharge'], 2) ?></div><div class="astat__label">累计充值</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">⬇️</div>
        <div><div class="astat__value">¥<?= number_format((float) $account['total_consume'], 2) ?></div><div class="astat__label">累计消费</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">快捷操作</span><span class="rule"></span></h3>
    <div class="adm-toolbar" style="margin-bottom:0">
        <a class="btn btn--primary" href="/user/finance/recharge">立即充值</a>
        <a class="btn btn--ghost" href="/user/finance/flows">全部资金流水</a>
        <?php if (!empty($invoiceEnabled)): ?><a class="btn btn--ghost" href="/user/finance/invoices">发票管理</a><?php endif; ?>
        <?php if ($pending > 0): ?>
            <span class="badge badge--orange" style="margin-left:auto">有待支付充值单，请尽快完成付款</span>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--gray">最近流水</span><span class="rule"></span>
        <a class="btn btn--ghost" href="/user/finance/flows">查看全部</a>
    </h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th class="nowrap">时间</th><th>类型</th><th>方向</th><th>金额</th><th>变动后余额</th><th>说明</th></tr></thead>
            <tbody>
            <?php if ($flows === []): ?>
                <tr><td colspan="6"><div class="empty-state">暂无资金流水。充值与运费支付都会记录在这里。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($flows as $f): ?>
                <?php $income = (int) $f['direction'] === 1; ?>
                <tr>
                    <td class="nowrap"><?= View::e(substr((string) $f['created_at'], 0, 16)) ?></td>
                    <td><span class="badge badge--<?= $income ? 'green' : 'orange' ?>"><?= View::e(Status::FLOW_TYPE[(int) $f['flow_type']] ?? '其他') ?></span></td>
                    <td><?= View::badge(Status::FLOW_DIRECTION[(int) $f['direction']] ?? '—', (int) $f['direction'] === 1 ? 'green' : 'red') ?></td>
                    <td class="nowrap" style="color:<?= $income ? '#16a34a' : '#dc2626' ?>">
                        <?= $income ? '+' : '-' ?> ¥<?= number_format((float) $f['amount'], 2) ?>
                    </td>
                    <td class="nowrap">¥<?= number_format((float) $f['balance_after'], 2) ?></td>
                    <td><?= View::e((string) ($f['remark'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
