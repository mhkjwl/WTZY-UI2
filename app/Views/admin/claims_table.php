<?php declare(strict_types=1);
/**
 * @file app/Views/admin/claims_table.php
 * @description 理赔列表局部渲染（受理/初审/财务审核/终审/赔付按钮按状态+权限显隐）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\ClaimModel;

/**
 * @var array $rows
 * @var int $page
 * @var int $pages
 * @var int $status
 * @var string $base
 * @var array $perms
 */
$qs = static fn(int $p): string => '/admin/claims?' . http_build_query(array_filter(['status' => $status, 'page' => $p], static fn($v): bool => $v !== -1));
?>
<table class="table">
    <thead>
    <tr><th>理赔单号</th><th>用户</th><th>关联运单</th><th>类型</th><th>申请金额</th><th>核定金额</th>
        <th>状态</th><th>提交时间</th><th class="nowrap">操作</th></tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="9"><div class="empty-state">暂无理赔单。会员提交理赔申请后将在此受理与审核。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td class="mono"><a href="/admin/claims/<?= (int) $r['id'] ?>"><b><?= View::e($r['claim_no']) ?></b></a></td>
            <td><?= View::e($r['user_no'] ?: '—') ?> <small><?= View::e($r['nickname'] ?: '') ?></small></td>
            <td class="mono"><?= View::e($r['waybill_no'] ?: '—') ?></td>
            <td><span class="badge badge--orange"><?= View::e(ClaimModel::TYPES[(int) $r['claim_type']] ?? '其他') ?></span></td>
            <td class="nowrap">¥<?= number_format((float) $r['amount'], 2) ?></td>
            <td class="nowrap"><?= $r['approved_amount'] !== null ? '¥' . number_format((float) $r['approved_amount'], 2) : '—' ?></td>
            <td><span class="badge badge--<?= Status::claimTone((int) $r['status']) ?>"><?= View::e(ClaimModel::STATUS[$r['status']] ?? '未知') ?></span></td>
            <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 10)) ?></td>
            <td class="nowrap">
                <?php if (!empty($perms['claim.review']) && $r['status'] == 0): ?>
                    <button class="btn btn--ghost btn--sm" onclick="claimListAction('/admin/claims/accept', {id: <?= (int) $r['id'] ?>}, 0, '受理该理赔单并进入处理中吗？')">受理</button>
                <?php endif; ?>
                <?php if (!empty($perms['claim.review']) && $r['status'] == 1): ?>
                    <button class="btn btn--ghost btn--sm" onclick="openClaimReview(<?= (int) $r['id'] ?>, '<?= View::e($r['claim_no']) ?>', <?= (float) $r['amount'] ?>)">初审</button>
                <?php endif; ?>
                <?php if (!empty($perms['claim.finance_audit']) && $r['status'] == 2): ?>
                    <button class="btn btn--primary btn--sm" onclick="claimListAction('/admin/claims/finance-audit', {id: <?= (int) $r['id'] ?>}, 2, '确认财务审核通过吗？通过后将提交管理员终审。', {confirmText: '确认通过'})">财务审核</button>
                <?php endif; ?>
                <?php if (!empty($perms['claim.admin_audit']) && $r['status'] == 5): ?>
                    <button class="btn btn--primary btn--sm" onclick="claimListAction('/admin/claims/admin-audit', {id: <?= (int) $r['id'] ?>}, 5, '确认管理员终审通过吗？通过后可执行赔付。', {confirmText: '终审通过'})">终审</button>
                <?php endif; ?>
                <?php if (!empty($perms['claim.pay']) && $r['status'] == 6): ?>
                    <button class="btn btn--primary btn--sm" onclick="claimListAction('/admin/claims/pay', {id: <?= (int) $r['id'] ?>}, 6, '将按核定金额 ¥<?= number_format((float) $r['approved_amount'], 2) ?> 给用户余额入账。该操作不可撤销，且入账后无法重试。', {confirmText: '确认赔付', title: '执行赔付确认', level: 'L2'})">赔付</button>
                <?php endif; ?>
                <?php if (!empty($perms['claim.review']) && in_array($r['status'], [0, 1, 2], true)): ?>
                    <button class="btn btn--ghost btn--sm" onclick="openClaimReject(<?= (int) $r['id'] ?>, '<?= View::e($r['claim_no']) ?>')">驳回</button>
                <?php endif; ?>
                <a class="btn btn--ghost btn--sm" href="/admin/claims/<?= (int) $r['id'] ?>">详情</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php if ($pages > 1): ?>
    <nav class="pager">
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:admTable('<?= View::e($qs($i)) ?>')"><?= $i ?></a>
        <?php endfor; ?>
        <span class="is-disabled">共 <?= (int) $total ?> 条</span>
    </nav>
<?php endif; ?>
