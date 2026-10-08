<?php declare(strict_types=1);
/**
 * @file app/Views/admin/reports.php
 * @description 数据报表页：核心指标 + 近 14 天趋势 + 包裹/工单/理赔统计（CSS 图表，零外部依赖）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $days
 * @var int $maxUsers
 * @var float $maxRecharge
 * @var int $maxOrders
 * @var int $totalUsers
 * @var int $totalWaybills
 * @var float $totalRevenue
 * @var int $totalPackages
 * @var array $packageBuckets
 * @var array $ticketBuckets
 * @var array $claimBuckets
 * @var float $claimPaidAmount
 * @var int $claimApplying
 */
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">👥</div>
        <div><div class="astat__value"><?= (int) $totalUsers ?></div><div class="astat__label">注册用户总数</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">💵</div>
        <div><div class="astat__value">¥<?= number_format($totalRevenue, 2) ?></div><div class="astat__label">累计充值收入</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">🧾</div>
        <div><div class="astat__value"><?= (int) $totalWaybills ?></div><div class="astat__label">运单总数</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">📦</div>
        <div><div class="astat__value"><?= (int) $totalPackages ?></div><div class="astat__label">在库包裹</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">近 14 天用户注册</span><span class="rule"></span></h3>
    <div class="bars">
        <?php foreach ($days as $d): ?>
            <div class="bar-col" title="<?= View::e($d['date']) ?>：<?= (int) $d['users'] ?> 人">
                <span class="bar-col__num"><?= (int) $d['users'] ?: '' ?></span>
                <div class="bar-col__bar" style="height:<?= (int) round($d['users'] / $maxUsers * 100) ?>%"></div>
                <span class="bar-col__label"><?= View::e($d['date']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--green">近 14 天充值金额</span><span class="rule"></span></h3>
    <div class="bars">
        <?php foreach ($days as $d): ?>
            <div class="bar-col" title="<?= View::e($d['date']) ?>：¥<?= number_format((float) $d['recharge'], 2) ?>">
                <span class="bar-col__num"><?= $d['recharge'] > 0 ? number_format((float) $d['recharge'], 0) : '' ?></span>
                <div class="bar-col__bar" style="height:<?= (int) round($d['recharge'] / $maxRecharge * 100) ?>%;background:linear-gradient(180deg,#34d399,#059669)"></div>
                <span class="bar-col__label"><?= View::e($d['date']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--violet">近 14 天新增运单</span><span class="rule"></span></h3>
    <div class="bars">
        <?php foreach ($days as $d): ?>
            <div class="bar-col" title="<?= View::e($d['date']) ?>：<?= (int) $d['orders'] ?> 单">
                <span class="bar-col__num"><?= (int) $d['orders'] ?: '' ?></span>
                <div class="bar-col__bar" style="height:<?= (int) round($d['orders'] / $maxOrders * 100) ?>%;background:linear-gradient(180deg,#a78bfa,#7c3aed)"></div>
                <span class="bar-col__label"><?= View::e($d['date']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php $pkgMax = max(1, (int) max($packageBuckets['inWarehouse'], $packageBuckets['transit'], $packageBuckets['signed'], $packageBuckets['abnormal'])); ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--amber">包裹流转统计</span><span class="rule"></span>
        <span class="form-hint">全部包裹 <?= (int) $packageBuckets['total'] ?> 件</span>
    </h3>
    <div class="report-rows">
        <?php foreach ([
            ['在仓（到仓/待认领/已认领）', $packageBuckets['inWarehouse'], 'linear-gradient(90deg,#fbbf24,#d97706)'],
            ['运输中（出库/运输/清关/派送）', $packageBuckets['transit'], 'linear-gradient(90deg,#3b82f6,#2563eb)'],
            ['已签收', $packageBuckets['signed'], 'linear-gradient(90deg,#34d399,#059669)'],
            ['异常/退回/销毁', $packageBuckets['abnormal'], 'linear-gradient(90deg,#f87171,#dc2626)'],
        ] as [$label, $count, $color]): ?>
            <div class="report-row">
                <span class="report-row__label"><?= View::e($label) ?></span>
                <div class="report-row__track"><div class="report-row__bar" style="width:<?= (int) round($count / $pkgMax * 100) ?>%;background:<?= $color ?>"></div></div>
                <b class="report-row__num"><?= (int) $count ?></b>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php $tkMax = max(1, (int) max($ticketBuckets['pending'], $ticketBuckets['process'], $ticketBuckets['replied'], $ticketBuckets['closed'])); ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--cyan">工单统计</span><span class="rule"></span>
        <span class="form-hint">全部工单 <?= (int) $ticketBuckets['total'] ?> 单</span>
    </h3>
    <div class="report-rows">
        <?php foreach ([
            ['待受理', $ticketBuckets['pending'], 'linear-gradient(90deg,#fbbf24,#d97706)'],
            ['处理中', $ticketBuckets['process'], 'linear-gradient(90deg,#22d3ee,#0891b2)'],
            ['已回复（待用户）', $ticketBuckets['replied'], 'linear-gradient(90deg,#818cf8,#4f46e5)'],
            ['已关闭', $ticketBuckets['closed'], 'linear-gradient(90deg,#94a3b8,#64748b)'],
        ] as [$label, $count, $color]): ?>
            <div class="report-row">
                <span class="report-row__label"><?= View::e($label) ?></span>
                <div class="report-row__track"><div class="report-row__bar" style="width:<?= (int) round($count / $tkMax * 100) ?>%;background:<?= $color ?>"></div></div>
                <b class="report-row__num"><?= (int) $count ?></b>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="astat-grid" style="margin-top:0">
    <div class="astat astat--red"><div class="astat__icon">🛡️</div>
        <div><div class="astat__value"><?= (int) $claimBuckets['total'] ?></div><div class="astat__label">理赔申请总数</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) $claimApplying ?></div><div class="astat__label">待受理/处理中</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">💸</div>
        <div><div class="astat__value"><?= (int) $claimBuckets['paid'] ?></div><div class="astat__label">已赔付件数</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">💰</div>
        <div><div class="astat__value">¥<?= number_format($claimPaidAmount, 2) ?></div><div class="astat__label">累计赔付金额</div></div></div>
</section>

<?php $clMax = max(1, (int) max($claimBuckets['pending'], $claimBuckets['process'], $claimBuckets['reviewed'], $claimBuckets['paid'], $claimBuckets['rejected'])); ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--red">理赔状态统计</span><span class="rule"></span></h3>
    <div class="report-rows">
        <?php foreach ([
            ['待受理', $claimBuckets['pending'], 'linear-gradient(90deg,#fb923c,#ea580c)'],
            ['处理中', $claimBuckets['process'], 'linear-gradient(90deg,#fbbf24,#d97706)'],
            ['审核中（初审/财务/终审通过）', $claimBuckets['reviewed'], 'linear-gradient(90deg,#c084fc,#9333ea)'],
            ['已赔付', $claimBuckets['paid'], 'linear-gradient(90deg,#34d399,#059669)'],
            ['已驳回', $claimBuckets['rejected'], 'linear-gradient(90deg,#94a3b8,#64748b)'],
        ] as [$label, $count, $color]): ?>
            <div class="report-row">
                <span class="report-row__label"><?= View::e($label) ?></span>
                <div class="report-row__track"><div class="report-row__bar" style="width:<?= (int) round($count / $clMax * 100) ?>%;background:<?= $color ?>"></div></div>
                <b class="report-row__num"><?= (int) $count ?></b>
            </div>
        <?php endforeach; ?>
    </div>
</section>
