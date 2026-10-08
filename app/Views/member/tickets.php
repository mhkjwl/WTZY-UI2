<?php declare(strict_types=1);
/**
 * @file app/Views/member/tickets.php
 * @description 会员中心：客服工单列表
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 * @var int   $status
 * @var array $counts
 * @var array $categories
 * @var array $statusMap
 */
$initialUrl = '/user/tickets?' . http_build_query(array_filter(['status' => $status, 'page' => $page], static fn($v): bool => $v !== '' && $v !== -1));
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">🎧</div>
        <div><div class="astat__value"><?= array_sum(array_map('intval', $counts)) ?></div><div class="astat__label">全部工单</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) ($counts[0] ?? 0) ?></div><div class="astat__label">待受理</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">💬</div>
        <div><div class="astat__value"><?= (int) ($counts[1] ?? 0) + (int) ($counts[2] ?? 0) ?></div><div class="astat__label">处理中 / 已回复</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">✅</div>
        <div><div class="astat__value"><?= (int) ($counts[3] ?? 0) ?></div><div class="astat__label">已关闭</div></div></div>
</section>

<section class="panel">
    <div class="adm-toolbar">
        <select class="form-input" id="tk-status">
            <option value="-1">全部状态</option>
            <?php foreach ($statusMap as $code => $label): ?>
                <option value="<?= $code ?>" <?= $status === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--primary" onclick="admSearch('/user/tickets', {status: val('tk-status')})">筛选</button>
        <a class="btn btn--primary" style="margin-left:auto" href="/user/tickets/create">+ 提交工单</a>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('member/tickets_table', get_defined_vars()) ?>
    </div>
</section>
