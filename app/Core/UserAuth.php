<?php declare(strict_types=1);
/**
 * @file app/Core/UserAuth.php
 * @description 用户端会话鉴权（普通用户/会员用户）：登录态读取与账号可用性闸门
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 *
 * 设计取舍：会话只作为「谁登录了」的凭据，账号能否使用一律以数据库当前状态为准，
 * 因此后台把账号改为禁用/黑名单后，无需依赖会话过期即可让在途会话立即失效。
 */

namespace App\Core;

use App\Enums\Status;
use App\Models\UserModel;

final class UserAuth
{
    /** 一次性提示键：账号被后台停用时记录原因，由登录页展示 */
    private const NOTICE_KEY = '_auth_notice';

    /** 账号已不存在或已注销时的提示文案（区别于状态停用，需引导重新注册） */
    private const REASON_REMOVED = '此账号已注销，如需继续使用请重新注册';

    /** 本次请求内的判定缓存：状态校验最多查库一次 */
    private static bool $resolved = false;
    private static ?array $freshUser = null;
    private static ?string $blockedReason = null;

    /**
     * 是否处于「已登录且账号可用」状态
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * 会话中的用户（账号被停用时返回 null 并清理会话，后续请求即回到登录页）
     */
    public static function user(): ?array
    {
        if (!self::hasSession()) {
            return null;
        }
        $reason = self::resolveBlockedReason();
        if ($reason !== null) {
            self::logout();
            $_SESSION[self::NOTICE_KEY] = $reason;
            return null;
        }
        return $_SESSION['auth_user'];
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    /**
     * 权威用户数据（含会员等级、实名状态等最新值）；账号不可用时返回 null
     */
    public static function fresh(): ?array
    {
        return self::user() === null ? null : self::$freshUser;
    }

    /**
     * 登录成功后重置会话 ID，防止会话固定攻击
     */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['auth_user'] = $user;
        // 重新登录说明账号已恢复可用，清掉上一次的停用提示，避免登录页残留旧提示
        unset($_SESSION[self::NOTICE_KEY]);
        self::$resolved = false;
        self::$freshUser = null;
        self::$blockedReason = null;
    }

    public static function logout(): void
    {
        unset($_SESSION['auth_user']);
        // 标记为已判定，避免同一请求内再次查库
        self::$resolved = true;
        self::$freshUser = null;
        self::$blockedReason = null;
    }

    /**
     * 当前挂起的一次性提示（不消费），供接口在返回中携带停用原因
     */
    public static function notice(): ?string
    {
        return empty($_SESSION[self::NOTICE_KEY]) ? null : (string) $_SESSION[self::NOTICE_KEY];
    }

    /**
     * 取走一次性提示（登录页展示用，读取即清除，避免反复出现）
     */
    public static function pullNotice(): ?string
    {
        $notice = self::notice();
        unset($_SESSION[self::NOTICE_KEY]);
        return $notice;
    }

    private static function hasSession(): bool
    {
        return isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user']);
    }

    /**
     * 账号可用性判定：以数据库为准，会话中缓存的旧状态不参与决策
     * @return string|null 可用返回 null，否则返回停用原因
     */
    private static function resolveBlockedReason(): ?string
    {
        if (self::$resolved) {
            return self::$blockedReason;
        }
        self::$resolved = true;
        $row = UserModel::findById((int) ($_SESSION['auth_user']['id'] ?? 0));
        if ($row === null) {
            self::$blockedReason = self::REASON_REMOVED;
            return self::$blockedReason;
        }
        self::$freshUser = $row;
        self::$blockedReason = Status::blockedReason((int) $row['status']);
        return self::$blockedReason;
    }
}
