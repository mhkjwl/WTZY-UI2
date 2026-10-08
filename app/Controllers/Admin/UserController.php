<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/UserController.php
 * @description 用户管理：列表检索 / 资料与权益编辑（含登录凭据）/ 启用禁用 / 风控设置 / 余额调整（各操作独立权限点）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\StaffAuth;
use App\Enums\Status;
use App\Models\FundFlowModel;
use App\Models\MemberLevelModel;
use App\Models\UserAuthModel;
use App\Models\UserModel;
use InvalidArgumentException;
use RuntimeException;

final class UserController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('user.view');
        $this->renderList((string) Request::query('kw', ''), (int) Request::query('status', '-1'));
    }

    /**
     * 检索/分页/操作后均局部刷新表格区
     */
    private function renderList(string $keyword, int $status): void
    {
        $page = $this->pageParams();
        $result = UserModel::search($keyword, $status, $page['limit'], $page['offset']);
        $data = [
            'title'     => '用户管理',
            'rows'      => $result['rows'],
            'total'     => $result['total'],
            'page'      => $page['page'],
            'pages'     => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword'   => $keyword,
            'status'    => $status,
            'memberLevels' => MemberLevelModel::all(),
            'counts'    => UserModel::statusCounts(),
            'base'      => '/admin/users',
        ];
        $isPartial = Request::header('X-Requested-With') === 'fetch';
        if ($isPartial) {
            $this->renderTable('admin/users_table', $data);
        }
        $this->adminView('admin/users', $data, 'users', '用户管理');
    }

    /**
     * 实名认证审核列表（默认只看待审核）
     */
    public function kyc(): never
    {
        $this->requireStaff();
        $this->enforce('user.kyc');
        $this->renderKycList((string) Request::query('kw', ''), (int) Request::query('status', '1'));
    }

    private function renderKycList(string $keyword, int $status): void
    {
        $page = $this->pageParams();
        $result = UserModel::kycSearch($keyword, $status, $page['limit'], $page['offset']);
        $data = [
            'title'   => '实名审核',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page['page'],
            'pages'   => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword' => $keyword,
            'status'  => $status,
            'counts'  => UserModel::kycCounts(),
            'base'    => '/admin/users/kyc',
        ];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/users_kyc_table', $data);
        }
        $this->adminView('admin/users_kyc', $data, 'kyc', '实名审核');
    }

    /**
     * 实名审核：通过或驳回（驳回需填写原因）
     */
    public function reviewKyc(): never
    {
        $this->requireStaff();
        $this->enforce('user.kyc');
        $this->verifyCsrf();

        $id = (int) Request::input('id', 0);
        $action = (string) Request::input('action', '');
        $remark = trim((string) Request::input('remark', ''));

        $user = UserModel::findDetail($id);
        if ($user === null) {
            $this->fail('用户不存在');
        }
        if ((int) $user['kyc_status'] !== 1) {
            $this->fail('该用户当前没有待审核的实名申请');
        }
        if (!in_array($action, ['approve', 'reject'], true)) {
            $this->fail('无效的审核操作');
        }
        if ($action === 'reject' && mb_strlen($remark) < 2) {
            $this->fail('驳回时必须填写原因，便于用户修正后重新提交');
        }
        // 审核通过前二次校验证件占用：提交时预校验与此刻之间可能被其他账号抢先提交同一证件
        if ($action === 'approve' && UserModel::kycOccupied((int) $user['id_type'], (string) $user['id_card_no'], $id)) {
            $this->fail('该证件号已被其他账号绑定，无法通过审核；请联系用户核实后驳回');
        }

        UserModel::reviewKyc($id, $action === 'approve' ? 2 : 3, $action === 'approve' ? '' : $remark);
        $this->ok(null, $action === 'approve' ? '实名认证已通过' : '实名认证已驳回');
    }

    /**
     * 用户详情（编辑弹窗回填 + 只读查看）
     */
    public function detail(string $id): never
    {
        $this->requireStaff();
        $this->enforce('user.view');
        $user = UserModel::findDetail((int) $id);
        if ($user === null) {
            $this->fail('用户不存在', 404);
        }
        $this->ok($user);
    }

    /**
     * 编辑用户资料与权益：昵称/联系方式/会员等级/账号状态/风控等级
     * 登录校验以 user_auths 为准，手机或邮箱变更时必须同步绑定，否则新账号无法登录
     * 登录凭据（用户名/密码）同窗提交，均留空表示保持原值，只处理实际变动项
     */
    public function save(): never
    {
        $this->requireStaff();
        $this->enforce('user.edit');
        $this->verifyCsrf();

        $id = (int) Request::input('id', 0);
        $user = UserModel::findDetail($id);
        if ($user === null) {
            $this->fail('用户不存在');
        }

        $nickname = trim((string) Request::input('nickname', ''));
        $phone = trim((string) Request::input('phone', ''));
        $email = trim((string) Request::input('email', ''));
        $memberLevel = (int) Request::input('member_level', 0);
        $status = (int) Request::input('status', 1);
        $riskLevel = (int) Request::input('risk_level', 0);
        $kycStatus = (int) Request::input('kyc_status', 0);
        $realName = trim((string) Request::input('real_name', ''));
        $idCard = strtoupper(trim((string) Request::input('id_card_no', '')));
        $kycRemark = trim((string) Request::input('kyc_remark', ''));
        $username = trim((string) Request::input('username', ''));
        $password = (string) Request::input('password', '');
        $passwordConfirm = (string) Request::input('password_confirm', '');

        $currentUsername = (string) ($user['username'] ?? '');
        $changeUsername = $username !== '' && $username !== $currentUsername;
        $changePassword = $password !== '';

        // 昵称非必填，留空表示不设置
        if (mb_strlen($nickname) > 50) {
            $this->fail('昵称不超过 50 字');
        }
        if ($phone !== '' && preg_match('/^[0-9+\- ]{6,20}$/', $phone) !== 1) {
            $this->fail('手机号格式不正确');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail('邮箱格式不正确');
        }
        if (MemberLevelModel::find($memberLevel) === null) {
            $this->fail('无效的会员等级');
        }
        if (!array_key_exists($status, Status::USER)) {
            $this->fail('无效的账号状态');
        }
        // 黑名单是身份级封禁，必须填写原因且解除只对超管开放，只能走列表上的专用入口；
        // 若在这里放开，普通管理员就能绕过原因留痕与超管门槛把封禁抹掉
        if ($status === Status::USER_BLACKLIST || (int) $user['status'] === Status::USER_BLACKLIST) {
            $this->fail('黑名单状态请使用列表中的「拉黑 / 解除黑名单」操作，以便记录原因并校验权限');
        }
        if (!array_key_exists($riskLevel, Status::RISK)) {
            $this->fail('无效的风控等级');
        }
        if (!array_key_exists($kycStatus, Status::KYC)) {
            $this->fail('无效的实名状态');
        }
        // 手动置为已通过/待审核时必须补齐姓名与证件号，否则实名信息不完整
        if (in_array($kycStatus, [1, 2], true)) {
            if ($realName === '' || mb_strlen($realName) < 2 || mb_strlen($realName) > 30) {
                $this->fail('手动认证需填写真实姓名（2~30 字）');
            }
            if (preg_match('/^[A-Z0-9]{6,20}$/', $idCard) !== 1) {
                $this->fail('证件号码格式不正确（6~20 位字母或数字）');
            }
        }
        if ($kycStatus === 3 && $kycRemark === '') {
            $this->fail('标记为已驳回时请填写驳回原因');
        }
        // 用户名同时是登录标识，规则与注册保持一致，避免生成前台无法注册、无法找回密码的账号
        if ($changeUsername) {
            if (preg_match('/^[A-Za-z][A-Za-z0-9_]{3,19}$/', $username) !== 1) {
                $this->fail('用户名需 4~20 位，以字母开头，仅含字母、数字与下划线');
            }
            $occupied = UserModel::findByUsername($username);
            if ($occupied !== null && (int) $occupied['id'] !== $id) {
                $this->fail('该用户名已被占用，请换一个');
            }
        }
        if ($changePassword) {
            if (strlen($password) < 8 || preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
                $this->fail('密码至少 8 位，且需同时包含字母和数字');
            }
            if ($password !== $passwordConfirm) {
                $this->fail('两次输入的密码不一致');
            }
        }

        $authSync = [];
        if ($phone !== (string) ($user['phone'] ?? '')) {
            $this->assertIdentifierFree($phone, Status::AUTH_PHONE, $id, '手机号');
            $authSync[] = [Status::AUTH_PHONE, $phone];
        }
        if ($email !== (string) ($user['email'] ?? '')) {
            $this->assertIdentifierFree($email, Status::AUTH_EMAIL, $id, '邮箱');
            $authSync[] = [Status::AUTH_EMAIL, $email];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            UserModel::updateProfile($id, [
                'nickname'     => $nickname,
                'phone'        => $phone,
                'email'        => $email,
                'member_level' => $memberLevel,
                'status'       => $status,
                'risk_level'   => $riskLevel,
                'kyc_status'   => $kycStatus,
                'kyc_remark'   => $kycRemark,
            ]);
            // 手动认证时一并写入姓名与证件号
            if ($realName !== '' && $idCard !== '') {
                Database::pdo()->prepare(
                    'UPDATE ' . Database::table('users') . ' SET real_name = :n, id_card_no = :c, kyc_at = NOW() WHERE id = :id'
                )->execute([':n' => $realName, ':c' => $idCard, ':id' => $id]);
            }
            foreach ($authSync as [$authType, $identifier]) {
                $this->syncAuthIdentifier($id, $authType, $identifier);
            }
            if ($changeUsername) {
                UserModel::updateUsername($id, $username);
            }
            if ($changePassword) {
                UserModel::updatePassword($id, password_hash($password, PASSWORD_DEFAULT));
            }
            // 登录凭据变更属安全敏感操作，必须留痕；日志与业务变更同事务，日志失败则整体回滚
            if ($changeUsername || $changePassword) {
                $this->logCredentialChange(
                    $id,
                    $currentUsername !== '' ? $currentUsername : null,
                    $changeUsername ? $username : ($currentUsername !== '' ? $currentUsername : null),
                    $changePassword
                );
            }
            // 客服/管理员协助修改邮箱必须留痕：写入员工操作日志（与业务变更同事务，日志失败则整体回滚）
            if ($email !== (string) ($user['email'] ?? '')) {
                $staff = StaffAuth::staff() ?? [];
                Database::pdo()->prepare(
                    'INSERT INTO ' . Database::table('admin_operation_logs') . '
                     (admin_id, admin_name, module, action, target_type, target_id, before_data, after_data, ip)
                     VALUES (:aid, :aname, :module, :action, :ttype, :tid, :bdata, :adata, :ip)'
                )->execute([
                    ':aid' => (int) (StaffAuth::id() ?? 0),
                    // 员工资料里姓名字段为 real_name，日志需记录操作人姓名快照以便追溯
                    ':aname' => (string) ($staff['real_name'] ?? $staff['name'] ?? '') ?: '—',
                    ':module' => 'user',
                    ':action' => $email === '' ? 'unbind_email' : 'change_email',
                    ':ttype' => 'user',
                    ':tid' => $id,
                    ':bdata' => json_encode(['email' => (string) ($user['email'] ?? '')], JSON_UNESCAPED_UNICODE),
                    ':adata' => json_encode(['email' => $email], JSON_UNESCAPED_UNICODE),
                    ':ip' => Request::ip(),
                ]);
            }
            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $this->fail('保存失败，手机号、邮箱或用户名可能已被占用');
        }

        if (!$changeUsername && !$changePassword) {
            $this->ok(null, '用户信息已更新');
        }
        $changed = [];
        if ($changeUsername) {
            $changed[] = '登录账号';
        }
        if ($changePassword) {
            $changed[] = '登录密码';
        }
        $this->ok(null, '用户信息已更新，' . implode('与', $changed) . '已变更，原凭据立即失效，请线下告知用户');
    }

    /**
     * 登录标识唯一性校验：同时排除其他用户已绑定该标识的情况
     */
    private function assertIdentifierFree(string $identifier, int $authType, int $userId, string $label): void
    {
        if ($identifier === '') {
            return;
        }
        $existing = UserAuthModel::findByIdentifier($authType, $identifier);
        if ($existing !== null && (int) $existing['user_id'] !== $userId) {
            $this->fail($label . '已被其他用户使用');
        }
    }

    /**
     * 同步登录绑定：已有该方式的更新标识，没有则新建，保证用户可用新账号登录
     */
    private function syncAuthIdentifier(int $userId, int $authType, string $identifier): void
    {
        $bound = UserAuthModel::findByUser($userId, $authType);
        if ($bound === null) {
            if ($identifier !== '') {
                UserAuthModel::create($userId, $authType, $identifier, 1);
            }
            return;
        }
        if ($identifier === '') {
            return;
        }
        UserAuthModel::updateIdentifier((int) $bound['id'], $identifier);
    }

    /**
     * 凭据变更留痕：只记录用户名前后值与是否重置密码，密码内容（含哈希）一律不入日志
     */
    private function logCredentialChange(int $userId, ?string $beforeUsername, ?string $afterUsername, bool $passwordReset): void
    {
        $staff = StaffAuth::staff() ?? [];
        Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('admin_operation_logs') . '
             (admin_id, admin_name, module, action, target_type, target_id, before_data, after_data, ip)
             VALUES (:aid, :aname, :module, :action, :ttype, :tid, :bdata, :adata, :ip)'
        )->execute([
            ':aid' => (int) (StaffAuth::id() ?? 0),
            // 员工资料里姓名字段为 real_name，日志需记录操作人姓名快照以便追溯
            ':aname' => (string) ($staff['real_name'] ?? $staff['name'] ?? '') ?: '—',
            ':module' => 'user',
            ':action' => 'change_credential',
            ':ttype' => 'user',
            ':tid' => $userId,
            ':bdata' => json_encode(['username' => $beforeUsername], JSON_UNESCAPED_UNICODE),
            ':adata' => json_encode(['username' => $afterUsername, 'password_reset' => $passwordReset], JSON_UNESCAPED_UNICODE),
            ':ip' => Request::ip(),
        ]);
    }

    /**
     * 账号状态变更：启用 / 禁用 / 拉黑 / 解除黑名单
     * 状态决定该账号能否登录、在途会话是否有效，属敏感操作，与业务变更同事务写入审计日志。
     * 禁用与拉黑并非同一件事的两种叫法：禁用只停用当前账号，随时可恢复；拉黑是身份级封禁，
     * 必须说明原因，且该手机号/邮箱/证件号无法再注册新账号，解除只能由超级管理员执行
     */
    public function audit(): never
    {
        $this->requireStaff();
        $this->enforce('user.audit');
        $this->verifyCsrf();

        $id = (int) Request::input('id', 0);
        $action = (string) Request::input('action', '');
        $reason = trim((string) Request::input('reason', ''));

        $statusMap = [
            'enable'    => Status::USER_ACTIVE,
            'disable'   => Status::USER_DISABLED,
            'blacklist' => Status::USER_BLACKLIST,
        ];
        if ($action === 'unblacklist') {
            // 解除封禁是黑名单链路中唯一可以「撤销处置」的动作，只对超级管理员开放，
            // 避免普通管理员与被拉黑用户私下沟通后就把封禁抹掉
            if (!StaffAuth::isSuperAdmin()) {
                $this->fail('解除黑名单仅超级管理员可操作', 403);
            }
            $after = Status::USER_ACTIVE;
        } elseif (isset($statusMap[$action])) {
            $after = $statusMap[$action];
        } else {
            $this->fail('无效的操作类型');
        }
        if ($action === 'blacklist') {
            if ($reason === '') {
                $this->fail('请填写拉黑原因，便于后续追溯与向用户解释处置结果');
            }
            if (mb_strlen($reason) > 200) {
                $this->fail('拉黑原因不超过 200 字');
            }
        }

        $user = UserModel::findWithAccount($id);
        if ($user === null || $user['deleted_at'] !== null) {
            $this->fail('用户不存在');
        }
        $before = (int) $user['status'];
        if ($before === $after) {
            $this->fail('该用户当前已是「' . Status::user($after) . '」状态，无需重复操作');
        }
        // 黑名单只能由超管走「解除黑名单」退出：若允许普通管理员直接改回禁用或正常，
        // 相当于绕过上面的超管门槛，封禁形同虚设
        if ($before === Status::USER_BLACKLIST && $action !== 'unblacklist') {
            $this->fail('该用户当前为黑名单状态，请由超级管理员执行「解除黑名单」后再变更状态');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            UserModel::updateStatus($id, $after, $action === 'blacklist' ? $reason : null);
            $this->logStatusChange($id, (string) $user['user_no'], $before, $after, $reason, (string) ($user['blacklist_reason'] ?? ''));
            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $this->fail('状态更新失败，请稍后重试');
        }

        $this->ok(null, $this->statusChangeMessage($after, $action));
    }

    /**
     * 状态变更结果提示：停用类状态需说明在途会话已失效，避免客服以为用户要重新登录是异常
     */
    private function statusChangeMessage(int $after, string $action): string
    {
        if ($action === 'blacklist') {
            return '已将该用户列入黑名单：账号立即下线，且其手机号、邮箱与证件号会被识别为黑名单，无法用于新账号注册与实名；解除黑名单仅超级管理员可操作';
        }
        if ($action === 'unblacklist') {
            return '已解除黑名单，该账号可以正常登录使用。原封禁记录与原因保留在操作日志中，用户表不再展示拉黑原因';
        }
        $label = Status::user($after);
        return $after === Status::USER_ACTIVE
            ? '用户状态已更新为：' . $label . '，该账号现在可以正常登录使用'
            : '用户状态已更新为：' . $label . '，该账号已无法登录，已登录的会话在下次操作时会被强制退出';
    }

    /**
     * 状态变更留痕：记录状态前后值、用户编号与拉黑原因，便于事后追溯是谁因何停用了账号
     * @param string $reason        本次操作填写的原因（仅拉黑时有值）
     * @param string $removedReason 解除拉黑前用户表里存的原因，记入变更前数据以保留完整链路
     */
    private function logStatusChange(int $userId, string $userNo, int $before, int $after, string $reason = '', string $removedReason = ''): void
    {
        $staff = StaffAuth::staff() ?? [];
        $beforeData = ['user_no' => $userNo, 'status' => $before, 'status_text' => Status::user($before)];
        if ($before === Status::USER_BLACKLIST && $removedReason !== '') {
            $beforeData['blacklist_reason'] = $removedReason;
        }
        $afterData = ['user_no' => $userNo, 'status' => $after, 'status_text' => Status::user($after)];
        if ($reason !== '') {
            $afterData['blacklist_reason'] = $reason;
        }
        Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('admin_operation_logs') . '
             (admin_id, admin_name, module, action, target_type, target_id, before_data, after_data, ip)
             VALUES (:aid, :aname, :module, :action, :ttype, :tid, :bdata, :adata, :ip)'
        )->execute([
            ':aid' => (int) (StaffAuth::id() ?? 0),
            // 员工资料里姓名字段为 real_name，日志需记录操作人姓名快照以便追溯
            ':aname' => (string) ($staff['real_name'] ?? $staff['name'] ?? '') ?: '—',
            ':module' => 'user',
            ':action' => 'change_status',
            ':ttype' => 'user',
            ':tid' => $userId,
            ':bdata' => json_encode($beforeData, JSON_UNESCAPED_UNICODE),
            ':adata' => json_encode($afterData, JSON_UNESCAPED_UNICODE),
            ':ip' => Request::ip(),
        ]);
    }

    /**
     * 风控等级设置（0 正常 / 1 关注 / 2 限制）
     */
    public function risk(): never
    {
        $this->requireStaff();
        $this->enforce('user.risk.set');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $level = (int) Request::input('level', -1);
        if (!in_array($level, [0, 1, 2], true)) {
            $this->fail('无效的风控等级');
        }
        UserModel::updateRiskLevel($id, $level);
        $this->ok(null, '风控等级已更新');
    }

    /**
     * 余额人工调整：资金流水入账（type 6 人工调整），余额与流水同事务
     */
    public function balance(): never
    {
        $this->requireStaff();
        $this->enforce('user.balance.adjust');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $direction = (int) Request::input('direction', 0);
        $amount = (float) Request::input('amount', 0);
        $remark = trim((string) Request::input('remark', ''));
        if (!in_array($direction, [1, 2], true)) {
            $this->fail('请选择调整方向（1 增加 / 2 扣减）');
        }
        if ($remark === '') {
            $this->fail('请填写调整原因，便于对账追溯');
        }
        $user = UserModel::findWithAccount($id);
        if ($user === null || $user['account_id'] === null) {
            $this->fail('用户或其账户不存在');
        }
        try {
            FundFlowModel::record($id, 6, $direction, $amount, 4, $user['user_no'], StaffAuth::id(), $remark);
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '余额调整成功');
    }
}
