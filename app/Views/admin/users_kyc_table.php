<?php declare(strict_types=1);
/**
 * @file app/Views/admin/users_kyc_table.php
 * @description 实名审核列表局部渲染（证件材料可查看）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\UserModel;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 * @var int    $status
 * @var array  $perms
 */
$qs = static fn(int $p): string => '/admin/users/kyc?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $p],
    static fn($v): bool => $v !== '' && $v !== -1
));
$statusMap = [0 => '未提交', 1 => '待审核', 2 => '已通过', 3 => '已驳回'];
$typeLabels = [1 => '身份证', 2 => '护照'];
?>
<div class="table-scroll">
<table class="table">
    <thead>
    <tr><th>用户</th><th>用户名</th><th>联系方式</th><th>真实姓名</th><th>证件类型</th><th>证件号码</th><th>认证材料</th><th>状态</th><th class="nowrap">提交时间</th><th class="nowrap">操作</th></tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="10"><div class="empty-state">暂无实名申请。用户提交后在「用户中心 → 实名认证」可查看进度。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php $kyc = (int) $r['kyc_status']; ?>
        <?php $front = (string) ($r['id_photo_front'] ?? ''); $back = (string) ($r['id_photo_back'] ?? ''); ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td><b><?= View::e($r['user_no']) ?></b><br><small><?= View::e((string) $r['nickname']) ?></small></td>
            <td class="mono"><?= View::e((string) ($r['username'] ?? '—')) ?></td>
            <td class="nowrap"><?= View::e($r['phone'] ?: ($r['email'] ?: '—')) ?></td>
            <td><?= View::e((string) ($r['real_name'] ?? '—')) ?></td>
            <td><?= View::e($typeLabels[(int) ($r['id_type'] ?? 1)] ?? '身份证') ?></td>
            <td class="mono"><?= View::e(UserModel::maskIdNo((string) ($r['id_card_no'] ?? ''))) ?></td>
            <td class="nowrap">
                <?php if ($front !== ''): ?>
                    <a href="javascript:void(0)" class="auth-link-mini"
                       onclick="App.previewImage(<?= json_encode((string) $front, JSON_UNESCAPED_SLASHES | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP) ?>)">查看照片</a>
                    <?php if ($back !== ''): ?><small>（正/反两张）</small><?php endif; ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <td>
                <span class="badge badge--<?= Status::kycTone($kyc) ?>"><?= View::e($statusMap[$kyc] ?? '未知') ?></span>
                <?php if ((int) $r['kyc_status'] === 3 && ($r['kyc_remark'] ?? '') !== ''): ?>
                    <br><small><?= View::e((string) $r['kyc_remark']) ?></small>
                <?php endif; ?>
            </td>
            <td class="nowrap"><?= View::e($r['kyc_at'] ? substr((string) $r['kyc_at'], 0, 16) : '—') ?></td>
            <td class="nowrap">
                <?php if (!empty($perms['user.kyc'])): ?>
                    <?php if ((int) $r['kyc_status'] === 1): ?>
                        <button class="btn btn--primary btn--sm"
                                onclick='openKyc(<?= json_encode(['id' => (int) $r['id'], 'user_no' => $r['user_no'], 'real_name' => $r['real_name'], 'id_type' => (int) ($r['id_type'] ?? 1), 'id_card_no' => $r['id_card_no'], 'id_photo_front' => $front, 'id_photo_back' => $back, 'kyc_at' => $r['kyc_at']], JSON_UNESCAPED_UNICODE) ?>)'>审核</button>
                    <?php else: ?>
                        <span class="msg-item__read">已处理</span>
                    <?php endif; ?>
                <?php endif; ?>
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
