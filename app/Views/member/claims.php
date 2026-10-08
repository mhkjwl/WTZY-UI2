<?php declare(strict_types=1);
/**
 * @file app/Views/member/claims.php
 * @description 会员中心：理赔申请列表
 * @author AI
 * @version 0.6.1
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
 * @var array $types
 * @var array $statusMap
 */
$initialUrl = '/user/claims?' . http_build_query(array_filter(['status' => $status, 'page' => $page], static fn($v): bool => $v !== '' && $v !== -1));
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">🛡️</div>
        <div><div class="astat__value"><?= array_sum(array_map('intval', $counts)) ?></div><div class="astat__label">全部理赔</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) ($counts[0] ?? 0) + (int) ($counts[1] ?? 0) ?></div><div class="astat__label">处理中</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">✅</div>
        <div><div class="astat__value"><?= (int) ($counts[2] ?? 0) + (int) ($counts[4] ?? 0) ?></div><div class="astat__label">已通过 / 已赔付</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">❌</div>
        <div><div class="astat__value"><?= (int) ($counts[3] ?? 0) ?></div><div class="astat__label">已驳回</div></div></div>
</section>

<section class="panel">
    <div class="adm-toolbar">
        <select class="form-input" id="cl-status">
            <option value="-1">全部状态</option>
            <?php foreach ($statusMap as $code => $label): ?>
                <option value="<?= $code ?>" <?= $status === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--primary" onclick="admSearch('/user/claims', {status: val('cl-status')})">筛选</button>
        <a class="btn btn--primary" style="margin-left:auto" href="/user/claims/create">+ 提交理赔</a>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('member/claims_table', get_defined_vars()) ?>
    </div>
</section>
