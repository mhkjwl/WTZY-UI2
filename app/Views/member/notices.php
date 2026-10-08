<?php declare(strict_types=1);
/**
 * @file app/Views/member/notices.php
 * @description 会员中心网站公告页：类型徽标 + 标题 + 日期 + 内容摘要，点击进入详情
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

use App\Core\View;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 */
$important = 0;
foreach ($rows as $r) {
    if ((int) ($r['notice_type'] ?? 1) === 2) {
        $important++;
    }
}
?>
<section class="astat-grid">
    <div class="astat astat--violet"><div class="astat__icon">📢</div>
        <div><div class="astat__value"><?= (int) $total ?></div><div class="astat__label">公告总数</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">❗</div>
        <div><div class="astat__value"><?= (int) $important ?></div><div class="astat__label">本页重要公告</div></div></div>
    <div class="astat astat--blue"><div class="astat__icon">📄</div>
        <div><div class="astat__value"><?= (int) $pages ?></div><div class="astat__label">总页数</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--violet">公告列表</span><span class="rule"></span>
        <span class="form-hint">点击公告查看详细内容。</span>
    </h3>
    <?= View::partial('member/notices_content', get_defined_vars()) ?>
</section>
