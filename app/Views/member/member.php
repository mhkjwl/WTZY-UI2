<?php declare(strict_types=1);
/**
 * @file app/Views/member/member.php
 * @description 会员中心：会员等级权益与成长进度
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array      $levels
 * @var array|null $current
 * @var array|null $nextLevel
 * @var int        $points
 * @var array      $couponCounts
 * @var array      $user
 */
$currentLevel = (int) $user['member_level'];
?>
<section class="welcome-banner">
    <div>
        <h2>当前等级：<?= View::e((string) ($current['name'] ?? '普通用户')) ?></h2>
        <p>可用积分 <b><?= (int) $points ?></b> · 运费折扣 <b><?= number_format((float) ($current['discount'] ?? 1) * 10, 1) ?> 折</b>
            <?php if ((int) ($current['extra_storage_days'] ?? 0) > 0): ?>
                · 额外免仓储 <b><?= (int) $current['extra_storage_days'] ?></b> 天
            <?php endif; ?>
        </p>
    </div>
    <div class="welcome-banner__meta">
        <span class="badge--glass">🎫 可用券 <?= (int) ($couponCounts[0] ?? 0) ?></span>
        <span class="badge--glass">⭐ <?= (int) $points ?> 分</span>
    </div>
</section>

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
    <h3 class="section-head"><span class="badge badge--blue">等级权益对照</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead>
            <tr><th>等级</th><th>名称</th><th>所需积分</th><th>运费折扣</th><th>额外免仓储</th><th>权益说明</th><th>状态</th></tr>
            </thead>
            <tbody>
            <?php foreach ($levels as $lv): ?>
                <?php $level = (int) $lv['level']; ?>
                <tr<?= $level === $currentLevel ? ' style="background:#eef2ff"' : '' ?>>
                    <td>Lv<?= $level ?></td>
                    <td><b><?= View::e($lv['name']) ?></b></td>
                    <td class="nowrap"><?= number_format((int) $lv['min_points']) ?></td>
                    <td class="nowrap"><?= number_format((float) $lv['discount'] * 10, 1) ?> 折</td>
                    <td class="nowrap"><?= (int) $lv['extra_storage_days'] ?> 天</td>
                    <td><?= View::e((string) ($lv['remark'] ?? '')) ?></td>
                    <td>
                        <?php if ($level === $currentLevel): ?>
                            <span class="badge badge--green">当前等级</span>
                        <?php elseif ($level < $currentLevel): ?>
                            <span class="badge badge--gray">已达成</span>
                        <?php else: ?>
                            <span class="badge badge--orange">未达成</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--gray">积分获取方式</span><span class="rule"></span></h3>
    <p class="form-hint">下单、邀请好友与平台活动均可获得积分，下单时可用积分抵扣部分运费。</p>
    <div class="adm-toolbar" style="margin-bottom:0">
        <a class="btn btn--primary" href="/user/member/invite">去邀请好友</a>
        <a class="btn btn--ghost" href="/user/member/points">查看积分明细</a>
        <a class="btn btn--ghost" href="/user/member/coupons">我的优惠券</a>
    </div>
</section>
