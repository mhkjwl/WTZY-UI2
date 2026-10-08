<?php declare(strict_types=1);
/**
 * @file app/Views/member/points.php
 * @description 会员中心：积分明细与升级进度
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var int        $points
 * @var array      $rows
 * @var int        $total
 * @var int        $page
 * @var int        $pages
 * @var array|null $nextLevel
 * @var int        $pendingPoints
 * @var array      $user
 */
$earned = 0;
$spent = 0;
foreach ($rows as $r) {
    (int) $r['change_amount'] > 0 ? $earned += (int) $r['change_amount'] : $spent += abs((int) $r['change_amount']);
}
$initialUrl = '/user/member/points?page=' . $page;
?>
<section class="astat-grid">
    <div class="astat astat--violet"><div class="astat__icon">⭐</div>
        <div><div class="astat__value"><?= (int) $points ?></div><div class="astat__label">可用积分<?= (int) $points < 0 ? '（待还清）' : '' ?></div></div></div>
    <div class="astat astat--cyan"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) $pendingPoints ?></div><div class="astat__label">待激活（签收缓冲中）</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">⬆️</div>
        <div><div class="astat__value"><?= (int) $earned ?></div><div class="astat__label">本页获取</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⬇️</div>
        <div><div class="astat__value"><?= (int) $spent ?></div><div class="astat__label">本页消耗</div></div></div>
</section>

<?php if ((int) $points < 0): ?>
<section class="panel" style="border-left:4px solid #dc2626">
    <h3 class="section-head"><span class="badge badge--red">积分待还清</span><span class="rule"></span></h3>
    <p>当前积分为 <b style="color:#dc2626"><?= (int) $points ?></b>，系运单退款/退运/销毁后按比例扣回导致。
        欠分期间暂不可提交转运订单，待后续消费积分入账补足后自动恢复。</p>
</section>
<?php endif; ?>

<?php if ($nextLevel !== null): ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--purple">升级进度</span><span class="rule"></span></h3>
    <div class="meter tc-violet" style="margin-bottom:0">
        <div class="meter__head">
            <b>距离「<?= View::e($nextLevel['name']) ?>」还差 <?= (int) $nextLevel['need'] ?> 积分</b>
            <span><?= (int) $points ?> 分</span>
        </div>
        <div class="meter__bar">
            <?php
            $target = (int) $points + (int) $nextLevel['need'];
            $percent = $target > 0 ? round((int) $points * 100 / $target, 1) : 0;
            ?>
            <div class="meter__fill" style="width:<?= (float) $percent ?>%"></div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--gray">积分流水</span><span class="rule"></span></h3>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('member/points_table', get_defined_vars()) ?>
    </div>
</section>
