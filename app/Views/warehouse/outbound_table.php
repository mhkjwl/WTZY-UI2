<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/outbound_table.php
 * @description 待出库运单列表局部渲染
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\UserModel;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 * @var array  $perms
 */
$qs = static fn(int $p): string => '/warehouse/outbound?' . http_build_query(array_filter(['kw' => $keyword, 'page' => $p]));
?>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>运单号</th><th>用户</th><th>包裹数</th><th>计费重</th><th>应付</th><th>目的国</th><th>渠道</th><th>状态</th><th class="nowrap">支付时间</th><th class="nowrap">操作</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="10"><div class="empty-state">暂无待出库运单。用户支付运费后会出现在这里。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td class="mono"><?= View::e($r['waybill_no']) ?></td>
            <td><?= View::e((string) $r['user_no']) ?> <small><?= View::e(UserModel::displayName($r)) ?></small></td>
            <td><?= View::badge((int) $r['package_count'] . ' 件', 'cyan') ?></td>
            <td class="nowrap"><?= $r['billable_weight'] !== null ? number_format((float) $r['billable_weight'], 3) . ' kg' : '—' ?></td>
            <td class="nowrap"><b class="v-money">¥<?= number_format((float) $r['total_amount'], 2) ?></b></td>
            <td class="mono"><?= View::e((string) $r['dest_country']) ?></td>
            <td><?= View::badge((string) ($r['channel_name'] ?? '') !== '' ? (string) $r['channel_name'] : '未指定', (string) ($r['channel_name'] ?? '') !== '' ? 'indigo' : 'gray') ?></td>
            <td><?= View::badge(Status::waybill((int) $r['status']), Status::waybillTone((int) $r['status'])) ?></td>
            <td class="nowrap"><?= View::e($r['paid_at'] ? substr((string) $r['paid_at'], 0, 16) : '—') ?></td>
            <td class="nowrap">
                <a class="btn btn--ghost btn--sm" href="/warehouse/packing/<?= (int) $r['id'] ?>">详情</a>
                <?php if (!empty($perms['wh.outbound']) && (int) $r['status'] !== 5): ?>
                    <button class="btn btn--primary btn--sm"
                            onclick='openOutbound(<?= (int) $r['id'] ?>, <?= json_encode((string) $r['waybill_no'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode((string) ($r['channel_name'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>出库</button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($pages > 1): ?>
    <nav class="pager">
        <?php if ($page > 1): ?><a href="javascript:admTable('<?= View::e($qs($page - 1)) ?>')">‹ 上一页</a><?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:admTable('<?= View::e($qs($i)) ?>')"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a href="javascript:admTable('<?= View::e($qs($page + 1)) ?>')">下一页 ›</a><?php endif; ?>
        <span class="is-disabled">共 <?= (int) $total ?> 条</span>
    </nav>
<?php endif; ?>
