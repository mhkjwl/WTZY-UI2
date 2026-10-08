<?php declare(strict_types=1);
/**
 * @file app/Views/member/recharge.php
 * @description 会员中心：余额充值（档位选择 → 在线支付/人工入账 → 扫码或跳转 → 实时监测入账 → 在线支付超时自动关闭，人工入账不受时效限制）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-16
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array  $account
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var float  $minAmount
 * @var array  $config
 * @var array  $onlineMethods 本地支付方式编码 => 名称（仅网关已开通的）
 */
$expireMinutes = (int) $config['expire_minutes'];
$expireSeconds = $expireMinutes * 60;
$availableMethods = $onlineMethods;
if ($config['manual_enabled'] === '1') {
    $availableMethods[5] = '银行转账（人工入账）';
}
$onlineCodes = array_keys($onlineMethods);
?>
<div class="notice-bar">
    <span class="notice-bar__icon">⚠️</span>
    <span>温馨提示：按需充值，不支持退款。</span>
</div>

<div id="rc-stats-host"><?= View::partial('member/recharge_stats', get_defined_vars()) ?></div>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">发起充值</span><span class="rule"></span></h3>

    <?php if ($availableMethods === []): ?>
        <div class="empty-state">当前未开放任何充值方式，请联系客服。</div>
    <?php else: ?>
        <div class="form-field">
            <label class="form-label">选择充值金额</label>
            <div class="adm-toolbar" id="rc-presets">
                <?php foreach ($config['amounts_list'] as $value): ?>
                    <button class="btn btn--ghost rc-amount" type="button" data-amount="<?= (int) $value ?>">¥<?= number_format((int) $value) ?></button>
                <?php endforeach; ?>
                <?php if ($config['amounts_list'] === []): ?>
                    <span class="form-hint">未配置预设档位，请直接填写金额</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-row">
            <?php if ($config['custom_enabled'] === '1'): ?>
                <div class="form-field" style="max-width:240px">
                    <label class="form-label">自定义金额（元）</label>
                    <input class="form-input" type="number" step="0.01" min="<?= (float) $minAmount ?>" id="rc-amount" placeholder="最低 ¥<?= number_format($minAmount, 0) ?>">
                </div>
            <?php else: ?>
                <input type="hidden" id="rc-amount" value="">
                <div class="form-field" style="max-width:240px">
                    <label class="form-label">充值金额</label>
                    <input class="form-input" id="rc-amount-show" readonly placeholder="请从上方档位选择">
                </div>
            <?php endif; ?>
            <div class="form-field" style="max-width:280px">
                <label class="form-label">支付方式</label>
                <select class="form-input" id="rc-method">
                    <?php foreach ($availableMethods as $code => $label): ?>
                        <option value="<?= (int) $code ?>"><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label">备注（选填）</label>
                <input class="form-input" id="rc-remark" maxlength="255" placeholder="如：转账尾号 8888">
            </div>
        </div>
        <div class="adm-toolbar" style="margin-bottom:0">
            <button class="btn btn--primary" type="button" onclick="createRecharge()">提交充值单</button>
            <span class="form-hint">
                在线支付请在 <?= (int) $expireMinutes ?> 分钟内完成付款，超时未支付将自动关闭；
                <?php if ($config['manual_enabled'] === '1'): ?>银行转账（人工入账）不受支付时效限制，转账后点击「我已完成支付」即可。<?php endif; ?>
            </span>
        </div>
    <?php endif; ?>
</section>

<div id="rc-list-host"><?= View::partial('member/recharge_list', get_defined_vars()) ?></div>

<dialog class="adm-dialog" id="dlg-pay">
    <div class="adm-dialog__head">完成支付 · <span id="pay-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="pay-id">
        <div class="hero-address" style="margin-bottom:14px">
            <div class="hero-address__row"><span>充值金额</span><b id="pay-amount" style="color:#4f46e5;font-size:19px"></b></div>
            <div class="hero-address__row"><span>支付方式</span><b id="pay-method"></b></div>
        </div>

        <div id="pay-status-box" class="pay-status" hidden>
            <span class="pay-status__dot"></span><span id="pay-status-text"></span>
        </div>

        <div id="pay-loading" hidden>
            <p class="form-hint" style="text-align:center;line-height:2">正在向支付平台发起请求…</p>
        </div>

        <div id="pay-error-box" hidden>
            <p class="form-hint" style="line-height:1.9;color:#b91c1c" id="pay-error-text"></p>
            <button class="btn btn--ghost btn--block" type="button" onclick="retryPay()">重新发起支付</button>
        </div>

        <div id="pay-online-box" hidden>
            <div id="pay-qr-box" hidden style="text-align:center">
                <div id="pay-qrcode" style="display:inline-block;padding:10px;background:#fff;border:1px solid #e5e9f2;border-radius:10px"></div>
                <p class="form-hint" style="margin-top:10px">
                    请用<b id="pay-qr-tip">手机</b>扫码完成付款，付款成功后本页面会自动更新。
                </p>
            </div>
            <div id="pay-frame-box" hidden>
                <iframe id="pay-frame" style="width:100%;height:420px;border:1px solid #e5e9f2;border-radius:10px" referrerpolicy="no-referrer"></iframe>
            </div>
            <div id="pay-jump-box" hidden>
                <p class="form-hint" style="line-height:1.9">点击下方按钮前往支付平台完成付款。</p>
                <button class="btn btn--primary btn--block" type="button" onclick="gotoPayUrl()">前往支付</button>
            </div>
            <p class="form-hint" style="margin-top:14px">
                支付结果会自动到账，无需手动确认；若已扣款但状态未更新，可点击下方「我已支付，刷新状态」。
            </p>
        </div>

        <div id="pay-manual-box" hidden>
            <p class="form-hint" style="margin-bottom:10px">请按以下信息转账，<b>转账时请在备注中填写充值单号</b>：</p>
            <pre id="pay-manual-info" style="white-space:pre-wrap;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;padding:14px;font-size:13px;line-height:1.8"></pre>
            <p class="form-hint" style="margin-top:14px">
                转账完成后点击下方「我已完成支付」告知客服，<b>银行转账不受支付时效限制，无需担心超时关单</b>；
                客服核实入账后本页面会自动更新，无需手动刷新。
            </p>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="closePayDialog()">稍后再说</button>
        <button class="btn btn--ghost" id="pay-refresh-btn" onclick="refreshPayStatus(true)" hidden>我已支付，刷新状态</button>
        <button class="btn btn--primary" id="pay-done-btn" onclick="markPaid()" hidden>我已完成支付</button>
    </div>
</dialog>

<script src="<?= View::asset('/assets/common/qrcode.min.js') ?>"></script>
<script>
var MANUAL_INFO = <?= json_encode($config['manual_info'], JSON_UNESCAPED_UNICODE) ?>;
var ONLINE_CODES = <?= json_encode(array_map('intval', $onlineCodes)) ?>;
var EXPIRE_SECONDS = <?= (int) $expireSeconds ?>;
var PAY_URL = '';
var PAY_TIMER = null;      // 状态轮询
var COUNT_TIMER = null;    // 表单内倒计时（未开弹窗时也在走）
var PAY_TICKS = 0;
var PAY_DEADLINE = 0;
var PAY_IS_MANUAL = false; // 当前弹窗是否人工入账渠道：决定等待态文案与按钮语义

/**
 * 翻页：一次请求取回统计卡与充值记录两块，分别注入各自挂载点，不整页刷新（规范 §2.5.2 分页）
 * 统计卡在表单上方、记录表在表单下方，中间的表单不可替换，故分两处注入
 * silent=true 供页面级自动检测使用：不加淡出效果、失败也不弹提示，避免后台轮询打扰用户
 */
async function rcReload(page, push, silent) {
    var statsHost = document.getElementById('rc-stats-host');
    var listHost = document.getElementById('rc-list-host');
    if (!statsHost || !listHost) { return; }
    if (!silent) { listHost.style.opacity = '0.55'; }
    try {
        var res = await fetch('/user/finance/recharge?page=' + page, { headers: { 'X-Requested-With': 'fetch' } });
        if (!res.ok || res.redirected) {
            if (!silent) { Notify.error('登录状态已失效，请重新登录'); }
            return;
        }
        var parsed = new DOMParser().parseFromString(await res.text(), 'text/html');
        var stats = parsed.getElementById('rc-stats');
        var list = parsed.getElementById('rc-list');
        if (!stats || !list) {
            if (!silent) { Notify.error('列表数据异常，请刷新页面重试'); }
            return;
        }
        statsHost.innerHTML = stats.innerHTML;
        listHost.innerHTML = list.innerHTML;
        if (push !== false) { history.pushState({}, '', '/user/finance/recharge?page=' + page); }
    } catch (err) {
        if (!silent) { Notify.error('网络异常，列表未能刷新'); }
    } finally {
        listHost.style.opacity = '1';
    }
}

function rcPage(n) { rcReload(n); }

/** 当前页码（翻页与自动检测共用，避免两处各解析一次地址栏） */
function rcCurrentPage() {
    return parseInt(new URLSearchParams(location.search).get('page'), 10) || 1;
}

/** 页面上仍在等待结果的充值单数量：待支付(0) 与 待入账(5) */
function rcPendingRowCount() {
    return document.querySelectorAll('#rc-body tr[data-status="0"], #rc-body tr[data-status="5"]').length;
}

/**
 * 页面级自动检测：弹窗关掉、用户仍停留在充值页时，也能自动看到客服入账的结果
 * 仅在「有等待中的单、页面可见、弹窗未开」时静默刷新列表，与弹窗内的逐单轮询互补而不叠加
 */
var RC_WATCH_TIMER = setInterval(function () {
    var dlg = document.getElementById('dlg-pay');
    if (dlg && dlg.open) { return; }
    if (document.hidden) { return; }
    if (rcPendingRowCount() === 0) { return; }
    rcReload(rcCurrentPage(), false, true);
}, 20000);

window.addEventListener('popstate', function () {
    rcReload(rcCurrentPage(), false);
});

document.querySelectorAll('.rc-amount').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.rc-amount').forEach(function (b) { b.classList.remove('btn--primary'); b.classList.add('btn--ghost'); });
        this.classList.remove('btn--ghost');
        this.classList.add('btn--primary');
        var input = document.getElementById('rc-amount');
        if (input) { input.value = this.dataset.amount; }
        var show = document.getElementById('rc-amount-show');
        if (show) { show.value = '¥' + this.dataset.amount; }
    });
});

function currentAmount() {
    var input = document.getElementById('rc-amount');
    return parseFloat(input ? input.value : 0) || 0;
}

function methodLabel(code) {
    var sel = document.getElementById('rc-method');
    if (!sel) { return ''; }
    var opt = Array.prototype.find.call(sel.options, function (o) { return o.value === String(code); });
    return opt ? opt.textContent.trim() : '';
}

function fmtRemain(sec) {
    if (sec <= 0) { return '已超时'; }
    var m = Math.floor(sec / 60), s = sec % 60;
    return '剩余 ' + m + ':' + (s < 10 ? '0' : '') + s;
}

/**
 * 列表内倒计时：服务端只给剩余秒数，首次渲染时换算成本地截止时刻再逐秒递减
 * （不直接下发时间戳，避免服务器与浏览器时钟/时区不一致导致倒计时偏差）
 */
function tickRowCountdowns() {
    var now = Math.floor(Date.now() / 1000);
    document.querySelectorAll('.rc-remain').forEach(function (el) {
        if (!el.dataset.until) {
            el.dataset.until = String(now + (parseInt(el.dataset.remain, 10) || 0));
        }
        var remain = parseInt(el.dataset.until, 10) - now;
        el.textContent = remain > 0 ? fmtRemain(remain) : '已超时，正在关闭…';
    });
    // 弹窗内同步展示剩余时间
    if (PAY_DEADLINE > 0 && document.getElementById('dlg-pay').open) {
        var remain = PAY_DEADLINE - now;
        var line = document.getElementById('pay-status-text');
        if (remain <= 0) {
            clearInterval(PAY_TIMER); PAY_TIMER = null;
            line.textContent = '订单已超时，正在关闭…';
            refreshPayStatus(false);
        } else if (line && line.dataset.mode === 'wait') {
            line.textContent = '正在等待支付结果…（' + fmtRemain(remain) + '）';
        }
    }
}
COUNT_TIMER = setInterval(tickRowCountdowns, 1000);
tickRowCountdowns();

/** 打开支付弹窗：先展示加载态，再按网关返回的 pay_type 渲染对应付款界面 */
async function openPay(id, amount, method, no) {
    document.getElementById('pay-id').value = id;
    document.getElementById('pay-no').textContent = no;
    document.getElementById('pay-amount').textContent = '¥' + Number(amount).toFixed(2);
    document.getElementById('pay-method').innerHTML = '<span class="badge badge--blue">' + (methodLabel(method) || '—') + '</span>';
    document.getElementById('dlg-pay').showModal();
    stopPoll();
    PAY_DEADLINE = 0;

    var isOnline = ONLINE_CODES.indexOf(Number(method)) !== -1;
    var isManual = Number(method) === 5;
    PAY_IS_MANUAL = isManual;
    // 「我已完成支付」仅人工入账渠道需要（在线渠道靠系统自动核对到账）
    document.getElementById('pay-manual-box').hidden = !isManual;
    document.getElementById('pay-online-box').hidden = !isOnline;
    document.getElementById('pay-loading').hidden = !isOnline;
    document.getElementById('pay-error-box').hidden = true;
    document.getElementById('pay-manual-info').textContent = MANUAL_INFO || '收款信息未配置，请联系客服。';
    document.getElementById('pay-done-btn').hidden = !isManual;
    // 两种渠道都保留手动刷新：人工入账用于用户自查是否已被核实入账，在线渠道用于已扣款但回调未达时补核
    document.getElementById('pay-refresh-btn').hidden = false;
    document.getElementById('pay-refresh-btn').textContent = isManual ? '刷新状态' : '我已支付，刷新状态';

    if (isManual) {
        // 与在线支付一致：打开弹窗即开始实时监测，管理员核实入账的瞬间本窗口自动提示到账
        setStatusLine('wait', '正在确认充值单状态…');
        startPoll(id);
        await refreshPayStatus(false);
        return;
    }
    if (!isOnline) {
        setStatusLine('closed', '该充值单使用的支付方式已停用，请取消本单后重新提交');
        return;
    }
    await requestPay(id);
}

/** 向服务端发起支付，失败时展示原因并可重试 */
async function requestPay(id) {
    document.getElementById('pay-error-box').hidden = true;
    document.getElementById('pay-loading').hidden = false;
    document.getElementById('pay-online-box').hidden = true;
    setStatusLine('wait', '正在向支付平台发起请求…');

    var res = await App.post('/user/finance/recharge/pay', { id: id });
    document.getElementById('pay-loading').hidden = true;
    if (!res.success) {
        document.getElementById('pay-error-text').textContent = res.message || '发起支付失败，请稍后重试';
        document.getElementById('pay-error-box').hidden = false;
        setStatusLine('closed', '支付未能发起');
        return;
    }
    document.getElementById('pay-online-box').hidden = false;
    PAY_DEADLINE = Math.floor(Date.now() / 1000) + (parseInt(res.data.remain, 10) || EXPIRE_SECONDS);
    renderPayInfo(res.data);
    startPoll(id);
}

function retryPay() {
    var id = val('pay-id');
    if (id) { requestPay(id); }
}

/** 弹窗状态行：mode=wait 时会被倒计时持续刷新 */
function setStatusLine(mode, text) {
    var box = document.getElementById('pay-status-box');
    var line = document.getElementById('pay-status-text');
    box.hidden = text === '';
    box.className = 'pay-status pay-status--' + (mode === 'paid' ? 'ok' : (mode === 'wait' ? 'wait' : 'bad'));
    line.dataset.mode = mode;
    line.textContent = text;
}

/** 依据 pay_type 渲染：qrcode 出二维码，jump/urlscheme 走跳转，html 内嵌支付页 */
function renderPayInfo(data) {
    var qrBox = document.getElementById('pay-qr-box');
    var frameBox = document.getElementById('pay-frame-box');
    var jumpBox = document.getElementById('pay-jump-box');
    qrBox.hidden = true; frameBox.hidden = true; jumpBox.hidden = true;
    PAY_URL = '';

    var type = String(data.pay_type || '');
    if (type === 'qrcode') {
        PAY_URL = data.pay_info;
        renderQr(data.pay_info);
        qrBox.hidden = false;
    } else if (type === 'html') {
        document.getElementById('pay-frame').srcdoc = data.pay_info;
        frameBox.hidden = false;
    } else {
        // jump / urlscheme 以及其它未知类型统一按跳转处理
        PAY_URL = data.pay_info;
        jumpBox.hidden = false;
    }
}

function renderQr(text) {
    var box = document.getElementById('pay-qrcode');
    box.innerHTML = '';
    if (typeof QRCode === 'undefined') {
        box.innerHTML = '<p class="form-hint">二维码组件未加载，请点击下方「前往支付」</p>';
        Notify.warning('二维码组件未加载，请改用「前往支付」');
        return;
    }
    new QRCode(box, { text: text, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });
}

function gotoPayUrl() {
    if (!PAY_URL) { Notify.warning('支付链接尚未就绪，请稍后重试'); return; }
    location.href = PAY_URL;
}

/**
 * 实时监测：每 2 秒查一次本地状态（回调入账后会立即反映），
 * 每 5 次顺带向支付平台核对一次，兼顾实时性与网关压力
 */
function startPoll(id) {
    PAY_TICKS = 0;
    stopPoll();
    PAY_TIMER = setInterval(function () { refreshPayStatus(false); }, 2000);
}

function stopPoll() {
    if (PAY_TIMER) { clearInterval(PAY_TIMER); PAY_TIMER = null; }
}

async function refreshPayStatus(manual) {
    var id = val('pay-id');
    if (!id) { return; }
    PAY_TICKS++;
    var sync = manual || PAY_TICKS % 5 === 0;
    var res = await App.post('/user/finance/recharge/status', { id: id, sync: sync ? 1 : 0 });
    if (!res.success) { return; }
    var data = res.data;

    if (data.paid) {
        stopPoll();
        markRowPaid(id);
        updateBalance(data.balance);
        setStatusLine('paid', PAY_IS_MANUAL ? '客服已核实入账，充值成功' : '支付成功，已入账');
        Notify.success('支付成功 ¥' + Number(data.amount).toFixed(2));
        setTimeout(function () { document.getElementById('dlg-pay').close(); }, 1200);
        return;
    }
    // 待入账（5）：用户已声明付款、等待管理员核实；该状态不会被超时自动关闭，故不再倒计时
    if (data.pending) {
        PAY_DEADLINE = 0;
        markRowPending(id);
        setStatusLine('wait', '已提交付款确认，等待客服核实入账…');
        if (manual) { Notify.info('客服核实到账后将自动入账，本窗口会自动更新'); }
        return;
    }
    if (data.closed) {
        stopPoll();
        markRowClosed(id);
        setStatusLine('closed', '该充值单已关闭，如需继续充值请重新提交');
        Notify.warning('该充值单已关闭');
        return;
    }
    // 人工入账渠道不受支付时效约束（超时自动关单已豁免），故不展示倒计时
    if (PAY_IS_MANUAL) {
        PAY_DEADLINE = 0;
        setStatusLine('wait', '请完成转账后点击「我已完成支付」，客服核实到账后将自动入账');
        if (manual) { Notify.info('暂未收到到账结果：' + (data.text || '待支付')); }
        return;
    }
    if (data.remain > 0) {
        PAY_DEADLINE = Math.floor(Date.now() / 1000) + data.remain;
        setStatusLine('wait', '正在等待支付结果…（' + fmtRemain(data.remain) + '）');
    }
    if (manual) { Notify.info('暂未收到支付结果：' + (data.text || '待支付')); }
}

/** 局部更新：本行置为已支付（不整页刷新） */
function markRowPaid(id) {
    var row = document.querySelector('#rc-body tr[data-id="' + id + '"]');
    if (!row) { return; }
    row.dataset.status = '1'; // 供页面级自动检测判断「是否还有等待中的单」
    var badge = row.querySelector('.badge');
    if (badge) { badge.className = 'badge badge--green'; badge.textContent = '已支付'; }
    var remain = row.querySelector('.rc-remain');
    if (remain) { remain.remove(); }
    if (row.cells[5]) { row.cells[5].innerHTML = '<span class="msg-item__read">—</span>'; }
}

/** 局部更新：本行置为待入账（用户已声明付款，等待客服核实） */
function markRowPending(id) {
    var row = document.querySelector('#rc-body tr[data-id="' + id + '"]');
    if (!row) { return; }
    row.dataset.status = '5';
    var badge = row.querySelector('.badge');
    if (badge) { badge.className = 'badge badge--orange'; badge.textContent = '待入账'; }
    var remain = row.querySelector('.rc-remain');
    if (remain) { remain.remove(); }
    if (row.cells[5]) { row.cells[5].innerHTML = '<span class="msg-item__read">等待入账</span>'; }
}

/** 局部更新：本行置为已关闭（超时自动关闭或用户取消） */
function markRowClosed(id) {
    var row = document.querySelector('#rc-body tr[data-id="' + id + '"]');
    if (!row) { return; }
    row.dataset.status = '4';
    var badge = row.querySelector('.badge');
    if (badge) { badge.className = 'badge badge--gray'; badge.textContent = '已关闭'; }
    var remain = row.querySelector('.rc-remain');
    if (remain) { remain.remove(); }
    if (row.cells[5]) { row.cells[5].innerHTML = '<span class="msg-item__read">—</span>'; }
}

function updateBalance(balance) {
    if (typeof balance !== 'number') { return; }
    var card = document.querySelector('.astat--blue .astat__value');
    if (card) { card.textContent = '¥' + balance.toFixed(2); }
}

function closePayDialog() {
    stopPoll();
    PAY_DEADLINE = 0;
    document.getElementById('dlg-pay').close();
}

async function createRecharge() {
    var amount = currentAmount();
    if (!(amount > 0)) { Notify.warning('请选择或填写充值金额'); return; }
    if (amount < <?= (float) $minAmount ?>) { Notify.warning('单笔充值不得低于 ¥<?= number_format($minAmount, 2) ?>'); return; }

    var method = Number(val('rc-method'));
    // 人工入账渠道不受支付时效约束，提示语不误导用户「必须在 N 分钟内付款」
    var tip = method === 5
        ? '银行转账不受支付时效限制，转账完成后点击「我已完成支付」即可。'
        : '提交后 ' + Math.round(EXPIRE_SECONDS / 60) + ' 分钟内完成付款即可自动入账。';
    admConfirm('确定提交 ¥' + amount.toFixed(2) + ' 的充值单吗？' + tip, async function () {
        var res = await App.post('/user/finance/recharge/create', {
            amount: amount, pay_method: val('rc-method'), remark: val('rc-remark').trim()
        });
        App.handle(res, '充值单已创建');
        if (!res.success) { return; }
        var data = res.data;
        insertRechargeRow(data);
        openPay(data.id, data.amount, data.pay_method, data.order_no || ('RC' + data.id));
    }, { title: '提交充值单', confirmText: '确认提交' });
}

/** 局部插入新充值记录行，不整页刷新 */
function insertRechargeRow(data) {
    var body = document.getElementById('rc-body');
    if (!body) { return; }
    var empty = body.querySelector('td[colspan]');
    if (empty) { empty.closest('tr').remove(); }
    // 人工入账渠道不受支付时效约束，不渲染倒计时，避免用户误以为会超时被关单
    var isManual = Number(data.pay_method) === 5;
    var stateHtml = '<span class="badge badge--orange">待支付</span><br>'
        + (isManual ? '<small class="form-hint">不受支付时效限制</small>' : '<small class="rc-remain"></small>');
    var tr = document.createElement('tr');
    tr.dataset.id = String(data.id);
    tr.dataset.status = '0';
    tr.innerHTML = '<td class="mono"></td><td class="nowrap"></td><td></td>'
        + '<td>' + stateHtml + '</td>'
        + '<td class="nowrap">刚刚</td>'
        + '<td class="nowrap"><button class="btn btn--ghost btn--sm">取消</button></td>';
    tr.cells[0].textContent = data.order_no || ('RC' + data.id);
    tr.cells[1].textContent = '¥' + Number(data.amount).toFixed(2);
    tr.cells[2].textContent = data.method_name || '';
    var remain = tr.querySelector('.rc-remain');
    if (remain) { remain.dataset.remain = String(parseInt(data.expire_seconds, 10) || EXPIRE_SECONDS); }
    tr.querySelector('button').addEventListener('click', function () { cancelRecharge(data.id, tr.cells[0].textContent); });
    body.insertBefore(tr, body.firstChild);
    tickRowCountdowns();
}

/**
 * 人工入账渠道专用：告知客服已付款，等待核实入账
 * 提交后不关闭弹窗——状态转「待入账」后仍由轮询持续监测，客服入账的瞬间本窗口会自动提示到账；
 * 若提交时管理员已先行入账（状态早已翻转），紧接着的这次刷新也会直接给出到账结果
 */
function markPaid() {
    var id = val('pay-id');
    admConfirm('确认已完成转账吗？客服核实到账后为您入账。', async function () {
        var res = await App.post('/user/finance/recharge/paid', { id: id });
        App.handle(res, '已提交付款确认');
        if (res.success) {
            // 已声明付款，本单不可再重复标记；后续交给「刷新状态」与自动轮询
            document.getElementById('pay-done-btn').hidden = true;
        }
        await refreshPayStatus(false);
    }, { title: '确认已付款', confirmText: '确认已支付' });
}

function cancelRecharge(id, no) {
    admConfirm('确定取消充值单 ' + no + ' 吗？', async function () {
        var res = await App.post('/user/finance/recharge/cancel', { id: id });
        App.handle(res, '充值单已取消');
        if (!res.success) { return; }
        stopPoll();
        markRowClosed(id);
        if (val('pay-id') === String(id)) { closePayDialog(); }
    }, { title: '取消充值单', confirmText: '确认取消', danger: true });
}
</script>
