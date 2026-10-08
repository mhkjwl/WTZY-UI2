<?php declare(strict_types=1);
/**
 * @file app/Views/admin/tickets.php
 * @description 客服工单页：状态统计 + 检索 + 回复对话框
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var int $status
 * @var int $category
 * @var int $page
 * @var array $counts
 * @var array $perms
 * @var string $csrf
 */
$initialUrl = '/admin/tickets?' . http_build_query(array_filter(
    ['status' => $status, 'category' => $category, 'page' => $page],
    static fn($v): bool => $v !== -1 && $v !== 0
));
$categories = Status::TICKET_CATEGORY;
?>
<section class="astat-grid">
    <div class="astat astat--amber"><div class="astat__icon">📨</div>
        <div><div class="astat__value"><?= (int) ($counts[0] ?? 0) ?></div><div class="astat__label">待受理</div></div></div>
    <div class="astat astat--blue"><div class="astat__icon">🔧</div>
        <div><div class="astat__value"><?= (int) ($counts[1] ?? 0) ?></div><div class="astat__label">处理中</div></div></div>
    <div class="astat astat--purple"><div class="astat__icon">💬</div>
        <div><div class="astat__value"><?= (int) ($counts[2] ?? 0) ?></div><div class="astat__label">已回复待确认</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">✅</div>
        <div><div class="astat__value"><?= (int) ($counts[3] ?? 0) ?></div><div class="astat__label">已关闭</div></div></div>
</section>

<section class="panel">
    <div class="adm-toolbar">
        <select class="form-input" id="t-status">
            <option value="-1">全部状态</option>
            <?php foreach (Status::TICKET as $code => $label): ?>
                <option value="<?= $code ?>" <?= $status === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-input" id="t-category">
            <option value="0">全部类型</option>
            <?php foreach ($categories as $code => $label): ?>
                <option value="<?= $code ?>" <?= $category === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--primary" onclick="admSearch('<?= View::e($base) ?>', {status: val('t-status'), category: val('t-category')})">检索</button>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('admin/tickets_table', get_defined_vars()) ?>
    </div>
</section>
