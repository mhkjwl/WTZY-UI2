<?php declare(strict_types=1);
/**
 * @file app/Views/admin/channel_detail.php
 * @description 渠道详情页：返回入口 + 局部内容区（分区/价格/附加费）
 *              渠道概要由内容区一并渲染，避免与局部刷新共用时重复出现标题
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $channel
 * @var array $zones
 * @var array $prices
 * @var array $surcharges
 * @var array $perms
 * @var string $csrf
 */
?>
<a class="btn btn--ghost btn--sm" href="/admin/channels">← 返回渠道列表</a>

<div class="table-wrap" data-table-wrap data-url="/admin/channels/<?= (int) $channel['id'] ?>">
    <?= View::partial('admin/channel_detail_content', get_defined_vars()) ?>
</div>
