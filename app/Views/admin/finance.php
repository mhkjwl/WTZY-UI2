<?php declare(strict_types=1);
/**
 * @file app/Views/admin/finance.php
 * @description 财务中心页：总览统计 + 流水/充值/汇率局部区
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array $overview
 * @var int $flowPage
 * @var int $flowType
 * @var int $reStatus
 * @var string $listUrl
 * @var string $csrf
 */
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">💰</div>
        <div><div class="astat__value">¥<?= number_format($overview['total_balance'], 2) ?></div><div class="astat__label">用户总余额</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">📥</div>
        <div><div class="astat__value">¥<?= number_format($overview['total_recharge'], 2) ?></div><div class="astat__label">累计充值</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">📤</div>
        <div><div class="astat__value">¥<?= number_format($overview['total_consume'], 2) ?></div><div class="astat__label">累计支出</div></div></div>
    <div class="astat astat--red"><div class="astat__icon">↩️</div>
        <div><div class="astat__value">¥<?= number_format($overview['total_refund'], 2) ?></div><div class="astat__label">累计退款</div></div></div>
</section>

<section class="panel">
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($listUrl) ?>">
        <?= View::partial('admin/finance_content', get_defined_vars()) ?>
    </div>
</section>
