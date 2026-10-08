<?php declare(strict_types=1);
/**
 * @file app/Views/member/abnormal_region.php
 * @description 会员中心：异常件统计与记录表区域（翻页时局部替换，规范 §2.5）
 * @author AI
 * @version 0.6
 * @date 2026-09-15
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 */
$pending = 0;
foreach ($rows as $r) {
    if ((int) $r['status'] === 0) {
        $pending++;
    }
}
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">⚠️</div>
        <div><div class="astat__value"><?= (int) $total ?></div><div class="astat__label">异常与处置记录</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) $pending ?></div><div class="astat__label">本页处理中</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">✅</div>
        <div><div class="astat__value"><?= count($rows) - (int) $pending ?></div><div class="astat__label">本页已处理</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">📄</div>
        <div><div class="astat__value"><?= (int) $pages ?></div><div class="astat__label">总页数</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--orange">异常件记录</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead>
            <tr><th>包裹编号</th><th>商品</th><th>异常类型</th><th>说明</th><th>状态</th><th>处理结果</th><th class="nowrap">提交时间</th></tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="7"><div class="empty-state">暂无异常记录，包裹状态良好。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="mono"><a href="/user/packages/<?= (int) $r['package_id'] ?>"><?= View::e($r['package_no']) ?></a></td>
                    <td><?= View::e($r['item_name']) ?></td>
                    <td><span class="badge badge--red"><?= View::e(Status::ABNORMAL_TYPE[(int) $r['abnormal_type']] ?? '其他') ?></span></td>
                    <td><?= View::e($r['description']) ?></td>
                    <td><span class="badge badge--<?= Status::abnormalStatusTone((int) $r['status']) ?>"><?= View::e(Status::ABNORMAL_STATUS[(int) $r['status']] ?? '未知') ?></span></td>
                    <td><?= View::e((string) ($r['handle_result'] ?? '—')) ?></td>
                    <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pager">
            <?php if ($page > 1): ?><a href="javascript:abnPage(<?= $page - 1 ?>)">‹ 上一页</a><?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:abnPage(<?= $i ?>)"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a href="javascript:abnPage(<?= $page + 1 ?>)">下一页 ›</a><?php endif; ?>
            <span class="is-disabled">共 <?= (int) $total ?> 条</span>
        </nav>
    <?php endif; ?>
</section>
