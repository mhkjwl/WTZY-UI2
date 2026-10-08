<?php declare(strict_types=1);
/**
 * @file app/Views/admin/warehouse_reports.php
 * @description 仓库报表：各仓在库量、今日出入库、待打包/待出库、异常件与库位占用
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array $rows   各仓库作业概况
 * @var array $status
 */
$sum = static function (string $key) use ($rows): int {
    return array_sum(array_map(static fn(array $r): int => (int) $r[$key], $rows));
};
$totalLoc = $sum('loc_total');
$usedLoc = $sum('loc_used');
?>
<section class="astat-grid">
    <div class="astat astat--emerald"><div class="astat__icon">🏗️</div>
        <div><div class="astat__value"><?= $sum('in_stock') ?></div><div class="astat__label">在库包裹（全部仓库）</div></div></div>
    <div class="astat astat--blue"><div class="astat__icon">📥</div>
        <div><div class="astat__value"><?= $sum('today_inbound') ?></div><div class="astat__label">今日入库</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">🚚</div>
        <div><div class="astat__value"><?= $sum('today_outbound') ?></div><div class="astat__label">今日出库</div></div></div>
    <div class="astat astat--red"><div class="astat__icon">⚠️</div>
        <div><div class="astat__value"><?= $sum('abnormal') ?></div><div class="astat__label">异常待处理</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--blue">各仓作业概况</span><span class="rule"></span>
        <span class="badge badge--gray">库位占用 <?= $totalLoc > 0 ? round($usedLoc * 100 / $totalLoc, 1) : 0 ?>%（<?= $usedLoc ?>/<?= $totalLoc ?>）</span>
    </h3>
    <?php if ($rows === []): ?>
        <div class="empty-state">暂无仓库。请先到「仓库管理」新建仓库并配置库位。</div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="table">
                <thead>
                <tr>
                    <th>仓库</th><th>状态</th><th>在库</th><th>无主件</th><th>待打包</th><th>待出库</th>
                    <th>今日入库</th><th>今日出库</th><th>异常待处理</th><th class="nowrap">库位占用</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <b><?= View::e((string) $r['name']) ?></b>
                            <br><small class="mono"><?= View::e((string) $r['code']) ?> · <?= View::e((string) $r['country']) ?></small>
                        </td>
                        <td><?= View::badge((int) $r['status'] === 1 ? '启用' : '停用', (int) $r['status'] === 1 ? 'green' : 'gray') ?></td>
                        <td><b class="v-count"><?= (int) $r['in_stock'] ?></b></td>
                        <td><?= (int) $r['unattributed'] > 0 ? View::badge((string) (int) $r['unattributed'] . ' 件', 'orange') : View::badge('0', 'gray') ?></td>
                        <td><?= (int) $r['pending_packing'] > 0 ? View::badge((string) (int) $r['pending_packing'] . ' 单', 'amber') : View::badge('0', 'gray') ?></td>
                        <td><?= (int) $r['pending_outbound'] > 0 ? View::badge((string) (int) $r['pending_outbound'] . ' 单', 'violet') : View::badge('0', 'gray') ?></td>
                        <td><?= View::badge((string) (int) $r['today_inbound'] . ' 件', 'blue') ?></td>
                        <td><?= View::badge((string) (int) $r['today_outbound'] . ' 单', 'emerald') ?></td>
                        <td><?= (int) $r['abnormal'] > 0 ? View::badge((string) (int) $r['abnormal'] . ' 件', 'red') : View::badge('0', 'gray') ?></td>
                        <td class="nowrap">
                            <b class="v-count"><?= View::e((string) $r['loc_rate']) ?>%</b>
                            <br><small class="mono"><?= (int) $r['loc_used'] ?>/<?= (int) $r['loc_total'] ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--violet">仓库端作业入口</span><span class="rule"></span></h3>
    <div class="adm-toolbar" style="margin-bottom:0">
        <a class="btn btn--primary" href="/warehouse" target="_blank" rel="noopener">打开仓库作业台</a>
    </div>
</section>
