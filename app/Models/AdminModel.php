<?php declare(strict_types=1);
/**
 * @file app/Models/AdminModel.php
 * @description 员工账号数据访问（含角色联查与 RBAC 权限加载、登录账号与密码维护）
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class AdminModel
{
    /**
     * 登录用联查：员工 + 角色代码/状态，一次取全
     */
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.id, a.username, a.password_hash, a.real_name, a.role_id, a.warehouse_id, a.staff_type,
                    a.status, a.deleted_at, r.code AS role_code, r.name AS role_name, r.status AS role_status
             FROM ' . Database::table('admins') . ' a
             JOIN ' . Database::table('roles') . ' r ON r.id = a.role_id
             WHERE a.username = :username LIMIT 1'
        );
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 角色的全部启用权限点代码（登录时加载进会话，端内逐请求判断）
     * @return string[]
     */
    /**
     * 角色当前生效的权限点
     * 同时要求权限点本身与角色都处于启用状态：角色被停用后立即失去权限，无需等待重新登录
     */
    public static function permissionCodesForRole(int $roleId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.perm_code FROM ' . Database::table('role_permissions') . ' rp
             JOIN ' . Database::table('permissions') . ' p ON p.id = rp.permission_id
             JOIN ' . Database::table('roles') . ' r ON r.id = rp.role_id
             WHERE rp.role_id = :role_id AND p.status = 1 AND r.status = 1'
        );
        $stmt->execute([':role_id' => $roleId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'perm_code');
    }

    public static function updateLoginInfo(int $id, string $ip): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('admins') . ' SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id'
        );
        $stmt->execute([':ip' => $ip, ':id' => $id]);
    }

    /**
     * 员工列表（联角色与仓库，供系统设置模块展示）
     */
    public static function search(string $keyword, int $limit, int $offset): array
    {
        $pdo = Database::pdo();
        $where = 'a.deleted_at IS NULL';
        $params = [];
        if ($keyword !== '') {
            $where .= ' AND ' . KeywordFilter::any(['a.username', 'a.real_name'], $params, $keyword);
        }
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM " . Database::table('admins') . " a WHERE {$where}");
        $countStmt->execute($params);
        $listStmt = $pdo->prepare(
            'SELECT a.id, a.username, a.real_name, a.phone, a.role_id, a.warehouse_id, a.staff_type, a.status,
                    a.last_login_at, r.code AS role_code, r.name AS role_name, w.name AS warehouse_name
             FROM ' . Database::table('admins') . ' a
             JOIN ' . Database::table('roles') . ' r ON r.id = a.role_id
             LEFT JOIN ' . Database::table('warehouses') . " w ON w.id = a.warehouse_id
             WHERE {$where} ORDER BY a.id ASC LIMIT {$limit} OFFSET {$offset}"
        );
        $listStmt->execute($params);
        return ['rows' => $listStmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $countStmt->fetchColumn()];
    }

    public static function createStaff(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('admins') . ' (username, password_hash, real_name, phone, role_id, warehouse_id, staff_type, status)
             VALUES (:username, :hash, :real_name, :phone, :role_id, :warehouse_id, :staff_type, 1)'
        );
        $stmt->execute([
            ':username'     => $data['username'],
            ':hash'         => password_hash($data['password'], PASSWORD_DEFAULT),
            ':real_name'    => $data['real_name'],
            ':phone'        => $data['phone'] !== '' ? $data['phone'] : null,
            ':role_id'      => $data['role_id'],
            ':warehouse_id' => $data['warehouse_id'] > 0 ? $data['warehouse_id'] : null,
            ':staff_type'   => $data['staff_type'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * 单个员工详情（编辑弹窗回填用，含角色/仓库名称）
     * 也用于每个请求回读权威资料：角色、仓库、状态变更后无需重新登录即可生效
     */
    public static function findStaff(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.id, a.username, a.real_name, a.phone, a.role_id, a.warehouse_id, a.staff_type, a.status,
                    r.code AS role_code, r.name AS role_name, r.status AS role_status, w.name AS warehouse_name
             FROM ' . Database::table('admins') . ' a
             JOIN ' . Database::table('roles') . ' r ON r.id = a.role_id
             LEFT JOIN ' . Database::table('warehouses') . ' w ON w.id = a.warehouse_id
             WHERE a.id = :id AND a.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 某仓库的仓库端在职员工（任务指派的执行人候选）
     */
    public static function warehouseStaff(int $warehouseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.id, a.username, a.real_name, r.name AS role_name
             FROM ' . Database::table('admins') . ' a
             LEFT JOIN ' . Database::table('roles') . ' r ON r.id = a.role_id
             WHERE a.deleted_at IS NULL AND a.status = 1 AND a.staff_type = 2 AND a.warehouse_id = :wh
             ORDER BY a.id ASC'
        );
        $stmt->execute([':wh' => $warehouseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 更新员工资料与角色归属（不涉及密码，密码走独立的重置流程）
     */
    public static function updateStaff(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('admins') . '
             SET real_name = :real_name, phone = :phone, role_id = :role_id,
                 warehouse_id = :warehouse_id, staff_type = :staff_type
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':real_name'    => $data['real_name'],
            ':phone'        => $data['phone'] !== '' ? $data['phone'] : null,
            ':role_id'      => $data['role_id'],
            ':warehouse_id' => $data['warehouse_id'] > 0 ? $data['warehouse_id'] : null,
            ':staff_type'   => $data['staff_type'],
            ':id'           => $id,
        ]);
    }

    /**
     * 修改员工登录账号（登录标识，全表唯一；调用方需先完成格式与占用校验）
     */
    public static function updateUsername(int $id, string $username): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('admins') . ' SET username = :username WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':username' => $username, ':id' => $id]);
    }

    public static function toggleStatus(int $id): int    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('admins') . ' SET status = 1 - status WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return (int) Database::pdo()->query('SELECT status FROM ' . Database::table('admins') . ' WHERE id = ' . (int) $id)->fetchColumn();
    }

    public static function resetPassword(int $id, string $passwordHash): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('admins') . ' SET password_hash = :hash WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':hash' => $passwordHash, ':id' => $id]);
    }
}
