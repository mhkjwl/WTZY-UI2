<?php declare(strict_types=1);
/**
 * @file app/Views/admin/finance_recharge_settings.php
 * @description 后台：充值设置（档位、易支付在线渠道、人工入账渠道）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

use App\Core\View;
use App\Services\EpayService;

/**
 * @var array  $config
 * @var array  $gateway   易支付配置（不含密钥明文）
 * @var array  $epayTypes 易支付支付方式列表
 * @var array  $epayModes 支付发起方式列表
 * @var string $notifyUrl
 * @var string $returnUrl
 * @var array  $perms
 * @var string $csrf
 */
$onlineMethods = EpayService::CODE_TO_TYPE;
$gatewayReady = $config['gateway_ready'];
?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">充值金额</span><span class="rule"></span></h3>
    <div class="form-row">
        <div class="form-field" style="max-width:480px">
            <label class="form-label">预设档位（英文逗号分隔，最多 12 个）</label>
            <input class="form-input" id="rc-amounts" value="<?= View::e($config['amounts']) ?>" placeholder="100,200,500,1000,2000">
            <p class="form-hint">当前档位：<?= $config['amounts_list'] === [] ? '未配置' : implode(' / ', array_map(static fn(int $v): string => '¥' . number_format($v), $config['amounts_list'])) ?></p>
        </div>
        <div class="form-field" style="max-width:240px">
            <label class="form-label">单笔起充金额（元）</label>
            <input class="form-input" type="number" step="0.01" min="1" id="rc-min" value="<?= View::e($config['min_amount']) ?>">
            <p class="form-hint">用户提交充值单的最低金额，不得高于最低档位</p>
        </div>
        <div class="form-field" style="max-width:240px">
            <label class="form-label">充值单有效时长（分钟）</label>
            <input class="form-input" type="number" min="1" max="1440" id="rc-expire" value="<?= (int) $config['expire_minutes'] ?>">
            <p class="form-hint">仅对在线支付生效：超时未支付自动转为「已关闭」；银行转账（人工入账）不受此时长限制，会一直保持「待支付 / 待入账」直至客服核实，用户付款后到账不受影响</p>
        </div>
        <div class="form-field" style="max-width:240px">
            <label class="form-label">允许自定义金额</label>
            <select class="form-input" id="rc-custom">
                <option value="1" <?= $config['custom_enabled'] === '1' ? 'selected' : '' ?>>允许（用户可手动输入）</option>
                <option value="0" <?= $config['custom_enabled'] === '0' ? 'selected' : '' ?>>不允许（只能选档位）</option>
            </select>
            <p class="form-hint">关闭后用户只能从上方预设档位中选择金额</p>
        </div>
    </div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--purple">在线支付渠道（易支付）</span><span class="rule"></span>
        <span class="badge badge--<?= $gatewayReady ? 'green' : 'orange' ?>"><?= $gatewayReady ? '已配置可用' : '未配置' ?></span>
    </h3>
    <p class="form-hint" style="line-height:1.9;margin-bottom:12px">采用易支付 V2 接口（SHA256WithRSA 签名）；需在支付平台商户后台生成 RSA 密钥对并填写以下回调地址：
        <br>异步通知：<span class="mono"><?= View::e($notifyUrl) ?></span>
        <br>页面跳转：<span class="mono"><?= View::e($returnUrl) ?></span>
    </p>
    <div class="form-row">
        <div class="form-field" style="max-width:240px">
            <label class="form-label">启用易支付</label>
            <select class="form-input" id="ep-enabled">
                <option value="1" <?= $gateway['enabled'] ? 'selected' : '' ?>>启用（用户端可选在线支付）</option>
                <option value="0" <?= !$gateway['enabled'] ? 'selected' : '' ?>>关闭（仅保留人工入账）</option>
            </select>
        </div>
        <div class="form-field" style="max-width:180px">
            <label class="form-label">商户号 pid</label>
            <input class="form-input" id="ep-pid" value="<?= View::e($gateway['pid']) ?>" placeholder="支付平台分配">
        </div>
        <div class="form-field" style="max-width:360px">
            <label class="form-label">接口地址</label>
            <input class="form-input mono" id="ep-url" value="<?= View::e($gateway['api_url']) ?>" placeholder="https://pay.example.com">
            <p class="form-hint">填写你的支付平台提供的接口域名，留空即未配置</p>
        </div>
        <div class="form-field" style="max-width:360px">
            <label class="form-label">支付发起方式</label>
            <select class="form-input" id="ep-mode">
                <?php foreach ($epayModes as $code => $label): ?>
                    <option value="<?= View::e($code) ?>" <?= $gateway['mode'] === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="form-hint">扫码方式在电脑端展示二维码，移动端自动跳转支付页</p>
        </div>
    </div>
    <div class="form-row">
        <div class="form-field" style="max-width:560px">
            <label class="form-label">商户私钥（生成密钥对时保存的那一份）</label>
            <textarea class="form-input mono" id="ep-private" rows="4"
                      placeholder="<?= $gateway['has_private'] ? '已设置，留空表示不修改' : '粘贴商户私钥，可只粘贴 base64 内容，无需 BEGIN/END 头尾' ?>"></textarea>
            <p class="form-hint">用于请求签名，以 AES 加密后入库，界面上不回显，请妥善保管</p>
        </div>
        <div class="form-field" style="max-width:560px">
            <label class="form-label">平台公钥</label>
            <textarea class="form-input mono" id="ep-public" rows="4"
                      placeholder="粘贴平台公钥，用于校验网关回调与接口返回"><?= View::e($gateway['public_key']) ?></textarea>
            <p class="form-hint">用于验签；未配置时网关回调一律拒绝入账，务必填写正确</p>
        </div>
    </div>
    <div class="form-field">
        <label class="form-label">开放给用户的在线支付方式</label>
        <div class="adm-toolbar" style="margin-bottom:0">
            <?php foreach ($onlineMethods as $code => $type): ?>
                <label class="confirm-box__check" style="margin:0">
                    <input type="checkbox" class="rc-online" value="<?= (int) $code ?>" <?= in_array($code, $config['online_list'], true) ? 'checked' : '' ?>>
                    <?= View::e($epayTypes[$type] ?? $type) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="form-hint">勾选后用户端可选对应支付方式；未勾选的渠道不会出现在充值页</p>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--emerald">人工入账渠道</span><span class="rule"></span></h3>
    <div class="form-row">
        <div class="form-field" style="max-width:240px">
            <label class="form-label">人工入账</label>
            <select class="form-input" id="rc-manual">
                <option value="1" <?= $config['manual_enabled'] === '1' ? 'selected' : '' ?>>开启（用户可线下付款）</option>
                <option value="0" <?= $config['manual_enabled'] === '0' ? 'selected' : '' ?>>关闭</option>
            </select>
        </div>
    </div>
    <div class="form-field">
        <label class="form-label">收款信息（用户提交充值单后展示）</label>
        <textarea class="form-input" id="rc-manual-info" rows="6" placeholder="示例：&#10;银行：中国工商银行&#10;户名：某某科技有限公司&#10;账号：6222 0000 0000 0000&#10;转账后请点击「我已完成支付」，客服核实到账后 1 小时内入账。"><?= View::e($config['manual_info']) ?></textarea>
        <p class="form-hint">开启人工入账时必填；用户按此信息付款后点击「我已完成支付」，由管理员在财务中心确认入账。</p>
    </div>
</section>

<?php if (!empty($perms['finance.manage'])): ?>
<section class="panel">
    <div class="adm-toolbar" style="margin-bottom:0">
        <button class="btn btn--primary" type="button" onclick="saveRechargeSettings()">保存充值设置</button>
        <span class="form-hint" style="margin-left:12px">保存后用户端「余额充值」页立即生效</span>
    </div>
</section>
<?php endif; ?>

<script>
/** 汇总各区块表单值（多处保存按钮共用同一份参数） */
function collectRechargeSettings() {
    return {
        recharge_amounts: val('rc-amounts').trim(),
        min_recharge_amount: val('rc-min'),
        recharge_expire_minutes: val('rc-expire'),
        recharge_custom_enabled: val('rc-custom'),
        online_methods: Array.prototype.map.call(document.querySelectorAll('.rc-online:checked'), function (b) { return b.value; }),
        recharge_manual_enabled: val('rc-manual'),
        recharge_manual_info: val('rc-manual-info').trim(),
        epay_enabled: val('ep-enabled'),
        epay_pid: val('ep-pid').trim(),
        epay_api_url: val('ep-url').trim(),
        epay_pay_mode: val('ep-mode'),
        epay_private_key: val('ep-private').trim(),
        epay_public_key: val('ep-public').trim()
    };
}

function saveRechargeSettings() {
    var data = collectRechargeSettings();
    var minAmount = parseFloat(data.min_recharge_amount);
    var expire = parseInt(data.recharge_expire_minutes, 10);
    if (!(minAmount >= 1)) { Notify.warning('单笔起充金额需填写不小于 1 元的数字'); return; }
    if (!(expire >= 1)) { Notify.warning('充值单有效时长需填写不小于 1 分钟的整数'); return; }
    if (data.recharge_custom_enabled === '0' && !data.recharge_amounts) { Notify.warning('关闭自定义金额时，至少配置一个充值档位'); return; }
    if (data.online_methods.length === 0 && data.recharge_manual_enabled === '0') { Notify.warning('至少需要启用一种支付方式'); return; }
    if (data.recharge_manual_enabled === '1' && !data.recharge_manual_info) { Notify.warning('启用人工入账时请填写收款信息'); return; }
    if (data.epay_enabled === '1') {
        if (!data.epay_api_url) { Notify.warning('启用易支付时必须填写接口地址（由你的支付平台提供）'); return; }
        if (!/^https?:\/\/.+/.test(data.epay_api_url)) { Notify.warning('接口地址需以 http:// 或 https:// 开头'); return; }
        if (!/^\d+$/.test(data.epay_pid)) { Notify.warning('易支付商户号 pid 需为纯数字'); return; }
        if (data.online_methods.length === 0) { Notify.warning('启用易支付时至少勾选一种在线支付方式'); return; }
    } else if (data.online_methods.length > 0) {
        Notify.warning('在线支付依赖易支付网关，请先启用易支付或取消勾选');
        return;
    }

    admConfirm('确定保存充值设置吗？保存后用户端充值方式立即变更。', async function () {
        var res = await App.post('/admin/finance/recharge-settings/save', data);
        App.handle(res, '充值设置已保存');
        if (!res.success) { return; }
        // 密钥留空表示不修改，保存后清空输入框并改用「已设置」提示，避免误以为要重填
        var priv = document.getElementById('ep-private');
        if (priv && priv.value) { priv.value = ''; priv.placeholder = '已设置，留空表示不修改'; }
        var pub = document.getElementById('ep-public');
        if (pub && pub.value) { pub.value = ''; pub.placeholder = '已设置，留空表示不修改'; }
    }, { title: '保存充值设置', confirmText: '确认保存' });
}
</script>
