<?php declare(strict_types=1);
/**
 * @file app/Views/admin/users.php
 * @description 用户管理页：统计 + 检索工具栏 + 局部表 + 编辑（含登录凭据）/拉黑/风控/余额对话框
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var string $keyword
 * @var int $status
 * @var array $memberLevels
 * @var array $counts
 * @var array $perms
 * @var string $csrf
 */
$initialUrl = '/admin/users?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $page],
    static fn($v): bool => $v !== '' && $v !== -1
));
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">👥</div>
        <div><div class="astat__value"><?= (int) ($counts[1] ?? 0) + (int) ($counts[0] ?? 0) ?></div><div class="astat__label">正常 / 待验证</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) ($counts[0] ?? 0) ?></div><div class="astat__label">待验证</div></div></div>
    <div class="astat astat--red"><div class="astat__icon">🚫</div>
        <div><div class="astat__value"><?= (int) ($counts[2] ?? 0) ?></div><div class="astat__label">已禁用</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">⛔</div>
        <div><div class="astat__value"><?= (int) ($counts[3] ?? 0) ?></div><div class="astat__label">黑名单</div></div></div>
</section>

<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="u-kw" placeholder="编号 / 识别码 / 昵称 / 手机 / 邮箱" value="<?= View::e($keyword) ?>">
        <select class="form-input" id="u-status">
            <option value="-1">全部状态</option>
            <?php foreach (Status::USER as $code => $label): ?>
                <option value="<?= $code ?>" <?= $status === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--primary" onclick="admSearch('<?= View::e($base) ?>', {kw: val('u-kw'), status: val('u-status')})">检索</button>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('admin/users_table', get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-blacklist">
    <div class="adm-dialog__head">拉黑用户 · <span id="bl-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="bl-id">
        <div class="form-field"><label class="form-label">拉黑原因（必填，写入操作日志）</label>
            <input class="form-input" id="bl-reason" maxlength="200" placeholder="如：恶意索赔 / 拒付运费 / 冒用他人身份">
        </div>
        <p class="form-hint">拉黑为身份级封禁：账号立即下线，其手机号、邮箱与证件号无法再注册新账号。与「禁用」的区别是禁用只停用当前账号、随时可恢复。解除黑名单仅超级管理员可操作。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--danger" onclick="saveBlacklist()">确认拉黑</button>
    </div>
</dialog>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-risk">
    <div class="adm-dialog__head">风控等级设置 · <span id="risk-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="risk-id">
        <div class="form-field"><label class="form-label">等级</label>
            <select class="form-input" id="risk-level">
                <option value="0">0 正常（所有功能可用）</option>
                <option value="1">1 关注（敏感操作需复核）</option>
                <option value="2">2 限制（禁止下单/认领）</option>
            </select>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveRisk()">保存</button>
    </div>
</dialog>

<dialog class="adm-dialog" id="dlg-balance">
    <div class="adm-dialog__head">余额人工调整 · <span id="bal-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="bal-id">
        <div class="form-row">
            <div class="form-field"><label class="form-label">方向</label>
                <select class="form-input" id="bal-dir">
                    <option value="1">增加（入账）</option>
                    <option value="2">扣减（出账）</option>
                </select>
            </div>
            <div class="form-field"><label class="form-label">金额（元）</label>
                <input class="form-input" type="number" id="bal-amount" step="0.01" min="0.01"></div>
            <div class="form-field"><label class="form-label">调整原因（必填，写入资金流水）</label>
                <input class="form-input" id="bal-remark" placeholder="如：线下汇款补偿 / 仓储费修正"></div>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveBalance()">确认调整</button>
    </div>
</dialog>

<dialog class="adm-dialog adm-dialog--lg" id="dlg-user">
    <div class="adm-dialog__head">编辑用户 · <span id="ue-no-title"></span></div>
    <div class="adm-dialog__body">
        <p class="auth-notice" id="ue-blacklist-hint" hidden></p>
        <input type="hidden" id="ue-id">
        <div class="form-row">
            <div class="form-field"><label class="form-label">用户编号</label><input class="form-input" id="ue-no" readonly></div>
            <div class="form-field"><label class="form-label">用户名（登录账号）</label><input class="form-input" id="ue-username" maxlength="20" placeholder="4~20 位，字母开头" autocomplete="off"></div>
            <div class="form-field"><label class="form-label">入库识别码</label><input class="form-input mono" id="ue-code" readonly></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">昵称（选填）</label><input class="form-input" id="ue-nickname" maxlength="50" placeholder="留空表示不设置"></div>
            <div class="form-field"><label class="form-label">手机号</label><input class="form-input" id="ue-phone" maxlength="20" placeholder="留空表示未绑定"></div>
            <div class="form-field"><label class="form-label">邮箱</label><input class="form-input" id="ue-email" maxlength="128" placeholder="留空表示未绑定"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">会员等级</label>
                <select class="form-input" id="ue-level">
                    <?php foreach ($memberLevels as $ml): ?>
                        <option value="<?= (int) $ml['level'] ?>"><?= View::e($ml['name']) ?>（满 <?= (int) $ml['min_points'] ?> 分）</option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-field"><label class="form-label">账号状态</label>
                <select class="form-input" id="ue-status">
                    <?php foreach (Status::USER as $code => $label): ?>
                        <option value="<?= $code ?>"><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-field"><label class="form-label">风控等级</label>
                <select class="form-input" id="ue-risk">
                    <?php foreach (Status::RISK as $code => $label): ?>
                        <option value="<?= $code ?>"><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <p class="form-hint">手机号与邮箱同时是登录账号，修改后用户需用新账号登录。</p>

        <h4 class="section-head" style="margin-top:16px"><span class="badge badge--gray">登录凭据</span><span class="rule"></span></h4>
        <div class="form-row">
            <div class="form-field"><label class="form-label">新密码</label>
                <input class="form-input" type="password" id="ue-password" maxlength="64" placeholder="留空表示不修改" autocomplete="new-password"></div>
            <div class="form-field"><label class="form-label">确认新密码</label>
                <input class="form-input" type="password" id="ue-password2" maxlength="64" placeholder="与左侧一致" autocomplete="new-password"></div>
        </div>
        <p class="form-hint">用户名需 4~20 位、以字母开头、仅含字母/数字与下划线；密码至少 8 位且同时包含字母和数字。</p>
        <p class="form-hint">用户名与密码留空表示保持原值；两者任一变更后原凭据立即失效，请线下把新账号或新密码告知用户。</p>

        <h4 class="section-head" style="margin-top:16px"><span class="badge badge--gray">实名认证</span><span class="rule"></span></h4>
        <div class="form-row">
            <div class="form-field"><label class="form-label">实名状态</label>
                <select class="form-input" id="ue-kyc" onchange="syncKycFields()">
                    <?php foreach (Status::KYC as $code => $label): ?>
                        <option value="<?= $code ?>"><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-field"><label class="form-label">真实姓名</label><input class="form-input" id="ue-realname" maxlength="30"></div>
            <div class="form-field"><label class="form-label">证件号码</label><input class="form-input mono" id="ue-idcard" maxlength="20"></div>
        </div>
        <div class="form-field" id="ue-kyc-remark-box" hidden>
            <label class="form-label">驳回原因</label>
            <input class="form-input" id="ue-kycremark" maxlength="200" placeholder="驳回时必填，用户端会展示该原因">
        </div>
        <p class="form-hint">手动置为「待审核 / 已认证」时必须填写真实姓名与证件号码。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveUser()">保存</button>
    </div>
</dialog>

<script>
function openBlacklist(id, no) {
    document.getElementById('bl-id').value = id;
    document.getElementById('bl-no').textContent = no;
    document.getElementById('bl-reason').value = '';
    document.getElementById('dlg-blacklist').showModal();
}
function saveBlacklist() {
    var reason = val('bl-reason').trim();
    if (!reason) { Notify.warning('请填写拉黑原因，便于后续追溯与向用户解释'); return; }
    admConfirm('确定将用户列为黑名单吗？该账号立即下线，其手机号、邮箱与证件号会被识别为黑名单，无法用于新账号注册与实名；解除黑名单仅超级管理员可操作。', async function () {
        var res = await App.post('/admin/users/audit', { id: val('bl-id'), action: 'blacklist', reason: reason });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-blacklist').close(); admRefresh(); }
    }, { title: '拉黑用户', confirmText: '确认拉黑', level: 'L2' });
}
function openRisk(id, level, no) {
    document.getElementById('risk-id').value = id;
    document.getElementById('risk-level').value = String(level);
    document.getElementById('risk-no').textContent = no;
    document.getElementById('dlg-risk').showModal();
}
function saveRisk() {
    admConfirm('确定修改该用户的风控等级吗？等级变化会立即影响其下单与认领权限。', async function () {
        var res = await App.post('/admin/users/risk', { id: val('risk-id'), level: val('risk-level') });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-risk').close(); admRefresh(); }
    }, { title: '风控等级变更', confirmText: '确认修改' });
}
function openBalance(id, no) {
    document.getElementById('bal-id').value = id;
    document.getElementById('bal-no').textContent = no;
    document.getElementById('bal-amount').value = '';
    document.getElementById('bal-remark').value = '';
    document.getElementById('dlg-balance').showModal();
}
function saveBalance() {
    var amount = val('bal-amount');
    if (!(parseFloat(amount) > 0)) { Notify.warning('请填写大于 0 的调整金额'); return; }
    if (!val('bal-remark').trim()) { Notify.warning('请填写调整原因，便于对账追溯'); return; }
    admConfirm('确定按 ¥' + amount + ' 调整该用户余额吗？该操作会生成资金流水且不可撤销。', async function () {
        var res = await App.post('/admin/users/balance', {
            id: val('bal-id'), direction: val('bal-dir'),
            amount: amount, remark: val('bal-remark')
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-balance').close(); admRefresh(); }
    }, { title: '余额人工调整', confirmText: '确认调整', danger: true });
}
/** 弹窗打开时的登录账号原值，用于判断本次是否真的改动了凭据（决定确认等级） */
var ueOriginUsername = '';
async function openUser(id) {
    var res = await App.get('/admin/users/' + id);
    if (!res.success) { Notify.error(res.message || '加载用户信息失败'); return; }
    var u = res.data;
    document.getElementById('ue-id').value = u.id;
    document.getElementById('ue-no-title').textContent = u.user_no;
    document.getElementById('ue-no').value = u.user_no || '';
    document.getElementById('ue-username').value = u.username || '';
    ueOriginUsername = u.username || '';
    document.getElementById('ue-code').value = u.user_code || '';
    document.getElementById('ue-nickname').value = u.nickname || '';
    document.getElementById('ue-phone').value = u.phone || '';
    document.getElementById('ue-email').value = u.email || '';
    document.getElementById('ue-level').value = String(u.member_level);
    document.getElementById('ue-status').value = String(u.status);
    document.getElementById('ue-risk').value = String(u.risk_level);
    document.getElementById('ue-kyc').value = String(u.kyc_status || 0);
    document.getElementById('ue-realname').value = u.real_name || '';
    document.getElementById('ue-idcard').value = u.id_card_no || '';
    document.getElementById('ue-kycremark').value = u.kyc_remark || '';
    document.getElementById('ue-password').value = '';
    document.getElementById('ue-password2').value = '';
    // 黑名单用户的状态在此处只读展示：改状态会绕过原因必填与超管门槛，故引导到列表按钮
    var blHint = document.getElementById('ue-blacklist-hint');
    if (String(u.status) === '3') {
        blHint.textContent = '该用户处于黑名单状态'
            + (u.blacklisted_at ? '（' + u.blacklisted_at + ' 拉黑）' : '')
            + '，原因：' + (u.blacklist_reason || '未填写')
            + '。如需解除，请在列表中由超级管理员执行「解除黑名单」。';
        blHint.hidden = false;
    } else {
        blHint.hidden = true;
    }
    syncKycFields();
    document.getElementById('dlg-user').showModal();
}
function syncKycFields() {
    var kyc = val('ue-kyc');
    document.getElementById('ue-kyc-remark-box').hidden = kyc !== '3';
}

function saveUser() {
    var nickname = val('ue-nickname').trim();
    var username = val('ue-username').trim();
    var pwd = val('ue-password');
    var pwd2 = val('ue-password2');
    var label = username || val('ue-no').trim();
    var kyc = val('ue-kyc');
    if ((kyc === '1' || kyc === '2') && (!val('ue-realname').trim() || !val('ue-idcard').trim())) {
        Notify.warning('手动认证需填写真实姓名与证件号码');
        return;
    }
    if (kyc === '3' && !val('ue-kycremark').trim()) {
        Notify.warning('标记为已驳回时请填写驳回原因');
        return;
    }
    // 前端提示仅为降低无效请求，最终校验以服务端为准
    if (pwd && pwd !== pwd2) {
        Notify.warning('两次输入的密码不一致');
        return;
    }
    // 黑名单必须是填写原因并校验权限的独立动作，表单里改不出来（服务端同样拒绝）
    if (val('ue-status') === '3') {
        Notify.warning('黑名单请使用列表中的「拉黑」按钮操作，需填写拉黑原因');
        return;
    }
    var credentialChanged = (username !== '' && username !== ueOriginUsername) || pwd !== '';
    var message = credentialChanged
        ? '确定保存对该用户的修改吗？登录账号或密码变更后原凭据立即失效，用户需使用新凭据登录。'
        : '确定保存用户「' + label + '」的资料与权限设置吗？';
    admConfirm(message, async function () {
        var res = await App.post('/admin/users/save', {
            id: val('ue-id'), nickname: nickname,
            username: username, password: pwd, password_confirm: pwd2,
            phone: val('ue-phone').trim(), email: val('ue-email').trim(),
            member_level: val('ue-level'), status: val('ue-status'), risk_level: val('ue-risk'),
            kyc_status: val('ue-kyc'), real_name: val('ue-realname').trim(),
            id_card_no: val('ue-idcard').trim(), kyc_remark: val('ue-kycremark').trim()
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-user').close(); admRefresh(); }
    }, { title: '保存用户信息', confirmText: '确认保存', level: credentialChanged ? 'L2' : 'L1' });
}

document.getElementById('u-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { admSearch('/admin/users', { kw: this.value, status: val('u-status') }); }
});
</script>
