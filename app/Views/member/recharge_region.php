<?php declare(strict_types=1);
/**
 * @file app/Views/member/recharge_region.php
 * @description 会员中心：充值页可刷新区域的组合片段（统计卡 + 充值记录，供翻页局部替换）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 *
 * 统计卡位于表单上方、充值记录位于表单下方，两者中间隔着不可替换的表单，
 * 故组合片段同时返回两块内容，由前端分别注入各自的挂载点，保持页面布局不变。
 */

use App\Core\View;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 * @var array $account
 * @var array $config
 */
?>
<div id="rc-stats"><?= View::partial('member/recharge_stats', get_defined_vars()) ?></div>
<div id="rc-list"><?= View::partial('member/recharge_list', get_defined_vars()) ?></div>
