<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/SystemController.php
 * @description 系统设置：站点信息 / 邮箱发送 / 员工管理 / 角色权限矩阵
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\StaffAuth;
use App\Models\AdminModel;
use App\Models\RoleModel;
use App\Models\SettingsModel;
use App\Models\WarehouseModel;
use App\Services\HomeContent;
use App\Services\SiteChrome;

final class SystemController extends BaseController
{
    /**
     * 站点信息：品牌（名称/关键词/介绍）与联系方式（页头页脚展示，官网各页共用）
     */
    public function site(): never
    {
        $this->requireStaff();
        $this->enforce('system.manage');
        $this->adminView('admin/system_site', [
            'title' => '基本信息',
            'site'  => [
                'site_name'        => SettingsModel::get('site_name', ''),
                'site_keywords'    => SettingsModel::get('site_keywords', ''),
                'site_description' => SettingsModel::get('site_description', ''),
                'site_company'     => SettingsModel::get('site_company', ''),
                'site_phone'       => SettingsModel::get('site_phone', ''),
                'site_email'       => SettingsModel::get('site_email', ''),
                'site_hours'       => SettingsModel::get('site_hours', ''),
                'site_address'     => SettingsModel::get('site_address', ''),
                'site_icp'         => SettingsModel::get('site_icp', ''),
                'warehouse_address_prefix' => SettingsModel::get('warehouse_address_prefix', 'WTZY'),
                // 官网首页数字：显示当前实际生效值（未配置时为默认值），便于直接编辑；
                // 服务承诺用原始值，{days} 占位符原样显示与保存，避免被固化成写死的数字
                'homepage_stats'    => HomeContent::stats(),
                'homepage_promises' => HomeContent::promisesRaw(),
                // 注册与实名设置：邮箱验证开关在「邮箱设置」页维护，此处只读展示便于总览
                'register_email_verify' => SettingsModel::get('register_email_verify', '0'),
                'realname_required'     => SettingsModel::get('realname_required', '0'),
            ],
        ], 'system_site', '基本信息');
    }

    /**
     * 保存站点信息（网站名称为必填项，关键词与介绍用于页面 meta 输出）
     */
    public function saveSite(): never
    {
        $this->requireStaff();
        $this->enforce('system.manage');
        $this->verifyCsrf();
        $name = trim((string) Request::input('site_name', ''));
        $keywords = trim((string) Request::input('site_keywords', ''));
        $description = trim((string) Request::input('site_description', ''));
        if ($name === '') {
            $this->fail('请填写网站名称');
        }
        if (mb_strlen($name) > 50) {
            $this->fail('网站名称不超过 50 个字符');
        }
        if (mb_strlen($keywords) > 200) {
            $this->fail('网站关键词不超过 200 个字符');
        }
        if (mb_strlen($description) > 500) {
            $this->fail('网站介绍不超过 500 个字符');
        }
        // 联系方式为可选项：留空时官网对应位置自动隐藏，不显示空占位
        $contact = [
            'site_company' => ['公司全称', trim((string) Request::input('site_company', '')), 100],
            'site_phone'   => ['客服电话', trim((string) Request::input('site_phone', '')), 50],
            'site_email'   => ['客服邮箱', trim((string) Request::input('site_email', '')), 100],
            'site_hours'   => ['服务时间', trim((string) Request::input('site_hours', '')), 50],
            'site_address' => ['公司地址', trim((string) Request::input('site_address', '')), 200],
            'site_icp'     => ['备案信息', trim((string) Request::input('site_icp', '')), 200],
        ];
        foreach ($contact as $key => [$label, $value, $max]) {
            if (mb_strlen($value) > $max) {
                $this->fail($label . '不超过 ' . $max . ' 个字符');
            }
        }
        if ($contact['site_email'][1] !== '' && filter_var($contact['site_email'][1], FILTER_VALIDATE_EMAIL) === false) {
            $this->fail('客服邮箱格式不正确');
        }
        SettingsModel::upsert('site_name', $name, 'site', '网站名称');
        SettingsModel::upsert('site_keywords', $keywords, 'site', '网站关键词');
        SettingsModel::upsert('site_description', $description, 'site', '网站介绍');
        foreach ($contact as $key => [$label, $value]) {
            SettingsModel::upsert($key, $value, 'site', $label);
        }
        // 海外仓地址前缀：拼进面单收件人名称供仓库识别，只允许字母数字
        $prefix = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) Request::input('warehouse_address_prefix', '')));
        if ($prefix === '') {
            $this->fail('海外仓地址前缀不能为空（只能包含字母与数字）');
        }
        if (strlen($prefix) > 10) {
            $this->fail('海外仓地址前缀不超过 10 个字符');
        }
        SettingsModel::upsert('warehouse_address_prefix', $prefix, 'site', '海外仓地址前缀（面单收件人名称前缀）');
        // 强制实名认证开关：开启后未实名用户仅可修改资料/实名/退出登录
        SettingsModel::upsert('realname_required', Request::input('realname_required', '0') === '1' ? '1' : '0', 'site', '强制实名认证开关');
        $this->saveNumberRows('homepage_stats', '官网首页-统计数字');
        $this->saveNumberRows('homepage_promises', '官网首页-服务承诺');
        $this->ok(null, '站点信息已保存');
    }

    /**
     * 自动任务：查看上次执行情况并支持一键手动执行（自动签收/积分入账/兜底退款）
     */
    public function tasks(): never
    {
        $this->requireStaff();
        $this->enforce('system.manage');
        $this->adminView('admin/system_tasks', [
            'title'      => '自动任务',
            'lastRunAt'  => (string) SettingsModel::get('task_last_run_at', '从未执行'),
            'lazyMinutes'=> (string) SettingsModel::get('task_lazy_minutes', '10'),
            'autoSignDays' => (string) SettingsModel::get('auto_sign_days', '15'),
            'delayDays'  => (string) SettingsModel::get('points_award_delay_days', '7'),
            'runToken'   => (string) SettingsModel::get('task_run_token', ''),
            'pendingCount' => (int) Database::pdo()->query(
                'SELECT COUNT(*) FROM ' . Database::table('points_pending') . ' WHERE status = 0'
            )->fetchColumn(),
        ], 'system_tasks', '自动任务');
    }

    /**
     * 生成/重置远程触发 token：返回新值并落库，前端展示完整 URL 供 cron 配置
     */
    public function resetTaskToken(): never
    {
        $this->requireStaff();
        $this->enforce('system.manage');
        $this->verifyCsrf();
        $token = bin2hex(random_bytes(24));
        SettingsModel::upsert('task_run_token', $token, 'order', '自动任务远程触发 token（外部定时任务凭此调用 /api/tasks/run）');
        $this->ok(['token' => $token], '已生成新的触发 token');
    }

    public function runTasks(): never
    {
        $this->requireStaff();
        $this->enforce('system.manage');
        $this->verifyCsrf();
        $result = \App\Services\TaskService::runAll(200);
        SettingsModel::upsert('task_last_run_at', date('Y-m-d H:i:s'), 'order', '自动任务上次执行时间（懒执行限频用）');
        $this->ok($result, '任务已执行：自动签收 ' . $result['auto_sign'] . ' 单、积分入账 '
            . $result['activate_points'] . ' 笔、兜底退款 ' . $result['refund_disposed'] . ' 单');
    }

    /**
     * 邮箱发送设置（SMTP）：密码以 AES 密文入库，读取时解密
     */
    public function mail(): never
    {
        $this->requireStaff();
        $this->enforce('system.manage');
        $this->adminView('admin/system_mail', [
            'title'      => '邮箱设置',
            'config'     => [
                'mail_host'      => SettingsModel::get('mail_host', (string) \App\Core\Config::get('mail.host', '')),
                'mail_port'      => SettingsModel::get('mail_port', (string) \App\Core\Config::get('mail.port', '465')),
                'mail_secure'    => SettingsModel::get('mail_secure', (string) \App\Core\Config::get('mail.secure', 'ssl')),
                'mail_user'      => SettingsModel::get('mail_user', (string) \App\Core\Config::get('mail.user', '')),
                'mail_from'      => SettingsModel::get('mail_from', (string) \App\Core\Config::get('mail.from', '')),
                'mail_from_name' => SettingsModel::get('mail_from_name', (string) \App\Core\Config::get('mail.from_name', '')),
            ],
            'hasPass'    => SettingsModel::get('mail_pass', '') !== '' || (string) \App\Core\Config::get('mail.pass', '') !== '',
            'configured' => \App\Core\Mailer::isConfigured(),
            // 邮箱验证注册开关：开启后注册必须邮箱 + 验证码，本页与邮件服务配置一起维护
            'register_email_verify' => SettingsModel::get('register_email_verify', '0'),
        ], 'system_mail', '邮箱设置');
    }

    public function saveMail(): never
    {
        $this->requireStaff();
        $this->enforce('system.manage');
        $this->verifyCsrf();

        $host = trim((string) Request::input('mail_host', ''));
        $port = (int) Request::input('mail_port', 465);
        $secure = (string) Request::input('mail_secure', 'ssl');
        $user = trim((string) Request::input('mail_user', ''));
        $pass = (string) Request::input('mail_pass', '');
        $from = trim((string) Request::input('mail_from', ''));
        $fromName = trim((string) Request::input('mail_from_name', ''));

        if ($host === '' || preg_match('/^[A-Za-z0-9.\-]+$/', $host) !== 1) {
            $this->fail('SMTP 服务器地址格式不正确');
        }
        if ($port < 1 || $port > 65535) {
            $this->fail('端口需在 1~65535 之间');
        }
        if (!in_array($secure, ['ssl', 'tls', 'none'], true)) {
            $this->fail('无效的加密方式');
        }
        if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail('发件邮箱格式不正确');
        }
        if ($user !== '' && $pass === '' && SettingsModel::get('mail_pass', '') === '') {
            $this->fail('填写了 SMTP 账号就必须填写密码/授权码');
        }

        SettingsModel::upsert('mail_host', $host, 'mail', 'SMTP 服务器');
        SettingsModel::upsert('mail_port', (string) $port, 'mail', 'SMTP 端口');
        SettingsModel::upsert('mail_secure', $secure, 'mail', '加密方式 ssl/tls/none');
        SettingsModel::upsert('mail_user', $user, 'mail', 'SMTP 账号');
        SettingsModel::upsert('mail_from', $from, 'mail', '发件邮箱');
        SettingsModel::upsert('mail_from_name', $fromName !== '' ? $fromName : SiteChrome::brand(), 'mail', '发件人名称');
        // 密码留空表示不修改，避免每次保存都要重新输入
        if ($pass !== '') {
            SettingsModel::upsert('mail_pass', \App\Core\Crypto::encrypt($pass), 'mail', 'SMTP 密码/授权码（AES 加密存储）');
        }
        // 邮箱验证注册开关：依赖本页邮件服务可用，故随邮箱设置一起保存
        SettingsModel::upsert('register_email_verify', Request::input('register_email_verify', '0') === '1' ? '1' : '0', 'site', '开启邮箱验证注册开关');
        $this->ok(null, '邮箱设置已保存');
    }

    /**
     * 发送测试邮件，验证配置是否可用
     */
    public function testMail(): never
    {
        $this->requireStaff();
        $this->enforce('system.manage');
        $this->verifyCsrf();

        $to = trim((string) Request::input('to', ''));
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->fail('请填写有效的测试收件邮箱');
        }
        try {
            \App\Core\Mailer::send($to, SiteChrome::brand() . ' 邮件配置测试', '<p>这是一封测试邮件。收到它说明邮箱发送设置已生效。</p>');
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '测试邮件已发送至 ' . $to);
    }

    /**
     * 保存首页数字行（统计卡 / 服务承诺）：前端按三列并列数组提交，组装为 JSON 存配置
     * 全部行留空表示恢复默认值；某行数值与说明都为空则丢弃该行
     */
    private function saveNumberRows(string $key, string $label): void
    {
        $values = (array) Request::input($key . '_value', []);
        $units = (array) Request::input($key . '_unit', []);
        $labels = (array) Request::input($key . '_label', []);
        $rows = [];
        foreach (array_keys($values) as $i) {
            $value = trim((string) ($values[$i] ?? ''));
            $unit = trim((string) ($units[$i] ?? ''));
            $text = trim((string) ($labels[$i] ?? ''));
            if ($value === '' && $text === '') {
                continue;
            }
            if (mb_strlen($value) > 12 || mb_strlen($unit) > 8 || mb_strlen($text) > 30) {
                $this->fail($label . '：数值 12 字、单位 8 字、说明 30 字以内');
            }
            $rows[] = ['value' => $value, 'unit' => $unit, 'label' => $text];
        }
        SettingsModel::upsert($key, $rows === [] ? '' : (string) json_encode($rows, JSON_UNESCAPED_UNICODE), 'site', $label);
    }

    public function staff(): never
    {
        $this->requireStaff();
        $this->enforce('staff.view');
        $this->renderStaff((string) Request::query('kw', ''));
    }

    private function renderStaff(string $keyword): void
    {
        $page = $this->pageParams();
        $result = AdminModel::search($keyword, $page['limit'], $page['offset']);
        $data = [
            'title'    => '员工管理',
            'rows'     => $result['rows'],
            'total'    => $result['total'],
            'page'     => $page['page'],
            'pages'    => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword'  => $keyword,
            'roles'    => RoleModel::all(),
            'warehouses' => WarehouseModel::all(),
            'base'     => '/admin/system/staff',
        ];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/staff_table', $data);
        }
        $this->adminView('admin/system_staff', $data, 'system_staff', '员工管理');
    }

    /**
     * 新建员工（角色决定功能边界；仓库端员工需指定归属仓库）
     */
    public function saveStaff(): never
    {
        $this->requireStaff();
        $this->enforce('staff.manage');
        $this->verifyCsrf();
        $username = trim((string) Request::input('username', ''));
        $password = (string) Request::input('password', '');
        $d = [
            'username'     => $username,
            'password'     => $password,
            'real_name'    => trim((string) Request::input('real_name', '')),
            'phone'        => trim((string) Request::input('phone', '')),
            'role_id'      => (int) Request::input('role_id', 0),
            'warehouse_id' => (int) Request::input('warehouse_id', 0),
            'staff_type'   => (int) Request::input('staff_type', 1),
        ];
        if (!preg_match('/^[A-Za-z0-9_]{4,32}$/', $username)) {
            $this->fail('用户名需 4~32 位字母、数字或下划线');
        }
        if (strlen($password) < 8 || preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/[a-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            $this->fail('初始密码至少 8 位，且包含大小写字母与数字');
        }
        if ($d['real_name'] === '') {
            $this->fail('请填写员工姓名');
        }
        if ($d['role_id'] <= 0) {
            $this->fail('请选择角色');
        }
        $this->assertCanAssignRole($d['role_id']);
        if ($d['staff_type'] === 2 && $d['warehouse_id'] <= 0) {
            $this->fail('仓库端员工必须指定归属仓库');
        }
        try {
            AdminModel::createStaff($d);
        } catch (\PDOException $e) {
            $this->fail('用户名已存在');
        }
        $this->ok(null, '员工已创建');
    }

    /**
     * 编辑员工资料、角色归属与登录凭据（登录账号与密码留空表示保持原值）
     * 账号即登录标识，与密码同属安全敏感变更，因此与资料同窗提交并一并留痕
     */
    public function updateStaff(): never
    {
        $this->requireStaff();
        $this->enforce('staff.manage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $this->assertCanOperateTarget($id);
        $current = AdminModel::findStaff($id);
        if ($current === null) {
            $this->fail('员工不存在');
        }
        $realName = trim((string) Request::input('real_name', ''));
        $phone = trim((string) Request::input('phone', ''));
        $roleId = (int) Request::input('role_id', 0);
        $warehouseId = (int) Request::input('warehouse_id', 0);
        $staffType = (int) Request::input('staff_type', 1);
        $username = trim((string) Request::input('username', ''));
        $password = (string) Request::input('password', '');
        $passwordConfirm = (string) Request::input('password_confirm', '');

        $currentUsername = (string) $current['username'];
        $changeUsername = $username !== '' && $username !== $currentUsername;
        $changePassword = $password !== '';

        if ($realName === '' || mb_strlen($realName) > 30) {
            $this->fail('员工姓名需为 1~30 字');
        }
        if ($phone !== '' && preg_match('/^[0-9+\- ]{6,20}$/', $phone) !== 1) {
            $this->fail('手机号格式不正确');
        }
        if (!in_array($staffType, [1, 2], true)) {
            $this->fail('无效的员工类型');
        }
        // 账号规则与新建员工保持一致，避免出现前台无法登录的账号
        if ($changeUsername) {
            if (preg_match('/^[A-Za-z0-9_]{4,32}$/', $username) !== 1) {
                $this->fail('登录账号需 4~32 位字母、数字或下划线');
            }
            $occupied = AdminModel::findByUsername($username);
            if ($occupied !== null && (int) $occupied['id'] !== $id) {
                $this->fail('该登录账号已被占用，请换一个');
            }
        }
        if ($changePassword) {
            if (strlen($password) < 8 || preg_match('/[A-Z]/', $password) !== 1
                || preg_match('/[a-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
                $this->fail('密码至少 8 位，且包含大小写字母与数字');
            }
            if ($password !== $passwordConfirm) {
                $this->fail('两次输入的密码不一致');
            }
        }
        $roleExists = false;
        $targetRoleCode = '';
        $currentRoleCode = '';
        foreach (RoleModel::all() as $role) {
            if ((int) $role['id'] === $roleId) {
                $roleExists = true;
                $targetRoleCode = (string) $role['code'];
            }
            if ((int) $role['id'] === (int) $current['role_id']) {
                $currentRoleCode = (string) $role['code'];
            }
        }
        if (!$roleExists) {
            $this->fail('请选择有效角色');
        }
        // 超级管理员权限由代码层全放行，不允许通过编辑把普通员工提升为该角色
        if ($targetRoleCode === 'super_admin' && $currentRoleCode !== 'super_admin') {
            $this->fail('不允许将员工提升为超级管理员');
        }
        if ($staffType === 2 && $warehouseId <= 0) {
            $this->fail('仓库端员工必须指定归属仓库');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            AdminModel::updateStaff($id, [
                'real_name'    => $realName,
                'phone'        => $phone,
                'role_id'      => $roleId,
                'warehouse_id' => $warehouseId,
                'staff_type'   => $staffType,
            ]);
            if ($changeUsername) {
                AdminModel::updateUsername($id, $username);
            }
            if ($changePassword) {
                AdminModel::resetPassword($id, password_hash($password, PASSWORD_DEFAULT));
            }
            if ($changeUsername || $changePassword) {
                $this->logCredentialChange($id, $currentUsername, $changeUsername ? $username : $currentUsername, $changePassword);
            }
            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $this->fail('保存失败，该登录账号可能已被占用');
        }

        $changed = [];
        if ($changeUsername) {
            $changed[] = '登录账号';
        }
        if ($changePassword) {
            $changed[] = '登录密码';
        }
        if ($changed === []) {
            $this->ok(null, '员工信息已更新，相关权限调整需本人重新登录后生效');
        }
        $this->ok(null, '员工信息已更新，' . implode('与', $changed) . '已变更，本人下次需使用新凭据登录');
    }

    public function toggleStaff(): never    {
        $this->requireStaff();
        $this->enforce('staff.manage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        if ($id === StaffAuth::id()) {
            $this->fail('不能停用自己的账号');
        }
        $this->assertCanOperateTarget($id);
        $status = AdminModel::toggleStatus($id);
        $this->ok(['status' => $status], $status === 1 ? '员工已启用' : '员工已禁用');
    }

    /**
     * 员工登录凭据变更留痕：只记录账号前后值与是否重设密码，密码内容（含哈希）一律不入日志
     */
    private function logCredentialChange(int $staffId, string $beforeUsername, string $afterUsername, bool $passwordReset): void
    {
        $operator = StaffAuth::staff() ?? [];
        Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('admin_operation_logs') . '
             (admin_id, admin_name, module, action, target_type, target_id, before_data, after_data, ip)
             VALUES (:aid, :aname, :module, :action, :ttype, :tid, :bdata, :adata, :ip)'
        )->execute([
            ':aid' => (int) (StaffAuth::id() ?? 0),
            // 员工资料里姓名字段为 real_name，日志需记录操作人姓名快照以便追溯
            ':aname' => (string) ($operator['real_name'] ?? '') ?: '—',
            ':module' => 'system',
            ':action' => 'change_credential',
            ':ttype' => 'staff',
            ':tid' => $staffId,
            ':bdata' => json_encode(['username' => $beforeUsername], JSON_UNESCAPED_UNICODE),
            ':adata' => json_encode(['username' => $afterUsername, 'password_reset' => $passwordReset], JSON_UNESCAPED_UNICODE),
            ':ip' => Request::ip(),
        ]);
    }

    /**
     * 员工账号操作保护：持有 staff.manage 的角色只能操作管理员（admin/super_admin）以外的账号，
     * 超级管理员不受限
     */
    private function assertCanOperateTarget(int $targetStaffId): void
    {
        if (StaffAuth::isSuperAdmin()) {
            return;
        }
        $target = AdminModel::findStaff($targetStaffId);
        if ($target === null) {
            $this->fail('员工不存在');
        }
        foreach (RoleModel::all() as $role) {
            if ((int) $role['id'] === (int) $target['role_id']
                && in_array((string) $role['code'], ['admin', 'super_admin'], true)) {
                $this->fail('无权操作管理员账号，仅超级管理员可操作');
            }
        }
    }

    /**
     * 角色授予保护：非超级管理员不能把账号创建/调整为管理员角色
     */
    private function assertCanAssignRole(int $roleId): void
    {
        if (StaffAuth::isSuperAdmin()) {
            return;
        }
        foreach (RoleModel::all() as $role) {
            if ((int) $role['id'] === $roleId && in_array((string) $role['code'], ['admin', 'super_admin'], true)) {
                $this->fail('无权授予管理员角色，仅超级管理员可操作');
            }
        }
    }

    /**
     * 角色权限矩阵
     */
    public function roles(): never
    {
        $this->requireStaff();
        $this->enforce('system.manage');
        $roles = RoleModel::all();
        $matrix = [];
        foreach ($roles as $role) {
            $matrix[$role['id']] = $role['code'] === 'super_admin' ? [] : RoleModel::permissionIdsForRole((int) $role['id']);
        }
        $data = ['title' => '角色权限', 'roles' => $roles, 'permissions' => RoleModel::permissionsGrouped(), 'matrix' => $matrix];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/roles_content', $data);
        }
        $this->adminView('admin/system_roles', $data, 'system_roles', '角色权限');
    }

    /**
     * 保存角色权限矩阵（超级管理员角色由代码层全放行，不开放编辑）
     */
    public function saveMatrix(): never
    {
        $this->requireStaff();
        $this->enforce('system.manage');
        $this->verifyCsrf();
        $roleId = (int) Request::input('role_id', 0);
        $role = null;
        foreach (RoleModel::all() as $r) {
            if ((int) $r['id'] === $roleId) {
                $role = $r;
            }
        }
        if ($role === null) {
            $this->fail('角色不存在');
        }
        if ($role['code'] === 'super_admin') {
            $this->fail('超级管理员权限由代码层控制，不可编辑');
        }
        // 必须显式传集合（可为空数组表示清空授权）：缺失时拒绝，避免误清空整角色权限
        $rawPermissions = Request::input('perms');
        if (!is_array($rawPermissions)) {
            $this->fail('缺少权限集合参数，请刷新页面后重试');
        }
        $permissionIds = array_map('intval', $rawPermissions);
        try {
            RoleModel::saveMatrix($roleId, $permissionIds);
        } catch (RuntimeException $e) {
            $this->fail('保存失败，请重试');
        }
        $this->ok(null, '角色权限已更新，相关员工下次操作即按新权限生效');
    }
}
