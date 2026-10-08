<?php declare(strict_types=1);
/**
 * @file app/Views/member/dashboard.php
 * @description 会员中心工作台：账户概览 + 待办提示 + 最近动态
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $user
 * @var float $balance
 * @var float $frozen
 * @var int   $inStock
 * @var int   $pendingClaim
 * @var int   $onGoing
 * @var int   $packageTotal
 * @var int   $waybillTotal
 * @var int   $unread
 * @var int   $couponUsable
 * @var int   $ticketPending
 * @var int   $claimPending
 * @var array|null $nextLevel
 * @var int   $pendingPoints
 * @var array|null $storage
 * @var array $recentPackages
 * @var array $recentWaybills
 * @var array $recentMessages
 */
?>
<section class="welcome-banner">
    <div>
        <h2><?= date('H') < 12 ? '上午好' : (date('H') < 18 ? '下午好' : '晚上好') ?>，<?= View::e($user['display_name'] ?? $user['user_no'] ?? '') ?> 👋</h2>
        <p>专属入库识别码 <b><?= View::e($user['user_code'] ?? '') ?></b> · 会员等级 <?= View::e($user['level_name'] ?? '') ?></p>
    </div>
    <div class="welcome-banner__meta">
        <span class="badge--glass">⭐ 积分 <?= (int) ($user['points'] ?? 0) ?><?= $pendingPoints > 0 ? '（待激活 ' . (int) $pendingPoints . '）' : '' ?></span>
        <span class="badge--glass">🎫 可用券 <?= (int) $couponUsable ?></span>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">专属入库识别码</span><span class="rule"></span>
        <span class="form-hint">填写在海外仓收货信息中，包裹凭此识别归属</span></h3>
    <div class="code-badge">
        <b class="code-badge__value" id="uc-value"><?= View::e((string) ($user['user_code'] ?? '—')) ?></b>
        <button class="btn btn--ghost btn--sm" type="button" onclick="copyUserCode()">复制</button>
    </div>
</section>

<?php if ($storage !== null): ?>
    <?php
    // 剩余时长决定告警级别：宽裕绿、临近黄、紧急红、已超期深红
    $remain = (int) $storage['remain_seconds'];
    $tone = $remain > 7 * 86400 ? 'green' : ($remain > 3 * 86400 ? 'amber' : ($remain > 0 ? 'red' : 'deep'));
    $panelTone = ['green' => '#16a34a', 'amber' => '#f59e0b', 'red' => '#dc2626', 'deep' => '#991b1b'][$tone];
    ?>
    <section class="panel" style="border-left:4px solid <?= $panelTone ?>" id="storage-panel">
        <h3 class="section-head">
            <span class="badge badge--<?= $tone === 'deep' ? 'red' : $tone ?>">免费仓储</span><span class="rule"></span>
            <span class="form-hint">在库 <?= (int) $storage['package_count'] ?> 件 · 免费 <?= (int) $storage['free_days'] ?> 天（含会员加成）</span>
        </h3>
        <?php if ($remain > 0): ?>
            <p style="font-size:15px;margin:6px 0">
                剩余 <b style="color:<?= $panelTone ?>;font-size:19px" data-remain="<?= $remain ?>">计算中…</b>
                · 到期时间 <b><?= View::e($storage['due_at']) ?></b>
            </p>
            <?php if ((float) $storage['est_fee'] > 0): ?>
                <p class="form-hint">超期部分已产生预估仓储费 ¥<?= number_format((float) $storage['est_fee'], 2) ?>，出库时一并结算。</p>
            <?php else: ?>
                <p class="form-hint">到期前请及时提交转运；到期后按件/天计收仓储费。</p>
            <?php endif; ?>
        <?php else: ?>
            <p style="font-size:15px;margin:6px 0">
                已超期 <b style="color:<?= $panelTone ?>;font-size:19px"><?= (int) $storage['overdue_days'] ?> 天</b>
                · 到期时间 <?= View::e($storage['due_at']) ?>
            </p>
            <p class="form-hint">预估仓储费 <b style="color:<?= $panelTone ?>">¥<?= number_format((float) $storage['est_fee'], 2) ?></b>，将在出库结算时并入运费，请尽快处理。</p>
        <?php endif; ?>
        <div class="adm-toolbar" style="margin-top:8px">
            <a class="btn btn--primary btn--sm" href="/user/waybills/create">提交转运</a>
            <a class="btn btn--ghost btn--sm" href="/user/packages">查看在库包裹</a>
            <a class="btn btn--ghost btn--sm" href="/user/packages/abnormal">申请退运/销毁</a>
        </div>
    </section>
    <script>
    (function () {
        var el = document.querySelector('#storage-panel [data-remain]');
        if (!el) { return; }
        var seconds = parseInt(el.getAttribute('data-remain'), 10) || 0;
        /** 秒数转「N 天 N 小时 N 分」，与充值倒计时口径一致 */
        function fmt(s) {
            var d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
            return (d > 0 ? d + ' 天 ' : '') + h + ' 小时 ' + m + ' 分';
        }
        function tick() {
            if (seconds <= 0) { el.textContent = '已到期'; return; }
            el.textContent = fmt(seconds);
            seconds--;
        }
        tick();
        setInterval(tick, 1000);
    })();
    </script>
<?php endif; ?>

<script>
/** 识别码要填进海外仓收件信息，手抄易错，提供一键复制 */
function copyUserCode() {
    var el = document.getElementById('uc-value');
    if (!el) { return; }
    var code = el.textContent.trim();
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(function () {
            Notify.success('识别码已复制');
        }).catch(function () {
            Notify.warning('复制失败，请手动记录：' + code);
        });
        return;
    }
    Notify.warning('当前浏览器不支持一键复制，请手动记录：' + code);
}
</script>

<section class="astat-grid">
    <a class="astat astat--blue" href="/user/finance">
        <div class="astat__icon">💰</div>
        <div>
            <div class="astat__value">¥<?= number_format($balance, 2) ?></div>
            <div class="astat__label">账户余额<?= $frozen > 0 ? '（冻结 ¥' . number_format($frozen, 2) . '）' : '' ?></div>
        </div>
        <span class="astat__trend">充值</span>
    </a>
    <a class="astat astat--violet" href="/user/packages">
        <div class="astat__icon">📦</div>
        <div>
            <div class="astat__value"><?= (int) $inStock ?></div>
            <div class="astat__label">在库包裹 / 共 <?= (int) $packageTotal ?></div>
        </div>
        <span class="astat__trend">查看</span>
    </a>
    <a class="astat astat--emerald" href="/user/waybills">
        <div class="astat__icon">🧾</div>
        <div>
            <div class="astat__value"><?= (int) $onGoing ?></div>
            <div class="astat__label">进行中运单 / 共 <?= (int) $waybillTotal ?></div>
        </div>
        <span class="astat__trend">查看</span>
    </a>
    <a class="astat astat--amber" href="/user/messages">
        <div class="astat__icon">📩</div>
        <div>
            <div class="astat__value"><?= (int) $unread ?></div>
            <div class="astat__label">未读消息</div>
        </div>
        <span class="astat__trend">查看</span>
    </a>
</section>

<?php if ($pendingClaim > 0 || $ticketPending > 0 || $claimPending > 0): ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--orange">待办提醒</span><span class="rule"></span></h3>
    <div class="adm-toolbar" style="margin-bottom:0">
        <?php if ($pendingClaim > 0): ?>
            <a class="btn btn--ghost btn--sm" href="/user/packages?status=3">🔔 <?= (int) $pendingClaim ?> 个包裹待认领</a>
        <?php endif; ?>
        <?php if ($ticketPending > 0): ?>
            <a class="btn btn--ghost btn--sm" href="/user/tickets">🎧 <?= (int) $ticketPending ?> 个工单处理中</a>
        <?php endif; ?>
        <?php if ($claimPending > 0): ?>
            <a class="btn btn--ghost btn--sm" href="/user/claims">🛡️ <?= (int) $claimPending ?> 个理赔待处理</a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($nextLevel !== null): ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--purple">会员成长</span><span class="rule"></span></h3>
    <div class="meter tc-violet" style="margin-bottom:0">
        <div class="meter__head">
            <b>距离「<?= View::e($nextLevel['name']) ?>」还差 <?= (int) $nextLevel['need'] ?> 积分</b>
            <span><?= (int) ($user['points'] ?? 0) ?> 分</span>
        </div>
        <div class="meter__bar">
            <?php
            $target = (int) ($user['points'] ?? 0) + (int) $nextLevel['need'];
            $percent = $target > 0 ? round((int) ($user['points'] ?? 0) * 100 / $target, 1) : 0;
            ?>
            <div class="meter__fill" style="width:<?= (float) $percent ?>%"></div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--blue">最近包裹</span><span class="rule"></span>
        <a class="btn btn--primary" href="/user/forecast">+ 新增预报</a>
    </h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>包裹编号</th><th>快递单号</th><th>商品</th><th>状态</th><th class="nowrap">预报时间</th></tr></thead>
            <tbody>
            <?php if ($recentPackages === []): ?>
                <tr><td colspan="5"><div class="empty-state">还没有包裹。先在海外电商下单，拿到快递单号后在这里预报，到仓即可自动归属。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($recentPackages as $p): ?>
                <tr>
                    <td class="mono"><a href="/user/packages/<?= (int) $p['id'] ?>"><?= View::e($p['package_no']) ?></a></td>
                    <td class="mono"><?= View::e($p['tracking_no'] ?: '—') ?></td>
                    <td><?= View::e($p['item_name']) ?> ×<?= (int) $p['item_qty'] ?></td>
                    <td><span class="badge badge--<?= Status::packageTone((int) $p['status']) ?>"><?= View::e(Status::package((int) $p['status'])) ?></span></td>
                    <td class="nowrap"><?= View::e(substr((string) $p['created_at'], 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--emerald">最近运单</span><span class="rule"></span>
        <a class="btn btn--primary" href="/user/waybills/create">+ 新建转运</a>
    </h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>运单号</th><th>类型</th><th>金额</th><th>状态</th><th class="nowrap">创建时间</th></tr></thead>
            <tbody>
            <?php if ($recentWaybills === []): ?>
                <tr><td colspan="5"><div class="empty-state">还没有转运订单。包裹认领后可在此提交转运。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($recentWaybills as $w): ?>
                <tr>
                    <td class="mono"><a href="/user/waybills/<?= (int) $w['id'] ?>"><?= View::e($w['waybill_no']) ?></a></td>
                    <td><?= [1 => '原箱转运', 2 => '合箱转运', 3 => '分箱转运'][(int) $w['bill_type']] ?? '—' ?></td>
                    <td class="nowrap"><?= View::e((string) $w['currency']) ?> <?= number_format((float) $w['total_amount'], 2) ?></td>
                    <td><span class="badge badge--purple"><?= View::e(Status::waybill((int) $w['status'])) ?></span></td>
                    <td class="nowrap"><?= View::e(substr((string) $w['created_at'], 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--violet">网站公告</span><span class="rule"></span>
        <a class="btn btn--ghost" href="/user/notices">全部公告</a>
    </h3>
    <ul class="msg-list">
        <?php if ($recentNotices === []): ?>
            <li><div class="empty-state">暂无网站公告。平台发布的公告会在这里展示。</div></li>
        <?php endif; ?>
        <?php foreach ($recentNotices as $n): ?>
            <li class="msg-item">
                <div class="msg-item__icon"><?= (int) ($n['notice_type'] ?? 1) === 2 ? '❗' : '📢' ?></div>
                <div class="msg-item__main">
                    <div class="msg-item__head">
                        <a class="msg-item__title" href="/user/notices/<?= (int) $n['id'] ?>"><?= View::e((string) $n['title']) ?></a>
                        <span class="badge badge--<?= (int) ($n['notice_type'] ?? 1) === 2 ? 'red' : 'gray' ?>"><?= (int) ($n['notice_type'] ?? 1) === 2 ? '重要' : '普通' ?></span>
                    </div>
                    <p class="msg-item__body"><?= View::e(mb_substr((string) $n['content'], 0, 80)) ?><?= mb_strlen((string) $n['content']) > 80 ? '…' : '' ?></p>
                    <div class="msg-item__foot"><span class="msg-item__time"><?= View::e(substr((string) $n['updated_at'], 0, 16)) ?></span></div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--gray">最新消息</span><span class="rule"></span>
        <a class="btn btn--ghost" href="/user/messages">全部消息</a>
    </h3>
    <ul class="msg-list">
        <?php if ($recentMessages === []): ?>
            <li><div class="empty-state">暂无消息。系统通知会送达这里。</div></li>
        <?php endif; ?>
        <?php foreach ($recentMessages as $m): ?>
            <li class="msg-item">
                <div class="msg-item__icon">📩</div>
                <div class="msg-item__main">
                    <div class="msg-item__head">
                        <span class="msg-item__title"><?= View::e($m['title']) ?></span>
                    </div>
                    <p class="msg-item__body"><?= View::e(mb_substr((string) $m['content'], 0, 80)) ?><?= mb_strlen((string) $m['content']) > 80 ? '…' : '' ?></p>
                    <div class="msg-item__foot"><span class="msg-item__time"><?= View::e(substr((string) $m['updated_at'], 0, 16)) ?></span></div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
