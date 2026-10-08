<?php declare(strict_types=1);
/**
 * @file app/Views/pay/result.php
 * @description 支付回跳结果页：只读展示订单状态，并提供走 POST 的主动查询入口
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var bool   $paid
 * @var string $orderNo
 * @var int    $orderId
 * @var string $statusText
 * @var string $message
 */
?>
<section class="panel" style="max-width:620px;margin:48px auto">
    <div style="text-align:center;padding:12px 0 4px">
        <div style="font-size:44px" id="pay-icon"><?= $paid ? '✅' : '⏳' ?></div>
        <h3 style="margin:12px 0 6px" id="pay-heading"><?= $paid ? '支付成功' : '支付结果待确认' ?></h3>
        <p class="form-hint" style="line-height:1.9" id="pay-message"><?= View::e($message) ?></p>
        <?php if ($orderNo !== ''): ?>
            <p class="form-hint">充值单号：<span class="mono"><?= View::e($orderNo) ?></span></p>
        <?php endif; ?>
    </div>
    <div class="adm-toolbar" style="justify-content:center;margin-bottom:0">
        <?php if ($orderId > 0 && !$paid): ?>
            <button type="button" class="btn btn--primary" id="pay-refresh" onclick="refreshPayStatus()">刷新支付状态</button>
        <?php endif; ?>
        <a class="btn <?= ($orderId > 0 && !$paid) ? 'btn--ghost' : 'btn--primary' ?>" href="/user/finance/recharge">返回充值记录</a>
        <a class="btn btn--ghost" href="/user/finance">查看余额</a>
    </div>
    <p class="form-hint" style="text-align:center;margin-top:14px">
        若已扣款但余额未变，请稍等 1~2 分钟；仍未到账请联系客服并提供充值单号。
    </p>
</section>
<?php if ($orderId > 0 && !$paid): ?>
<script>
var PAY_ORDER_ID = <?= (int) $orderId ?>;

/**
 * 主动查询支付状态：走 POST（规范 §5.5.3 禁止 GET 触发写操作），成功后局部更新本页
 */
async function refreshPayStatus() {
    var btn = document.getElementById('pay-refresh');
    btn.disabled = true;
    var res = await App.post('/user/finance/recharge/status', { id: PAY_ORDER_ID, sync: 1 });
    btn.disabled = false;

    if (!res.success) {
        Notify.error(res.message || '暂时无法查询，请稍后重试');
        return;
    }
    var st = res.data || {};
    document.getElementById('pay-message').textContent = st.paid
        ? '支付成功，余额已入账。'
        : '当前状态：' + (st.text || '未知') + '。若已扣款请稍后重试或联系客服。';
    if (st.paid) {
        document.getElementById('pay-icon').textContent = '✅';
        document.getElementById('pay-heading').textContent = '支付成功';
        btn.remove();
        Notify.success('支付成功，余额已入账');
    } else {
        Notify.info('当前状态：' + (st.text || '待确认'));
    }
}
</script>
<?php endif; ?>
