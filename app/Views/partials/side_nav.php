<?php declare(strict_types=1);
/**
 * @file app/Views/partials/side_nav.php
 * @description 三端共用的侧边栏菜单渲染（光年 v5 菜单结构）：无分组项平铺为一级入口，
 *              同名分组聚合成「一级分组 + 二级子菜单」区块；当前页所在分组由服务端直出展开态，
 *              避免首屏闪动；分组的展开/收起动画由模板脚本接管，展开记忆见 assets/ui/shell.js
 * @author AI
 * @version 0.6.2
 * @date 2026-10-08
 */

use App\Core\View;

/**
 * @var array  $menu   菜单项（group 为空表示不属于任何分组，直接平铺）
 * @var string $active 当前高亮 key
 */
// 分组与平铺项使用模板自带的 Material Design Icons 线性图标；
// 菜单数据只表达端内语义，图标在此映射，避免把视觉选择写进控制器
$groupIcons = [
    '业务管理'   => 'mdi-briefcase-outline',
    '运营支撑'   => 'mdi-lifebuoy',
    '会员与营销' => 'mdi-crown-outline',
    '系统'       => 'mdi-cog-outline',
    '收件入库'   => 'mdi-inbox-arrow-down',
    '出库作业'   => 'mdi-truck-outline',
    '异常与调度' => 'mdi-alert-outline',
    '账户设置'   => 'mdi-account-cog-outline',
    '我的包裹'   => 'mdi-package-variant-closed',
    '转运与物流' => 'mdi-truck-fast-outline',
    '财务中心'   => 'mdi-wallet-outline',
    '客服与售后' => 'mdi-headset',
];
$flatIcons = [
    'dashboard' => 'mdi-home-city-outline',
];

// 按分组聚合：保持控制器定义顺序，同组连续项合并为一个区块
$blocks = [];
foreach (($menu ?? []) as $item) {
    $group = $item['group'] ?? null;
    $last = array_key_last($blocks);
    if ($last === null || $blocks[$last]['group'] !== $group) {
        $blocks[] = ['group' => $group, 'items' => []];
        $last = array_key_last($blocks);
    }
    $blocks[$last]['items'][] = $item;
}
?>
<ul class="nav-drawer">
<?php foreach ($blocks as $block): ?>
    <?php if ($block['group'] === null): ?>
        <?php foreach ($block['items'] as $item): ?>
        <li class="nav-item<?= ($active ?? '') === $item['key'] ? ' active' : '' ?>">
            <a href="<?= View::e($item['href']) ?>" data-key="<?= View::e($item['key']) ?>">
                <i class="mdi <?= View::e($flatIcons[$item['key']] ?? 'mdi-circle-outline') ?>"></i>
                <span><?= View::e($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?><span class="badge rounded-pill bg-danger nav-badge" data-badge-key="<?= View::e((string) $item['badge_key']) ?>"><?= (int) $item['badge'] > 99 ? '99+' : (int) $item['badge'] ?></span><?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    <?php else: ?>
        <?php
        // 当前页在组内则服务端直接展开：输出 open 后模板 CSS 即显示子菜单，不依赖脚本
        $hasActive = false;
        foreach ($block['items'] as $it) {
            if (($active ?? '') === $it['key']) {
                $hasActive = true;
                break;
            }
        }
        ?>
        <li class="nav-item nav-item-has-subnav<?= $hasActive ? ' open' : '' ?>" data-group="<?= View::e($block['group']) ?>">
            <a href="javascript:void(0)">
                <i class="mdi <?= View::e($groupIcons[$block['group']] ?? 'mdi-folder-outline') ?>"></i>
                <span><?= View::e($block['group']) ?></span>
                <span class="badge rounded-pill bg-danger nav-badge nav-badge--group" hidden></span>
            </a>
            <ul class="nav nav-subnav">
                <?php foreach ($block['items'] as $item): ?>
                <li class="<?= ($active ?? '') === $item['key'] ? 'active' : '' ?>">
                    <a href="<?= View::e($item['href']) ?>" data-key="<?= View::e($item['key']) ?>">
                        <span><?= View::e($item['label']) ?></span>
                        <?php if (!empty($item['badge'])): ?><span class="badge rounded-pill bg-danger nav-badge" data-badge-key="<?= View::e((string) $item['badge_key']) ?>"><?= (int) $item['badge'] > 99 ? '99+' : (int) $item['badge'] ?></span><?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </li>
    <?php endif; ?>
<?php endforeach; ?>
</ul>