<?php declare(strict_types=1);
/**
 * @file app/Views/admin/channels_table.php
 * @description 渠道列表局部渲染
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array $rows
 * @var int $page
 * @var int $pages
 * @var string $keyword
 * @var string $base
 * @var array $perms
 */
$qs = static fn(int $p): string => '/admin/channels?' . http_build_query(array_filter(['kw' => $keyword, 'page' => $p], static fn($v): bool => $v !== ''));
$taxModes = [1 => '到付', 2 => '预付', 3 => '双清包税'];
?>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>渠道代码</th><th>名称</th><th>承运商</th><th>货物</th><th>体积重系数</th><th>时效</th><th>关税</th><th>分区/价格</th><th>状态</th><th class="nowrap">操作</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="10"><div class="empty-state">暂无渠道。请先创建渠道并配置分区与价格。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="mono"><b><?= View::e($r['channel_code']) ?></b></td>
            <td><?= View::e($r['name']) ?></td>
            <td><?= View::e($r['carrier']) ?></td>
            <td><span class="badge badge--<?= (int) $r['goods_type'] === 1 ? 'green' : 'orange' ?>"><?= (int) $r['goods_type'] === 1 ? '普货' : '敏感货' ?></span></td>
            <td>÷<?= (int) $r['volume_divisor'] ?></td>
            <td><?= View::e($r['aging_desc'] ?: '—') ?></td>
            <td><?= View::e($taxModes[(int) $r['tax_mode']] ?? '—') ?></td>
            <td><?= (int) $r['zone_count'] ?> 区 / <?= (int) $r['price_count'] ?> 价</td>
            <td><span class="badge badge--<?= (int) $r['status'] === 1 ? 'green' : 'gray' ?>"><?= (int) $r['status'] === 1 ? '启用' : '停用' ?></span></td>
            <td class="nowrap">
                <a class="btn btn--ghost btn--sm" href="/admin/channels/<?= (int) $r['id'] ?>">详情</a>
                <?php if (!empty($perms['channel.manage'])): ?>
                    <button class="btn btn--ghost btn--sm" onclick="admAction('/admin/channels/toggle', {id: <?= (int) $r['id'] ?>}, '确定切换渠道启停状态吗？')"><?= (int) $r['status'] === 1 ? '停用' : '启用' ?></button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($pages > 1): ?>
    <nav class="pager">
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:admTable('<?= View::e($qs($i)) ?>')"><?= $i ?></a>
        <?php endfor; ?>
        <span class="is-disabled">共 <?= count($rows) ?>+/页</span>
    </nav>
<?php endif; ?>
