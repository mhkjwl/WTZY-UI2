<?php declare(strict_types=1);
/**
 * @file app/Views/member/notice_detail.php
 * @description 会员中心公告详情页：类型徽标 + 标题 + 日期 + 全文
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

use App\Core\View;

/**
 * @var array $notice
 */
$nt = ((int) ($notice['notice_type'] ?? 1) === 2) ? ['重要', 'red', '❗'] : ['普通', 'gray', '📢'];
?>
<div class="adm-toolbar">
    <a class="btn btn--ghost btn--sm" href="/user/notices">← 返回网站公告</a>
</div>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--violet">网站公告</span><span class="rule"></span>
        <span class="badge badge--<?= $nt[1] ?>"><?= View::e($nt[0]) ?>公告</span>
    </h3>
    <div class="module-hero" style="margin-top:4px">
        <div class="module-hero__icon" style="background:linear-gradient(135deg,#6366f1,#8b5cf6)"><?= $nt[2] ?></div>
        <div>
            <div class="module-hero__title"><?= View::e((string) $notice['title']) ?></div>
            <div class="module-hero__desc">发布时间：<?= View::e(substr((string) $notice['created_at'], 0, 16)) ?>
                <?php if ($notice['updated_at'] !== $notice['created_at']): ?>
                     · 最近更新：<?= View::e(substr((string) $notice['updated_at'], 0, 16)) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div style="font-size:15px;line-height:2;color:var(--c-text);white-space:pre-wrap;margin-top:8px"><?= View::e((string) $notice['content']) ?></div>
</section>
