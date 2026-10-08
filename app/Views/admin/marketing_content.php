<?php declare(strict_types=1);
/**
 * @file app/Views/admin/marketing_content.php
 * @description 全站公告局部内容
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */

use App\Core\View;

/**
 * @var array $notices
 * @var int $page
 * @var int $pages
 * @var string $base
 * @var array $perms
 */
$qs = static fn(int $p): string => '/admin/marketing?' . http_build_query(['page' => $p]);
$channels = [1 => '站内信', 3 => '邮箱'];
$noticeTypes = [1 => ['普通', 'gray'], 2 => ['重要', 'red']];
$sendStates = [0 => ['待发送', 'orange'], 1 => ['已发送', 'green'], 2 => ['发送失败', 'red']];
?>
<div class="table-scroll">
<table class="table">
    <thead><tr><th>类型</th><th>标题</th><th>内容摘要</th><th>渠道</th><th>发送状态</th><th>更新时间</th><th class="nowrap">操作</th></tr></thead>
    <tbody>
    <?php if ($notices === []): ?>
        <tr><td colspan="7"><div class="empty-state">暂无全站公告。发布后将在用户端「网站公告」页展示。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($notices as $n): ?>
        <?php
        $channel = (int) $n['msg_type'];
        // 站内信落地即可见，不展示发送状态列的意义不大，统一按已发送呈现
        $send = $sendStates[(int) $n['send_status']] ?? ['待发送', 'orange'];
        $nt = $noticeTypes[(int) ($n['notice_type'] ?? 1)] ?? $noticeTypes[1];
        ?>
        <tr>
            <td><span class="badge badge--<?= $nt[1] ?>"><?= View::e($nt[0]) ?></span></td>
            <td><b><?= View::e($n['title']) ?></b></td>
            <td><?= View::e(mb_strlen($n['content']) > 50 ? mb_substr($n['content'], 0, 50) . '…' : $n['content']) ?></td>
            <td><span class="badge badge--<?= $channel === 3 ? 'purple' : 'blue' ?>"><?= View::e($channels[$channel] ?? '站内信') ?></span></td>
            <td><span class="badge badge--<?= $channel === 3 ? $send[1] : 'green' ?>"><?= View::e($channel === 3 ? $send[0] : '已发送') ?></span></td>
            <td class="nowrap">
                <?= View::e($n['updated_at']) ?>
                <?php if ($n['updated_at'] !== $n['created_at']): ?>
                    <br><small>已编辑</small>
                <?php endif; ?>
            </td>
            <td class="nowrap">
                <?php if (!empty($perms['marketing.manage'])): ?>
                    <button class="btn btn--ghost btn--sm"
                            onclick="openNotice(<?= (int) $n['id'] ?>, <?= View::e(json_encode(['title' => $n['title'], 'content' => $n['content'], 'msg_type' => $channel, 'notice_type' => (int) ($n['notice_type'] ?? 1)], JSON_UNESCAPED_UNICODE)) ?>)">编辑</button>
                    <?php if ($channel === 3): ?>
                        <button class="btn btn--ghost btn--sm"
                                onclick="admAction('/admin/marketing/notice/resend', {id: <?= (int) $n['id'] ?>}, '确定向所有已绑定邮箱的正常用户重发该公告邮件吗？已收到邮件的用户会再收到一封。')">重发邮件</button>
                    <?php endif; ?>
                    <button class="btn btn--ghost btn--sm" onclick="admAction('/admin/marketing/notice/delete', {id: <?= (int) $n['id'] ?>}, '确定下架该公告吗？用户端将不再展示。')">下架</button>
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
        <span class="is-disabled">共 <?= count($notices) ?>+/页</span>
    </nav>
<?php endif; ?>
