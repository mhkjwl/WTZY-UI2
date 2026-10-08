<?php declare(strict_types=1);
/**
 * @file app/Views/member/tracking_region.php
 * @description 物流跟踪：运单列表与轨迹详情区域（检索/翻页/切换运单时局部替换，规范 §2.5）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array      $rows
 * @var int        $total
 * @var int        $page
 * @var int        $pages
 * @var array|null $selected
 * @var array      $records
 * @var string     $keyword
 */
?>
<div class="site-grid">
    <section class="panel">
        <h3 class="section-head"><span class="badge badge--blue">我的运单</span><span class="rule"></span>
            <span class="form-hint">共 <?= (int) $total ?> 张运单</span>
        </h3>
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th>运单号</th><th>承运单号</th><th>状态</th><th class="nowrap">操作</th></tr></thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="4"><div class="empty-state">暂无运单。</div></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr<?= $selected !== null && (int) $selected['id'] === (int) $r['id'] ? ' style="background:#eef2ff"' : '' ?>>
                        <td class="mono"><?= View::e($r['waybill_no']) ?></td>
                        <td class="mono"><?= View::e($r['carrier_tracking_no'] ?: '待生成') ?></td>
                        <td><span class="badge badge--<?= Status::waybillTone((int) $r['status']) ?>"><?= View::e(Status::waybill((int) $r['status'])) ?></span></td>
                        <td class="nowrap">
                            <a class="btn btn--ghost btn--sm" href="javascript:trackView(<?= (int) $r['id'] ?>)">查看轨迹</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="pager">
                <?php if ($page > 1): ?><a href="javascript:trackPage(<?= $page - 1 ?>)">‹ 上一页</a><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                    <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:trackPage(<?= $i ?>)"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $pages): ?><a href="javascript:trackPage(<?= $page + 1 ?>)">下一页 ›</a><?php endif; ?>
                <span class="is-disabled">共 <?= (int) $total ?> 条</span>
            </nav>
        <?php endif; ?>
    </section>

    <section class="panel">
        <?php if ($selected === null): ?>
            <h3 class="section-head"><span class="badge badge--violet">轨迹详情</span><span class="rule"></span></h3>
            <div class="empty-state">请在左侧选择一张运单查看完整轨迹。</div>
        <?php else: ?>
            <h3 class="section-head">
                <span class="badge badge--violet">轨迹详情</span>
                <span class="rule"></span>
                <a class="btn btn--ghost" href="/user/waybills/<?= (int) $selected['id'] ?>">运单详情</a>
            </h3>
            <div class="hero-address" style="margin-bottom:14px">
                <div class="hero-address__row"><span>运单号</span><b class="v-code mono"><?= View::e($selected['waybill_no']) ?></b></div>
                <div class="hero-address__row"><span>承运单号</span><b class="v-code mono"><?= View::e($selected['carrier_tracking_no'] ?: '待生成') ?></b></div>
                <div class="hero-address__row"><span>渠道</span><b><?= View::e((string) ($selected['channel_name'] ?? '—')) ?></b></div>
                <div class="hero-address__row"><span>当前状态</span><?= View::badge(Status::waybill((int) $selected['status']), Status::waybillTone((int) $selected['status'])) ?></div>
                <div class="hero-address__row"><span>清关状态</span><?= View::badge(Status::clear((int) $selected['clear_status']), Status::clearTone((int) $selected['clear_status'])) ?></div>
            </div>

            <?php if ($records === []): ?>
                <div class="empty-state">暂无轨迹记录。出库后承运商轨迹会同步到这里。</div>
            <?php else: ?>
                <ul class="msg-list">
                    <?php foreach ($records as $i => $t): ?>
                        <li class="msg-item<?= $i === 0 ? ' is-unread' : '' ?>">
                            <div class="msg-item__icon"><?= $i === 0 ? '🚚' : '•' ?></div>
                            <div class="msg-item__main">
                                <div class="msg-item__head">
                                    <span class="msg-item__title"><?= View::e($t['status_desc']) ?></span>
                                    <?php if ($i === 0): ?><span class="badge badge--green">最新</span><?php endif; ?>
                                </div>
                                <div class="msg-item__foot">
                                    <span class="msg-item__time"><?= View::e(substr((string) $t['event_time'], 0, 16)) ?></span>
                                    <span class="msg-item__read"><?= View::e((string) ($t['location'] ?? '')) ?></span>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
