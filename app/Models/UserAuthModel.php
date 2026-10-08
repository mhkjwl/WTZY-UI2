<?php declare(strict_types=1);
/**
 * @file app/Models/UserAuthModel.php
 * @description 用户登录方式绑定表数据访问（账号唯一性在此层保证）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class UserAuthModel
{
    /**
     * 按登录标识查找绑定记录（手机/邮箱方式）
     */
    public static function findByIdentifier(int $authType, string $identifier): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, user_id, auth_type, identifier, is_verified
             FROM ' . Database::table('user_auths') . ' WHERE auth_type = :type AND identifier = :identifier LIMIT 1'
        );
        $stmt->execute([':type' => $authType, ':identifier' => $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function create(int $userId, int $authType, string $identifier, int $isVerified = 0): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('user_auths') . ' (user_id, auth_type, identifier, is_verified)
             VALUES (:user_id, :type, :identifier, :verified)'
        );
        $stmt->execute([
            ':user_id'    => $userId,
            ':type'       => $authType,
            ':identifier' => $identifier,
            ':verified'   => $isVerified,
        ]);
    }

    /**
     * 某用户指定登录方式的绑定（后台改手机/邮箱时用于同步）
     */
    public static function findByUser(int $userId, int $authType): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, identifier FROM ' . Database::table('user_auths') . '
             WHERE user_id = :user_id AND auth_type = :type LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId, ':type' => $authType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 同步登录标识：登录校验以本表为准，改了 users.phone/email 必须同时更新此处，
     * 否则用户仍只能用旧账号登录
     */
    public static function updateIdentifier(int $id, string $identifier): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('user_auths') . ' SET identifier = :identifier WHERE id = :id'
        );
        $stmt->execute([':identifier' => $identifier, ':id' => $id]);
    }
}
