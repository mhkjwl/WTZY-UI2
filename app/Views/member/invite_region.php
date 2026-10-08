<?php declare(strict_types=1);
/**
 * @file app/Views/member/invite_region.php
 * @description 会员中心：邀请记录列表区域（翻页时局部替换，规范 §2.5）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;
use App\Models\UserModel;

/**
 * @var array $invitees
 * @var int   $inviteeTotal
 * @var int   $page
 * @var int   $pages
 */
?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">邀请记录</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>用户编号</th><th>用户名</th><th class="nowrap">注册时间</th></tr></thead>
            <tbody>
            <?php if ($invitees === []): ?>
                <tr><td colspan="3"><div class="empty-state">还没有邀请记录。把邀请码分享给好友，注册成功后即会出现在这里。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($invitees as $u): ?>
                <tr>
                    <td class="mono"><?= View::e($u['user_no']) ?></td>
                    <td><?= View::e(UserModel::displayName($u)) ?></td>
                    <td class="nowrap"><?= View::e(substr((string) $u['created_at'], 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="pager">
            <?php if ($page > 1): ?><a href="javascript:ivtPage(<?= $page - 1 ?>)">‹ 上一页</a><?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:ivtPage(<?= $i ?>)"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a href="javascript:ivtPage(<?= $page + 1 ?>)">下一页 ›</a><?php endif; ?>
            <span class="is-disabled">共 <?= (int) $inviteeTotal ?> 人</span>
        </nav>
    <?php endif; ?>
</section>
