<?php declare(strict_types=1);
/**
 * @file app/Services/AuthService.php
 * @description 认证业务：用户注册（事务闭环）、用户登录、员工登录（双端 + RBAC 权限加载）
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

namespace App\Services;

use App\Core\Database;
use App\Core\Mailer;
use App\Core\Request;
use App\Enums\Status;
use App\Models\AdminModel;
use App\Models\SettingsModel;
use App\Models\UidSequenceModel;
use App\Models\UserAccountModel;
use App\Models\UserAuthModel;
use App\Models\UserModel;
use App\Models\VerifyCodeModel;
use App\Models\WarehouseAddressModel;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class AuthService
{
    /** 登录方式编码（与 wtzy_user_auths.auth_type 对应） */
    private const AUTH_PHONE = 1;
    private const AUTH_EMAIL = 2;

    /** 识别码字符集：去除易混淆的 0/O/1/I，便于仓库人工核对面单 */
    private const CODE_CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** 找回密码：验证码用途标识与有效期（秒） */
    private const PURPOSE_RESET = 'reset_password';
    private const RESET_CODE_TTL = 600;

    /** 邮箱验证码用途：注册校验 / 绑定邮箱，有效期 10 分钟（规范要求） */
    private const PURPOSE_REGISTER = 'register_email';
    private const PURPOSE_BIND_EMAIL = 'bind_email';
    private const EMAIL_CODE_TTL = 600;
    /** 邮箱发码限流：同一邮箱每小时 5 封、同一 IP 每小时 20 封 */
    private const EMAIL_HOURLY_LIMIT = 5;
    private const EMAIL_IP_HOURLY_LIMIT = 20;

    /**
     * 用户注册：校验 → 事务内取号/建号/开户/分配仓地址/欢迎信 → 返回可入会话的用户数据
     * 开启「邮箱验证注册」时账号必须是邮箱且需先通过邮箱验证码校验
     */
    public static function register(?string $username, ?string $account, ?string $password, ?string $passwordConfirm, ?string $nickname, ?string $emailCode = null): array
    {
        $username = trim((string) $username);
        $account = trim((string) $account);
        $emailCode = trim((string) $emailCode);
        [$authType, $identifier] = self::detectAccountType($account);

        self::assertUsername($username);
        self::assertPassword((string) $password, (string) $passwordConfirm);

        if (UserModel::findByUsername($username) !== null) {
            throw new InvalidArgumentException('该用户名已被占用，请换一个');
        }
        // 黑名单校验必须先于「已注册」校验：两者可能命中同一手机号/邮箱，
        // 但「已注册」会让用户以为可以找回账号，实际是身份被封禁，提示必须说清处置结果
        $idLabel = $authType === self::AUTH_PHONE ? '手机号' : '邮箱';
        if (UserModel::findBlacklistedByIdentifier($authType, $identifier) !== null) {
            throw new InvalidArgumentException('该' . $idLabel . '已被列入黑名单，无法注册，如有疑问请联系客服');
        }
        if (UserAuthModel::findByIdentifier($authType, $identifier) !== null) {
            throw new InvalidArgumentException('该' . $idLabel . '已注册');
        }
        // 邮箱验证开关开启：账号必须是邮箱、全局唯一，且验证码必须在建号前校验通过
        if (self::emailVerifyEnabled()) {
            self::assertEmail($account);
            self::assertEmailAvailable($account);
            VerifyCodeModel::consume($account, self::PURPOSE_REGISTER, $emailCode);
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // 先原子取号再插入：UID 随首次写入即确定，不再依赖自增主键回填或空值占位
            $userNo = UidSequenceModel::allocate('user');
            [$userId, $userCode] = self::createUserWithUniqueCode($pdo, $authType, $identifier, $userNo, $username, (string) $password, $nickname);
            UserAuthModel::create($userId, $authType, $identifier);
            UserAccountModel::open($userId);
            self::assignDefaultWarehouseAddress($pdo, $userId, $userCode);
            self::sendWelcomeMessage($userId, $userNo);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            if ($e instanceof InvalidArgumentException) {
                throw $e;
            }
            throw new RuntimeException('注册失败，请稍后重试');
        }

        return self::sessionUser(UserModel::findById($userId) ?? []);
    }

    /**
     * 建立用户记录：识别码存在唯一键，并发下极小概率撞号，
     * 命中即重新生成后重试，避免用户侧看到无谓的注册失败
     * @return array{0: int, 1: string} 用户ID 与最终落库的识别码
     */
    private static function createUserWithUniqueCode(PDO $pdo, int $authType, string $identifier, string $userNo, string $username, string $password, ?string $nickname): array
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $userCode = self::generateUserCode($pdo);
            try {
                $userId = UserModel::create([
                    'user_no'       => $userNo,
                    'username'      => $username,
                    'user_code'     => $userCode,
                    'nickname'      => self::sanitizeNickname($nickname),
                    'phone'         => $authType === self::AUTH_PHONE ? $identifier : null,
                    'email'         => $authType === self::AUTH_EMAIL ? $identifier : null,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                return [$userId, $userCode];
            } catch (PDOException $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'uk_username')) {
                    throw new InvalidArgumentException('该用户名已被占用，请换一个');
                }
                if (!str_contains($message, 'uk_user_code')) {
                    throw $e;
                }
            }
        }
        throw new RuntimeException('识别码分配失败，请稍后重试');
    }

    /**
     * 用户名规则：字母开头，4~20 位字母/数字/下划线
     */
    private static function assertUsername(string $username): void
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{3,19}$/', $username) !== 1) {
            throw new InvalidArgumentException('用户名需 4~20 位，以字母开头，仅含字母、数字与下划线');
        }
    }

    /**
     * 是否开启「邮箱验证注册」（后台「系统 → 邮箱设置」开关）
     */
    public static function emailVerifyEnabled(): bool
    {
        return SettingsModel::get('register_email_verify', '0') === '1';
    }

    /**
     * 邮箱格式校验（注册与绑定共用）
     */
    private static function assertEmail(?string $email): string
    {
        $email = trim((string) $email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('请输入正确的邮箱地址');
        }
        if (mb_strlen($email) > 128) {
            throw new InvalidArgumentException('邮箱长度超出限制');
        }
        return $email;
    }

    /**
     * 邮箱全局唯一：一个邮箱只能绑定一个账号（users.email 与登录绑定表双重校验）
     */
    private static function assertEmailAvailable(string $email, ?int $exceptUserId = null): void
    {
        $owner = UserModel::findByEmail($email);
        if ($owner !== null && ($exceptUserId === null || (int) $owner['id'] !== $exceptUserId)) {
            throw new InvalidArgumentException('该邮箱已被其他账号使用，一个邮箱只能绑定一个账号');
        }
        $auth = UserAuthModel::findByIdentifier(self::AUTH_EMAIL, $email);
        if ($auth !== null && ($exceptUserId === null || (int) $auth['user_id'] !== $exceptUserId)) {
            throw new InvalidArgumentException('该邮箱已被其他账号使用，一个邮箱只能绑定一个账号');
        }
    }

    /**
     * 注册邮箱验证码：发送前做格式、占用与频率限制校验（10 分钟有效、60 秒重发、小时级邮箱/IP 上限）
     */
    public static function sendRegisterCode(?string $email, string $ip): void
    {
        $email = self::assertEmail($email);
        self::assertEmailAvailable($email);
        $code = self::guardAndIssueEmailCode($email, self::PURPOSE_REGISTER, $ip);
        self::sendEmailCode($email, $code, 'register');
    }

    /**
     * 绑定邮箱验证码：发给已登录但尚未绑定邮箱的用户
     */
    public static function sendBindEmailCode(int $userId, ?string $email, string $ip): void
    {
        $email = self::assertEmail($email);
        self::assertEmailAvailable($email, $userId);
        $code = self::guardAndIssueEmailCode($email, self::PURPOSE_BIND_EMAIL, $ip);
        self::sendEmailCode($email, $code, 'bind');
    }

    /**
     * 绑定邮箱：核销验证码 → 唯一性终检 → 同事务写 users.email 与登录绑定（已验证）
     */
    public static function bindEmail(int $userId, ?string $email, ?string $code): void
    {
        $email = self::assertEmail($email);
        self::assertEmailAvailable($email, $userId);
        VerifyCodeModel::consume($email, self::PURPOSE_BIND_EMAIL, trim((string) $code));

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE ' . Database::table('users') . ' SET email = :e WHERE id = :id AND deleted_at IS NULL')
                ->execute([':e' => $email, ':id' => $userId]);
            $bound = UserAuthModel::findByUser($userId, self::AUTH_EMAIL);
            if ($bound === null) {
                UserAuthModel::create($userId, self::AUTH_EMAIL, $email, 1);
            } else {
                $pdo->prepare(
                    'UPDATE ' . Database::table('user_auths') . ' SET identifier = :e, is_verified = 1 WHERE id = :id'
                )->execute([':e' => $email, ':id' => (int) $bound['id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw new RuntimeException('绑定失败，邮箱可能刚被其他账号使用，请换一个或稍后再试');
        }
    }

    /**
     * 邮箱验证码频率限制：60 秒重发间隔 + 小时级邮箱/IP 上限，通过后签发验证码
     */
    private static function guardAndIssueEmailCode(string $email, string $purpose, string $ip): string
    {
        if (VerifyCodeModel::isTooFrequent($email, $purpose)) {
            throw new InvalidArgumentException('验证码已发送，请 60 秒后再试');
        }
        if (VerifyCodeModel::recentIssueCount($email, 3600) >= self::EMAIL_HOURLY_LIMIT) {
            throw new InvalidArgumentException('该邮箱验证码发送次数已达上限，请 1 小时后再试');
        }
        if (VerifyCodeModel::recentIpIssueCount($ip, 3600) >= self::EMAIL_IP_HOURLY_LIMIT) {
            throw new InvalidArgumentException('当前网络发送过于频繁，请 1 小时后再试');
        }
        return VerifyCodeModel::issue($email, VerifyCodeModel::CHANNEL_EMAIL, $purpose, self::EMAIL_CODE_TTL, $ip);
    }

    /**
     * 发送验证码邮件；SMTP 未配置或发送异常时给出可理解的失败提示
     */
    private static function sendEmailCode(string $email, string $code, string $scene): void
    {
        $safeBrand = htmlspecialchars(SiteChrome::brand(), ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $minutes = (int) (self::EMAIL_CODE_TTL / 60);
        $action = $scene === 'register' ? '注册账号' : '绑定邮箱';
        $body = '<div style="font-family:sans-serif;font-size:14px;color:#0f172a;line-height:1.8">'
            . '<p>您好，</p>'
            . '<p>您正在 ' . $safeBrand . ' ' . $action . '，验证码为：</p>'
            . '<p style="font-size:26px;font-weight:700;letter-spacing:4px;color:#4f46e5">' . $safeCode . '</p>'
            . '<p>验证码 ' . $minutes . ' 分钟内有效，请勿转发给他人。</p>'
            . '<p style="color:#64748b;font-size:12px">若非本人操作，请忽略本邮件。</p>'
            . '</div>';
        try {
            Mailer::send($email, SiteChrome::brand() . ' ' . $action . '验证码', $body);
        } catch (Throwable $e) {
            throw new RuntimeException('验证码发送失败，请稍后重试；若持续失败请联系客服（需先配置好邮箱服务）');
        }
    }

    public static function userLogin(?string $account, ?string $password): array
    {
        $user = self::findUserByLoginAccount(trim((string) $account));

        // 统一提示文案，不区分账号不存在与密码错误，避免账号枚举探测
        if ($user === null || !password_verify((string) $password, (string) $user['password_hash'])) {
            throw new InvalidArgumentException('账号或密码错误');
        }
        self::assertUserUsable($user);

        UserModel::updateLoginInfo((int) $user['id'], Request::ip());
        return self::sessionUser($user);
    }

    /**
     * 登录账号解析：用户名 / 手机号 / 邮箱三种标识任选其一
     */
    private static function findUserByLoginAccount(string $account): ?array
    {
        if ($account === '') {
            return null;
        }
        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            $auth = UserAuthModel::findByIdentifier(self::AUTH_EMAIL, $account);
            return $auth === null ? null : UserModel::findById((int) $auth['user_id']);
        }
        if (preg_match('/^\+?\d{7,15}$/', $account) === 1) {
            $auth = UserAuthModel::findByIdentifier(self::AUTH_PHONE, $account);
            return $auth === null ? null : UserModel::findById((int) $auth['user_id']);
        }
        return UserModel::findByUsername($account);
    }

    /**
     * 找回密码第一步：校验用户名与邮箱匹配后，向该邮箱发送验证码
     * 匹配失败与用户名不存在返回同一提示，避免暴露账号是否存在
     */
    public static function requestPasswordReset(?string $username, ?string $email, string $ip): void
    {
        $username = trim((string) $username);
        $email = trim((string) $email);
        $user = self::matchUserForReset($username, $email);

        if (VerifyCodeModel::isTooFrequent($email, self::PURPOSE_RESET)) {
            throw new InvalidArgumentException('验证码已发送，请 60 秒后再试');
        }

        $code = VerifyCodeModel::issue($email, VerifyCodeModel::CHANNEL_EMAIL, self::PURPOSE_RESET, self::RESET_CODE_TTL, $ip);
        Mailer::send($email, SiteChrome::brand() . ' 找回密码验证码', self::resetMailBody((string) $user['nickname'], $code));
    }

    /**
     * 找回密码第二步：校验邮箱验证码后重置登录密码
     */
    public static function resetPassword(?string $username, ?string $email, ?string $code, ?string $password, ?string $passwordConfirm): void
    {
        $username = trim((string) $username);
        $email = trim((string) $email);
        self::assertPassword((string) $password, (string) $passwordConfirm);

        $user = self::matchUserForReset($username, $email);
        VerifyCodeModel::consume($email, self::PURPOSE_RESET, trim((string) $code));
        UserModel::updatePassword((int) $user['id'], password_hash((string) $password, PASSWORD_DEFAULT));
    }

    /**
     * 找回密码的身份匹配：用户名存在且邮箱一致才放行
     */
    private static function matchUserForReset(string $username, string $email): array
    {
        $user = $username === '' ? null : UserModel::findByUsername($username);
        if ($user === null || $email === '' || strcasecmp((string) $user['email'], $email) !== 0) {
            throw new InvalidArgumentException('用户名与邮箱不匹配，请检查后重试');
        }
        return $user;
    }

    private static function resetMailBody(string $nickname, string $code): string
    {
        $safeName = htmlspecialchars($nickname !== '' ? $nickname : '用户', ENT_QUOTES, 'UTF-8');
        $safeBrand = htmlspecialchars(SiteChrome::brand(), ENT_QUOTES, 'UTF-8');
        $minutes = (int) (self::RESET_CODE_TTL / 60);
        return '<div style="font-family:sans-serif;font-size:14px;color:#0f172a;line-height:1.8">'
            . '<p>' . $safeName . ' 您好，</p>'
            . '<p>您正在申请重置 ' . $safeBrand . ' 的登录密码，验证码为：</p>'
            . '<p style="font-size:26px;font-weight:700;letter-spacing:4px;color:#4f46e5">' . $code . '</p>'
            . '<p>验证码 ' . $minutes . ' 分钟内有效，请勿转发给他人。</p>'
            . '<p style="color:#64748b;font-size:12px">若非本人操作，请忽略本邮件，您的密码不会被修改。</p>'
            . '</div>';
    }

    /**
     * 员工登录（管理端/仓库端共用 admins 表，端别由 staff_type 区分）
     * @return array{0: array, 1: string[]} 会话员工数据 + 权限点列表
     */
    public static function staffLogin(?string $username, ?string $password, int $expectedStaffType): array
    {
        $username = trim((string) $username);
        if ($username === '' || (string) $password === '') {
            throw new InvalidArgumentException('请输入账号和密码');
        }

        $staff = AdminModel::findByUsername($username);
        if ($staff === null || $staff['deleted_at'] !== null
            || !password_verify((string) $password, (string) $staff['password_hash'])) {
            throw new InvalidArgumentException('账号或密码错误');
        }
        if ((int) $staff['status'] !== 1) {
            throw new InvalidArgumentException('账号已被禁用，请联系管理员');
        }
        if ((int) $staff['role_status'] !== 1) {
            throw new InvalidArgumentException('账号所属角色已停用，请联系管理员');
        }
        if ((int) $staff['staff_type'] !== $expectedStaffType) {
            throw new InvalidArgumentException('该账号无权登录此端');
        }

        $permissions = AdminModel::permissionCodesForRole((int) $staff['role_id']);
        AdminModel::updateLoginInfo((int) $staff['id'], Request::ip());

        return [self::sessionStaff($staff), $permissions];
    }

    private static function detectAccountType(string $account): array
    {
        if ($account === '') {
            throw new InvalidArgumentException('请输入手机号或邮箱');
        }
        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            return [self::AUTH_EMAIL, $account];
        }
        if (preg_match('/^\+?\d{7,15}$/', $account) === 1) {
            return [self::AUTH_PHONE, $account];
        }
        throw new InvalidArgumentException('账号格式不正确，请输入手机号或邮箱');
    }

    private static function assertPassword(string $password, string $passwordConfirm): void
    {
        if (strlen($password) < 8 || preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            throw new InvalidArgumentException('密码至少 8 位，且需同时包含字母和数字');
        }
        if ($password !== $passwordConfirm) {
            throw new InvalidArgumentException('两次输入的密码不一致');
        }
    }

    /**
     * 登录前置校验：状态文案统一取自 Status::blockedReason，
     * 与在途会话校验保持同一份判定，避免同一账号在登录与已登录两种场景给出不同说法
     */
    private static function assertUserUsable(array $user): void
    {
        $reason = Status::blockedReason((int) $user['status']);
        if ($reason !== null) {
            throw new InvalidArgumentException($reason);
        }
    }

    private static function generateUserCode(PDO $pdo): string
    {
        // 冲突重试：8 位识别码空间约 39^8，实际几乎不会连续冲突
        for ($i = 0; $i < 5; $i++) {
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= self::CODE_CHARSET[random_int(0, strlen(self::CODE_CHARSET) - 1)];
            }
            $stmt = $pdo->prepare('SELECT 1 FROM ' . Database::table('users') . ' WHERE user_code = :code LIMIT 1');
            $stmt->execute([':code' => $code]);
            if ($stmt->fetchColumn() === false) {
                return $code;
            }
        }
        throw new RuntimeException('注册失败，请稍后重试');
    }

    /**
     * 昵称非必填：未填写就保持为空，不再用账号前缀生成占位昵称
     */
    private static function sanitizeNickname(?string $nickname): string
    {
        return mb_substr(trim((string) $nickname), 0, 20);
    }

    private static function assignDefaultWarehouseAddress(PDO $pdo, int $userId, string $userCode): void
    {
        $stmt = $pdo->prepare(
            'SELECT id, code, country, state, city, address, zip_code, contact_phone
             FROM ' . Database::table('warehouses') . ' WHERE status = 1 AND deleted_at IS NULL ORDER BY sort ASC, id ASC LIMIT 1'
        );
        $stmt->execute();
        $warehouse = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($warehouse === false) {
            return;
        }
        WarehouseAddressModel::createForUser($userId, (int) $warehouse['id'], $userCode, $warehouse);
    }

    private static function sendWelcomeMessage(int $userId, string $userNo): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('messages') . ' (user_id, msg_type, title, content, biz_type, biz_no)
             VALUES (:user_id, 1, :title, :content, 4, :biz_no)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':title'   => '欢迎加入 ' . SiteChrome::brand(),
            ':content' => "您的账号 {$userNo} 已开通。请先在「海外仓地址」获取专属收货地址，即可在电商平台下单后进行包裹预报。",
            ':biz_no'  => $userNo,
        ]);
    }

    /**
     * 提取可入会话的用户字段（不携带密码哈希）
     */
    private static function sessionUser(array $user): array
    {
        return [
            'id'           => (int) $user['id'],
            'user_no'      => (string) $user['user_no'],
            'username'     => (string) ($user['username'] ?? ''),
            'user_code'    => (string) $user['user_code'],
            'nickname'     => (string) $user['nickname'],
            'member_level' => (int) $user['member_level'],
            'risk_level'   => (int) $user['risk_level'],
        ];
    }

    private static function sessionStaff(array $staff): array
    {
        return [
            'id'           => (int) $staff['id'],
            'username'     => (string) $staff['username'],
            'real_name'    => (string) $staff['real_name'],
            'role_id'      => (int) $staff['role_id'],
            'role_code'    => (string) $staff['role_code'],
            'role_name'    => (string) $staff['role_name'],
            'warehouse_id' => $staff['warehouse_id'] !== null ? (int) $staff['warehouse_id'] : null,
            'staff_type'   => (int) $staff['staff_type'],
        ];
    }
}
