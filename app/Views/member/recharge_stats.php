<?php declare(strict_types=1);
/**
 * @file app/Views/member/recharge_stats.php
 * @description 会员中心：充值页统计卡区域（独立片段，供整页与局部刷新共用）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

/**
 * @var array $rows
 * @var int   $total
 * @var array $account
 * @var array $config
 */
$expireMinutes = (int) ($config['expire_minutes'] ?? 30);
$pending = 0;
foreach ($rows as $r) {
    if (in_array((int) $r['status'], [0, 5], true)) {
        $pending++;
    }
}
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">💰</div>
        <div><div class="astat__value">¥<?= number_format((float) $account['balance'], 2) ?></div><div class="astat__label">当前余额</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) $pending ?></div><div class="astat__label">本页待处理</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">📄</div>
        <div><div class="astat__value"><?= (int) $total ?></div><div class="astat__label">充值记录</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">⏱️</div>
        <div><div class="astat__value"><?= (int) $expireMinutes ?> 分钟</div><div class="astat__label">在线支付时效</div></div></div>
</section>
