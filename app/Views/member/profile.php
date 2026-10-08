<?php declare(strict_types=1);
/**
 * @file app/Views/member/profile.php
 * @description 会员中心：个人资料 / 登录绑定（绑定邮箱） / 修改密码
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

use App\Core\View;
use App\Models\UserModel;

/**
 * @var array $user
 * @var array $bindings
 * @var array $account
 */
$kycMap = [0 => ['未提交', 'gray'], 1 => ['待审核', 'orange'], 2 => ['已通过', 'green'], 3 => ['已驳回', 'red']];
$kyc = $kycMap[(int) $user['kyc_status']] ?? ['未知', 'gray'];
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">💰</div>
        <div><div class="astat__value">¥<?= number_format((float) $account['balance'], 2) ?></div><div class="astat__label">账户余额</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">⬆️</div>
        <div><div class="astat__value">¥<?= number_format((float) $account['total_recharge'], 2) ?></div><div class="astat__label">累计充值</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⬇️</div>
        <div><div class="astat__value">¥<?= number_format((float) $account['total_consume'], 2) ?></div><div class="astat__label">累计消费</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">⭐</div>
        <div><div class="astat__value"><?= (int) $user['points'] ?></div><div class="astat__label">可用积分</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">基本资料</span><span class="rule"></span></h3>
    <div class="form-row">
        <div class="form-field"><label class="form-label">用户编号</label><input class="form-input" value="<?= View::e($user['user_no']) ?>" readonly></div>
        <div class="form-field"><label class="form-label">入库识别码</label><input class="form-input mono" value="<?= View::e($user['user_code']) ?>" readonly></div>
        <div class="form-field"><label class="form-label">会员等级</label><input class="form-input" value="<?= View::e($user['level_name']) ?>" readonly></div>
    </div>
    <div class="form-row">
        <div class="form-field"><label class="form-label">用户名</label><input class="form-input" value="<?= View::e((string) ($user['username'] ?? '未设置')) ?>" readonly></div>
        <div class="form-field"><label class="form-label">实名状态</label>
            <div style="padding-top:8px">
                <span class="badge badge--<?= $kyc[1] ?>"><?= View::e($kyc[0]) ?></span>
                <?php if ((int) $user['kyc_status'] === 2): ?>
                    <span style="margin-left:10px"><?= View::e((string) ($user['real_name'] ?? '')) ?></span>
                    <span class="form-hint" style="margin-left:8px"><?= View::e([1 => '身份证', 2 => '护照'][(int) ($user['id_type'] ?? 1)] ?? '身份证') ?>
                        <?= View::e(UserModel::maskIdNo((string) ($user['id_card_no'] ?? ''))) ?></span>
                <?php else: ?>
                    <a class="auth-link-mini" style="display:inline-block;margin-left:10px" href="/user/kyc">前往认证</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="form-field">
        <label class="form-label" for="pf-nickname">昵称 <span class="form-optional">（选填）</span></label>
        <div class="captcha-row">
            <input class="form-input" id="pf-nickname" maxlength="50" value="<?= View::e($user['nickname']) ?>">
            <button class="btn btn--primary" type="button" onclick="saveProfile()">保存昵称</button>
        </div>
        <p class="form-hint">昵称可留空。</p>
    </div>
    <p class="form-hint">用户名注册后不可修改；手机号与邮箱在下方登录绑定维护。</p>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--purple">登录绑定</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>登录方式</th><th>账号</th><th>状态</th><th class="nowrap">绑定时间</th></tr></thead>
            <tbody id="bind-tbody">
            <?php if ($bindings === []): ?>
                <tr><td colspan="4"><div class="empty-state">暂无登录绑定记录。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($bindings as $b): ?>
                <tr>
                    <td><?= [1 => '手机号', 2 => '邮箱', 3 => '微信', 4 => 'Google', 5 => 'Apple'][(int) $b['auth_type']] ?? '其他' ?></td>
                    <td class="mono"><?= View::e($b['identifier']) ?></td>
                    <td><span class="badge badge--<?= (int) $b['is_verified'] === 1 ? 'green' : 'gray' ?>"><?= (int) $b['is_verified'] === 1 ? '已验证' : '未验证' ?></span></td>
                    <td class="nowrap"><?= View::e(substr((string) $b['created_at'], 0, 10)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php $boundEmail = (string) ($user['email'] ?? ''); ?>
    <?php if ($boundEmail !== ''): ?>
        <div class="adm-toolbar" style="margin-bottom:0">
            <span class="badge badge--green">✅ 已绑定邮箱：<?= View::e($boundEmail) ?></span>
            <span class="form-hint" style="margin-left:12px">绑定后不支持解绑或更换；如需变更邮箱，请联系客服处理。</span>
        </div>
    <?php else: ?>
        <div id="bind-form-wrap">
            <h4 style="font-size:14px;margin:16px 0 10px">绑定邮箱</h4>
            <div class="form-row">
                <div class="form-field">
                    <label class="form-label" for="be-email">邮箱地址</label>
                    <input class="form-input" type="email" id="be-email" maxlength="128" placeholder="用于登录与接收通知">
                </div>
                <div class="form-field">
                    <label class="form-label" for="be-code">邮箱验证码</label>
                    <div class="captcha-row">
                        <input class="form-input" type="text" id="be-code" maxlength="6" inputmode="numeric" placeholder="6 位数字">
                        <button class="btn btn--ghost" type="button" id="be-send" onclick="sendBindCode()">发送验证码</button>
                    </div>
                </div>
            </div>
            <p class="form-hint">验证码 10 分钟内有效。绑定后不支持解绑或更换；如需变更邮箱，请联系客服处理。</p>
            <button class="btn btn--primary" type="button" onclick="submitBindEmail()">绑定邮箱</button>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--red">修改密码</span><span class="rule"></span></h3>
    <div class="form-row">
        <div class="form-field"><label class="form-label">原密码</label><input class="form-input" type="password" id="pw-old" autocomplete="current-password"></div>
        <div class="form-field"><label class="form-label">新密码</label><input class="form-input" type="password" id="pw-new" autocomplete="new-password"></div>
        <div class="form-field"><label class="form-label">确认新密码</label><input class="form-input" type="password" id="pw-confirm" autocomplete="new-password"></div>
    </div>
    <p class="form-hint">至少 8 位，需同时包含字母和数字；修改后其他设备需重新登录。</p>
    <button class="btn btn--primary" type="button" onclick="savePassword()">更新密码</button>
</section>

<script>
function saveProfile() {
    var nickname = val('pf-nickname').trim();
    admConfirm('确定保存昵称' + (nickname ? '「' + nickname + '」' : '（留空表示不设置）') + '吗？', async function () {
        var res = await App.post('/user/profile/save', { nickname: nickname });
        App.handle(res, '昵称已更新');
    }, { title: '保存昵称', confirmText: '确认保存' });
}

function savePassword() {
    if (!val('pw-old')) { Notify.warning('请填写原密码'); return; }
    if (!val('pw-new')) { Notify.warning('请填写新密码'); return; }
    admConfirm('确定修改登录密码吗？修改后其他设备需重新登录。', async function () {
        var res = await App.post('/user/profile/password', {
            old_password: val('pw-old'), new_password: val('pw-new'), new_password_confirm: val('pw-confirm')
        });
        App.handle(res, '密码修改成功');
        if (res.success) {
            document.getElementById('pw-old').value = '';
            document.getElementById('pw-new').value = '';
            document.getElementById('pw-confirm').value = '';
        }
    }, { title: '修改登录密码', confirmText: '确认修改', danger: true });
}

var bindSendBtn = document.getElementById('be-send');
var bindTimer = null;
function sendBindCode() {
    var email = val('be-email').trim();
    if (!email || email.indexOf('@') === -1) { Notify.warning('请先填写正确的邮箱地址'); return; }
    bindSendBtn.disabled = true;
    App.post('/user/profile/send-bind-code', { email: email }).then(function (res) {
        if (!res.success) {
            Notify.error(res.message || '验证码发送失败');
            bindSendBtn.disabled = false;
            return;
        }
        Notify.success(res.message || '验证码已发送');
        var left = 60;
        bindSendBtn.textContent = left + ' 秒后重发';
        bindTimer = setInterval(function () {
            left--;
            if (left <= 0) {
                clearInterval(bindTimer);
                bindSendBtn.disabled = false;
                bindSendBtn.textContent = '发送验证码';
                return;
            }
            bindSendBtn.textContent = left + ' 秒后重发';
        }, 1000);
    }).catch(function () {
        Notify.error('网络异常，请稍后重试');
        bindSendBtn.disabled = false;
    });
}

function submitBindEmail() {
    var email = val('be-email').trim();
    var code = val('be-code').trim();
    if (!email || email.indexOf('@') === -1) { Notify.warning('请填写正确的邮箱地址'); return; }
    if (!code) { Notify.warning('请填写邮箱验证码'); return; }
    admConfirm('确定绑定邮箱 ' + email + ' 吗？绑定后不支持解绑或更换。', async function () {
        var res = await App.post('/user/profile/bind-email', { email: email, code: code });
        App.handle(res, '邮箱绑定成功');
        if (!res.success) { return; }
        // 局部更新：表单区替换为已绑定提示，绑定表插入邮箱行（规范 §2.5 不整页刷新）
        var wrap = document.getElementById('bind-form-wrap');
        if (wrap) {
            var done = document.createElement('div');
            done.className = 'adm-toolbar';
            done.style.marginBottom = '0';
            done.innerHTML = '<span class="badge badge--green">✅ 已绑定邮箱：' + email + '</span>'
                + '<span class="form-hint" style="margin-left:12px">绑定后不支持解绑或更换；如需变更邮箱，请联系客服处理。</span>';
            wrap.replaceWith(done);
        }
        var tbody = document.getElementById('bind-tbody');
        if (tbody) {
            var empty = tbody.querySelector('.empty-state');
            if (empty && empty.closest('tr')) { empty.closest('tr').remove(); }
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>邮箱</td><td class="mono">' + email + '</td>'
                + '<td><span class="badge badge--green">已验证</span></td><td class="nowrap">刚刚</td>';
            tbody.appendChild(tr);
        }
    }, { title: '绑定邮箱', confirmText: '确认绑定' });
}
</script>
