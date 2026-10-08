<?php declare(strict_types=1);
/**
 * @file app/Views/admin/tickets_table.php
 * @description 工单列表局部渲染（含回复/关闭对话框触发）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\UserModel;

/**
 * @var array $rows
 * @var int $page
 * @var int $pages
 * @var int $status
 * @var int $category
 * @var array $pendingUser 工单ID => 最后一条回复是否来自用户
 * @var string $base
 * @var array $perms
 */
$categories = Status::TICKET_CATEGORY;
$pendingUser = $pendingUser ?? [];
$qs = static fn(int $p): string => '/admin/tickets?' . http_build_query(array_filter(
    ['status' => $status, 'category' => $category, 'page' => $p],
    static fn($v): bool => $v !== -1 && $v !== 0
));
?>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>工单号</th><th>用户</th><th>类型</th><th>标题</th><th>状态</th><th>受理客服</th><th>最后回复</th><th class="nowrap">操作</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="8"><div class="empty-state">暂无工单。用户端提交工单后在此受理与跟进。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $t): ?>
        <?php $awaiting = !empty($pendingUser[(int) $t['id']]) && (int) $t['status'] !== 3; ?>
        <tr data-id="<?= (int) $t['id'] ?>">
            <td class="mono"><a href="/admin/tickets/<?= (int) $t['id'] ?>"><b><?= View::e($t['ticket_no']) ?></b></a></td>
            <td><?= View::e($t['user_no'] ?: '—') ?> <small><?= View::e(UserModel::displayName($t)) ?></small></td>
            <td><span class="badge badge--purple"><?= View::e($categories[(int) $t['category']] ?? '其他') ?></span></td>
            <td><a href="/admin/tickets/<?= (int) $t['id'] ?>"><?= View::e($t['title']) ?></a></td>
            <td>
                <span class="badge badge--<?= Status::ticketTone((int) $t['status']) ?>"><?= View::e(Status::ticket((int) $t['status'])) ?></span>
                <?php if ($awaiting): ?><br><span class="badge badge--red">等待客服回复</span><?php endif; ?>
            </td>
            <td><?= View::e($t['assign_name'] ?: '—') ?></td>
            <td class="nowrap"><?= View::e($t['last_reply_at'] ?: '—') ?></td>
            <td class="nowrap">
                <a class="btn btn--ghost btn--sm" href="/admin/tickets/<?= (int) $t['id'] ?>">详情</a>
                <?php if (!empty($perms['ticket.handle']) && (int) $t['status'] !== 3): ?>
                    <?php if ((int) $t['status'] === 0): ?>
                        <button class="btn btn--ghost btn--sm" onclick="admAction('/admin/tickets/assign', {id: <?= (int) $t['id'] ?>}, '受理该工单并锁定为自己处理吗？')">受理</button>
                    <?php endif; ?>
                    <a class="btn btn--ghost btn--sm" href="/admin/tickets/<?= (int) $t['id'] ?>">回复</a>
                    <button class="btn btn--ghost btn--sm" onclick="admAction('/admin/tickets/close', {id: <?= (int) $t['id'] ?>}, '确定关闭该工单吗？关闭后用户不可继续回复。', {confirmText: '关闭'})">关闭</button>
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
