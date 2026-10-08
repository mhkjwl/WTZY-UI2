<?php declare(strict_types=1);
/**
 * @file app/Views/member/forecast_table.php
 * @description 包裹预报列表局部渲染（检索/分页/操作后仅替换本区域）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 */
$qs = static fn(int $p): string => '/user/forecast?' . http_build_query(array_filter(['kw' => $keyword, 'page' => $p]));
?>
<div class="table-scroll">
<table class="table">
    <thead>
    <tr>
        <th>包裹编号</th><th>快递单号</th><th>商品</th><th>类别</th><th>申报价值</th>
        <th>状态</th><th class="nowrap">预计到仓</th><th class="nowrap">操作</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="8"><div class="empty-state">还没有预报记录。点击右上角「新增预报」登记第一个包裹。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php $editable = in_array((int) $r['status'], [0, 1], true); ?>
        <tr>
            <td class="mono"><a href="/user/packages/<?= (int) $r['id'] ?>"><?= View::e($r['package_no']) ?></a></td>
            <td class="mono"><?= View::e($r['tracking_no'] ?: '—') ?><br><small><?= View::e($r['express_company'] ?: '') ?></small></td>
            <td><?= View::e($r['item_name']) ?> ×<?= (int) $r['item_qty'] ?></td>
            <td><span class="badge badge--gray"><?= View::e(Status::PACKAGE_CATEGORY[(int) $r['category']] ?? '其他') ?></span></td>
            <td class="nowrap"><?= View::e((string) $r['currency']) ?> <?= number_format((float) $r['declared_value'], 2) ?></td>
            <td>
                <span class="badge badge--<?= Status::packageTone((int) $r['status']) ?>"><?= View::e(Status::package((int) $r['status'])) ?></span>
                <?php if ((int) $r['is_abnormal'] === 1): ?><span class="badge badge--red">异常</span><?php endif; ?>
            </td>
            <td class="nowrap"><?= View::e($r['expected_arrival'] ?: '—') ?></td>
            <td class="nowrap">
                <?php if ($editable): ?>
                    <button class="btn btn--ghost btn--sm"
                            onclick='openForecast(<?= json_encode([
                                'id' => (int) $r['id'], 'package_no' => $r['package_no'], 'tracking_no' => $r['tracking_no'],
                                'express_company' => $r['express_company'], 'item_name' => $r['item_name'], 'item_qty' => (int) $r['item_qty'],
                                'declared_value' => $r['declared_value'], 'currency' => $r['currency'], 'category' => (int) $r['category'],
                                'expected_arrival' => $r['expected_arrival'], 'remark' => $r['remark'],
                            ], JSON_UNESCAPED_UNICODE) ?>)'>修改</button>
                <?php endif; ?>
                <a class="btn btn--ghost btn--sm" href="/user/packages/<?= (int) $r['id'] ?>">详情</a>
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
