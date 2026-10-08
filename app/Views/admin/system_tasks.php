<?php declare(strict_types=1);
/**
 * @file app/Views/admin/system_tasks.php
 * @description 自动任务页：查看执行状态并手动触发（自动签收 / 待激活积分入账 / 退运销毁兜底退款）
 * @author AI
 * @version 0.6
 * @date 2026-09-16
 */

use App\Core\View;

/**
 * @var string $lastRunAt
 * @var string $lazyMinutes
 * @var string $autoSignDays
 * @var string $delayDays
 * @var string $runToken
 * @var int    $pendingCount
 * @var array  $perms
 * @var string $csrf
 */
$taskUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8099') . '/api/tasks/run';
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">🚚</div>
        <div><div class="astat__value"><?= View::e($autoSignDays) ?> 天</div><div class="astat__label">自动签收阈值（出库后）</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">⭐</div>
        <div><div class="astat__value"><?= (int) $pendingCount ?></div><div class="astat__label">待激活积分笔数</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= View::e($delayDays) ?> 天</div><div class="astat__label">积分到账缓冲（T+N）</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">🔄</div>
        <div><div class="astat__value" style="font-size:15px"><?= View::e($lastRunAt) ?></div><div class="astat__label">上次执行时间</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--cyan">任务说明</span><span class="rule"></span>
        <?php if (!empty($perms['system.manage'])): ?>
            <button class="btn btn--primary" onclick="runTasks()">立即执行全部任务</button>
        <?php endif; ?>
    </h3>
    <div class="hero-address">
        <div class="hero-address__row"><span>自动签收</span><b>出库满 <?= View::e($autoSignDays) ?> 天且用户未手动签收的运单自动置为已签收；清关异常、有进行中理赔或异常包裹的运单跳过</b></div>
        <div class="hero-address__row"><span>积分入账</span><b>签收满 <?= View::e($delayDays) ?> 天的待激活积分自动到账；期间退运/销毁/取消的积分直接作废</b></div>
        <div class="hero-address__row"><span>兜底退款</span><b>未出库且包裹已全部退运/销毁的运单自动退还运费（仓库端处理时已即时触发，此处为重试兜底）</b></div>
        <div class="hero-address__row"><span>执行频率</span><b>页面访问时自动顺带执行（每 <?= View::e($lazyMinutes) ?> 分钟最多一次）；生产环境建议配置服务器 cron 定时执行 <code>php tasks/run.php all</code></b></div>
    </div>
    <p class="form-hint" style="margin-top:10px">所有任务均为幂等设计：重复执行不会重复签收、重复发积分或重复退款。</p>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--purple">远程触发（URL）</span><span class="rule"></span>
        <?php if (!empty($perms['system.manage'])): ?>
            <button class="btn btn--ghost" id="task-token-btn" onclick="resetToken()"><?= $runToken === '' ? '生成触发 Token' : '重置触发 Token' ?></button>
        <?php endif; ?>
    </h3>
    <div class="empty-state" id="task-url-empty" <?= $runToken === '' ? '' : 'hidden' ?>>
        尚未配置远程触发 Token。点击右上角「生成触发 Token」后，把下方完整 URL 配置到外部定时任务（服务器 cron / 云函数 / 其他系统）即可定时调用本系统自动任务。
    </div>
    <div class="hero-address" id="task-url-box" <?= $runToken === '' ? 'hidden' : '' ?>>
        <div class="hero-address__row"><span>触发 URL</span>
            <b style="word-break:break-all" id="task-trigger-url"><?= $runToken === '' ? '' : View::e($taskUrl . '?token=' . $runToken) ?></b></div>
        <div class="hero-address__row"><span>说明</span>
            <b>访问该 URL 即执行全部自动任务（自动签收 / 积分入账 / 兜底退款），无需登录态；
                Token 是访问凭证，请勿泄露；泄露后可点击「重置触发 Token」使其立即失效。</b></div>
    </div>
    <div class="adm-toolbar" id="task-url-ops" style="margin-top:10px" <?= $runToken === '' ? 'hidden' : '' ?>>
        <button class="btn btn--ghost btn--sm" onclick="copyTaskUrl()">复制 URL</button>
        <button class="btn btn--ghost btn--sm" onclick="testTaskUrl()">立即测试</button>
    </div>
</section>

<script>
function runTasks() {
    admConfirm('确认立即执行全部自动任务吗？将处理到期的自动签收、积分入账与兜底退款。', async function () {
        var res = await App.post('/admin/system/tasks/run', {});
        App.handle(res, '任务已执行');
        if (res.success) {
            var d = res.data || {};
            Notify.info('自动签收 ' + (d.auto_sign || 0) + ' 单 · 积分入账 ' + (d.activate_points || 0) + ' 笔 · 兜底退款 ' + (d.refund_disposed || 0) + ' 单');
        }
    }, { title: '执行自动任务', confirmText: '确认执行' });
}

function resetToken() {
    var hasToken = '<?= $runToken === '' ? '0' : '1' ?>' === '1';
    admConfirm(hasToken ? '确定重置触发 Token 吗？旧 Token 将立即失效，需同步更新外部定时任务中的 URL。' : '确定生成远程触发 Token 吗？', async function () {
        var res = await App.post('/admin/system/tasks/token', {});
        App.handle(res, 'Token 已生成');
        if (res.success && res.data && res.data.token) {
            var url = '<?= View::e($taskUrl) ?>' + '?token=' + res.data.token;
            // 元素始终存在：未配置时隐藏、配置后显示，避免空态分支缺失元素
            document.getElementById('task-trigger-url').textContent = url;
            document.getElementById('task-url-empty').hidden = true;
            document.getElementById('task-url-box').hidden = false;
            document.getElementById('task-url-ops').hidden = false;
            var btn = document.getElementById('task-token-btn');
            if (btn) { btn.textContent = '重置触发 Token'; }
        }
    }, { title: '生成触发 Token', confirmText: '确认' });
}

function copyTaskUrl() {
    var url = document.getElementById('task-trigger-url');
    if (!url) { return; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url.textContent).then(function () { Notify.success('URL 已复制'); });
    } else {
        Notify.info('请手动复制 URL');
    }
}

function testTaskUrl() {
    var url = document.getElementById('task-trigger-url');
    if (!url) { return; }
    Notify.info('正在通过远程 URL 触发，请稍候…');
    fetch(url.textContent, { headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d && d.success) {
                Notify.success('触发成功：自动签收 ' + (d.data.auto_sign || 0) + ' 单 · 积分入账 ' + (d.data.activate_points || 0) + ' 笔 · 兜底退款 ' + (d.data.refund_disposed || 0) + ' 单');
            } else {
                Notify.error((d && d.message) || '触发失败');
            }
        })
        .catch(function () { Notify.error('触发失败，请检查网络'); });
}
</script>