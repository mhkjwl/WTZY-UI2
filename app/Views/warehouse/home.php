<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/home.php
 * @description 仓库作业台：今日作业量 + 待办统计 + 我的任务 + 模块入口
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $today   今日作业量
 * @var array $stats   待办统计卡
 * @var int   $abnormalCount
 * @var array $myTasks 指派给我的未完成任务
 * @var array $taskCounts
 * @var array $taskTypes
 * @var array $taskStatus
 * @var array $modules
 */
$taskTargetUrl = static fn(array $t): string => (int) $t['target_type'] === 2
    ? ((int) $t['task_type'] === 4 ? '/warehouse/outbound' : '/warehouse/packing')
    : ((int) $t['task_type'] === 5 ? '/warehouse/abnormal' : '/warehouse/storage');
?>
<section class="astat-grid">
    <?php foreach ($stats as $card): ?>
        <a class="astat astat--<?= View::e($card['tone']) ?>" href="<?= View::e($card['href']) ?>" style="text-decoration:none">
            <div class="astat__icon"><?= $card['icon'] ?></div>
            <div>
                <div class="astat__value"><?= (int) $card['value'] ?></div>
                <div class="astat__label"><?= View::e($card['label']) ?></div>
            </div>
        </a>
    <?php endforeach; ?>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--cyan">今日作业量</span><span class="rule"></span>
    </h3>
    <div class="adm-toolbar" style="gap:24px;flex-wrap:wrap">
        <span>📥 今日入库 <b class="v-money" style="font-size:20px"><?= (int) $today['inbound'] ?></b> 件</span>
        <span>📦 今日打包 <b style="font-size:20px;color:#b45309"><?= (int) $today['packed'] ?></b> 单</span>
        <span>🚚 今日出库 <b style="font-size:20px;color:#047857"><?= (int) $today['shipped'] ?></b> 单</span>
        <?php if ($abnormalCount > 0): ?>
            <a class="badge badge--red" href="/warehouse/abnormal">⚠️ 异常待处理 <?= (int) $abnormalCount ?> 件</a>
        <?php else: ?>
            <span class="badge badge--green">✓ 无待处理异常</span>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--violet">我的作业任务</span><span class="rule"></span>
        <span class="badge badge--gray">未完成 <?= count($myTasks) ?> 项</span>
    </h3>
    <?php if ($myTasks === []): ?>
        <div class="empty-state">当前没有指派给你的作业任务。主管可在「任务分配」中指派入库、打包、出库等作业。</div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th>任务号</th><th>类型</th><th>目标单号</th><th>优先级</th><th>状态</th><th>指派说明</th><th class="nowrap">操作</th></tr></thead>
                <tbody>
                <?php foreach ($myTasks as $t): ?>
                    <tr>
                        <td class="mono"><?= View::e($t['task_no']) ?></td>
                        <td><?= View::badge($taskTypes[(int) $t['task_type']] ?? '作业', 'indigo') ?></td>
                        <td class="mono"><?= View::e($t['target_no']) ?></td>
                        <td><?= (int) $t['priority'] === 1 ? View::badge('加急', 'red') : View::badge('普通', 'gray') ?></td>
                        <td><?= View::badge($taskStatus[(int) $t['status']] ?? '—', (int) $t['status'] === 1 ? 'blue' : 'orange') ?></td>
                        <td><?= View::e((string) ($t['remark'] ?? '') ?: '—') ?></td>
                        <td class="nowrap"><a class="btn btn--primary btn--sm" href="<?= View::e($taskTargetUrl($t)) ?>">去作业</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">作业模块</span><span class="rule"></span></h3>
    <?php if ($modules === []): ?>
        <div class="empty-state">当前角色未分配任何仓库作业权限，请联系仓库主管在管理后台「系统 → 角色权限」中调整。</div>
    <?php else: ?>
        <section class="tile-grid">
            <?php foreach ($modules as $module): ?>
                <a class="tile tc-<?= View::e($module['color']) ?>" href="<?= View::e($module['href']) ?>">
                    <div class="tile__icon"><?= $module['icon'] ?></div>
                    <div class="tile__title"><?= View::e($module['name']) ?></div>
                    <div class="tile__desc"><?= View::e($module['desc']) ?></div>
                    <div class="tile__foot"><span class="badge badge--green">已上线</span><span class="tile__arrow">→</span></div>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</section>
