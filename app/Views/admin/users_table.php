<?php declare(strict_types=1);
/**
 * @file app/Views/admin/users_table.php
 * @description 用户列表局部渲染（检索/分页/操作后仅替换本区域）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\UserModel;

/**
 * @var array $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var string $keyword
 * @var int $status
 * @var string $base
 * @var array $perms
 * @var array $memberLevels
 */
$qs = static fn(int $p): string => '/admin/users?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $p],
    static fn($v): bool => $v !== '' && $v !== -1
));
// 等级名称映射（member_levels 行数组 → [level => name]）
$levelNames = [];
foreach ($memberLevels as $ml) {
    $levelNames[(int) $ml['level']] = (string) $ml['name'];
}
?>
<div class="table-scroll">
<table class="table">
    <thead>
    <tr>
        <th>用户</th><th>识别码</th><th>联系方式</th><th>等级</th><th>余额</th>
        <th>状态</th><th>风控</th><th>实名状态</th><th>姓名</th><th>证件</th><th>认证时间</th><th>注册时间</th><th class="nowrap">操作</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="13"><div class="empty-state">暂无匹配用户，试试调整关键词或状态筛选。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php $kyc = (int) ($r['kyc_status'] ?? 0); ?>
        <?php $uStatus = (int) $r['status']; ?>
        <?php $blReason = trim((string) ($r['blacklist_reason'] ?? '')); ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td><b><?= View::e($r['user_no']) ?></b><br><small><?= View::e($r['username'] ?: ($r['nickname'] ?: '—')) ?></small></td>
            <td class="mono"><?= View::e($r['user_code']) ?></td>
            <td class="nowrap"><?= View::e($r['phone'] ?: $r['email'] ?: '—') ?></td>
            <td><span class="badge badge--purple"><?= View::e($levelNames[(int) $r['member_level']] ?? 'Lv' . (int) $r['member_level']) ?></span></td>
            <td class="nowrap">¥<?= number_format((float) $r['balance'], 2) ?></td>
            <td><span class="badge badge--<?= Status::userTone((int) $r['status']) ?>"><?= View::e(Status::user((int) $r['status'])) ?></span></td>
            <td><span class="badge badge--<?= Status::riskTone((int) $r['risk_level']) ?>"><?= View::e(Status::risk((int) $r['risk_level'])) ?></span></td>
            <td><span class="badge badge--<?= Status::kycTone($kyc) ?>"><?= View::e(Status::KYC[$kyc] ?? '未认证') ?></span></td>
            <td><?= in_array($kyc, [1, 2], true) ? View::e((string) ($r['real_name'] ?? '—')) : '—' ?></td>
            <td class="nowrap">
                <?php if ((string) ($r['id_card_no'] ?? '') !== '' && $kyc !== 3): ?>
                    <small><?= View::e([1 => '身份证', 2 => '护照'][(int) ($r['id_type'] ?? 1)] ?? '身份证') ?></small><br>
                    <span class="mono"><small><?= View::e(UserModel::maskIdNo((string) $r['id_card_no'])) ?></small></span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <td class="nowrap"><?= $kyc === 2 && !empty($r['kyc_at']) ? View::e(substr((string) $r['kyc_at'], 0, 10)) : '—' ?></td>
            <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 10)) ?></td>
            <td class="nowrap">
                <?php if (!empty($perms['user.edit'])): ?>
                    <button class="btn btn--ghost btn--sm" onclick="openUser(<?= (int) $r['id'] ?>)">编辑</button>
                <?php endif; ?>
                <?php if (!empty($perms['user.audit'])): ?>
                    <?php if ($uStatus === Status::USER_BLACKLIST): ?>
                        <?php /* 解除封禁是黑名单链路唯一的撤销动作，服务端同样校验超管身份，此处只做入口收敛 */ ?>
                        <?php if ($isSuper ?? false): ?>
                            <button class="btn btn--ghost btn--sm" onclick="admAction('/admin/users/audit', {id: <?= (int) $r['id'] ?>, action: 'unblacklist'}, '确定解除该用户的黑名单吗？解除后该账号可重新登录，其手机号、邮箱与证件号也可再次注册。', {title: '解除黑名单', confirmText: '确认解除', level: 'L2'})">解除黑名单</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($uStatus === Status::USER_DISABLED): ?>
                            <button class="btn btn--ghost btn--sm" onclick="admAction('/admin/users/audit', {id: <?= (int) $r['id'] ?>, action: 'enable'}, '确定恢复该用户为正常状态吗？')">恢复</button>
                        <?php else: ?>
                            <button class="btn btn--ghost btn--sm" onclick="admAction('/admin/users/audit', {id: <?= (int) $r['id'] ?>, action: 'disable'}, '确定禁用用户 <?= View::e($r['user_no']) ?> 吗？禁用仅停用这一个账号，随时可以恢复；如需禁止该用户的证件号被其他账号实名，请改用「拉黑」。')">禁用</button>
                        <?php endif; ?>
                        <button class="btn btn--ghost btn--sm" onclick="openBlacklist(<?= (int) $r['id'] ?>, '<?= View::e($r['user_no']) ?>')">拉黑</button>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($perms['user.risk.set'])): ?>
                    <button class="btn btn--ghost btn--sm" onclick="openRisk(<?= (int) $r['id'] ?>, <?= (int) $r['risk_level'] ?>, '<?= View::e($r['user_no']) ?>')">风控</button>
                <?php endif; ?>
                <?php if (!empty($perms['user.balance.adjust'])): ?>
                    <button class="btn btn--ghost btn--sm" onclick="openBalance(<?= (int) $r['id'] ?>, '<?= View::e($r['user_no']) ?>')">余额</button>
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
