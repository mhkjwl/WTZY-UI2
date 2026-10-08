<?php declare(strict_types=1);
/**
 * @file app/Core/StaffAuth.php
 * @description 员工会话鉴权（管理端 / 仓库端会话彼此独立，含 RBAC 角色判断）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 *
 * 两端隔离且互不影响：
 *   - 会话按端分槽存储（管理端 auth_staff_admin、仓库端 auth_staff_warehouse），
 *     同一浏览器可同时保持两端登录态，登录一端不会把另一端挤掉线；
 *   - 读取时按当前请求路径首段自动定位所属端，调用方无需关心；
 *   - 校验时同时核对账号自身 staff_type 是否属于该端，管理员账号无法进入仓库端。
 */

namespace App\Core;

final class StaffAuth
{
    /** 端别标识 */
    public const END_ADMIN = 'admin';
    public const END_WAREHOUSE = 'warehouse';

    /** 员工端别编码（与 admins.staff_type 对应） */
    public const TYPE_ADMIN = 1;
    public const TYPE_WAREHOUSE = 2;

    /**
     * 当前请求所属的端（按路径首段判断，非员工端返回空串）
     */
    public static function currentEnd(): string
    {
        $first = explode('/', trim(Request::path(), '/'))[0] ?? '';
        return match ($first) {
            'admin'     => self::END_ADMIN,
            'warehouse' => self::END_WAREHOUSE,
            default     => '',
        };
    }

    /**
     * 会话槽位键名（不同端互不覆盖）
     */
    private static function slot(string $end, string $suffix = ''): string
    {
        return 'auth_staff' . ($end !== '' ? '_' . $end : '') . $suffix;
    }

    public static function check(): bool
    {
        return self::staff() !== null;
    }

    /**
     * 当前端的登录员工（未登录返回 null）
     */
    public static function staff(): ?array
    {
        $key = self::slot(self::currentEnd());
        return isset($_SESSION[$key]) && is_array($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    public static function id(): ?int
    {
        $staff = self::staff();
        return $staff === null ? null : (int) $staff['id'];
    }

    /**
     * 当前端的权限点集合
     */
    public static function permissions(): array
    {
        $perms = $_SESSION[self::slot(self::currentEnd(), '_perms')] ?? [];
        return is_array($perms) ? $perms : [];
    }

    /**
     * 指定端是否处于有效登录态
     * 双重校验：该端槽位有登录态，且账号自身 staff_type 属于该端
     */
    public static function boundTo(string $end): bool
    {
        $staff = $_SESSION[self::slot($end)] ?? null;
        if (!is_array($staff)) {
            return false;
        }
        $expected = $end === self::END_WAREHOUSE ? self::TYPE_WAREHOUSE : self::TYPE_ADMIN;
        return (int) ($staff['staff_type'] ?? 0) === $expected;
    }

    public static function roleCode(): string
    {
        $staff = self::staff();
        return (string) ($staff['role_code'] ?? '');
    }

    public static function is(string $roleCode): bool
    {
        return self::roleCode() === $roleCode;
    }

    public static function isSuperAdmin(): bool
    {
        return self::is('super_admin');
    }

    /**
     * 登录：写入该端专属会话槽位（不影响另一端已登录的会话）
     */
    public static function login(array $staff, string $end, array $permissions = []): void
    {
        session_regenerate_id(true);
        $_SESSION[self::slot($end)] = $staff;
        $_SESSION[self::slot($end, '_perms')] = $permissions;
    }

    /**
     * 退出：只清当前端的会话，另一端保持登录
     */
    public static function logout(): void
    {
        $end = self::currentEnd();
        unset($_SESSION[self::slot($end)], $_SESSION[self::slot($end, '_perms')]);
    }

    /**
     * 退出指定端（跨端清理用）
     */
    public static function logoutEnd(string $end): void
    {
        unset($_SESSION[self::slot($end)], $_SESSION[self::slot($end, '_perms')]);
    }
}
