<?php declare(strict_types=1);
/**
 * @file app/Models/RoleModel.php
 * @description 角色与 RBAC 权限矩阵数据访问
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class RoleModel
{
    /**
     * 按钮级权限点 => 所属页面权限点
     * 这类权限只控制页面内的操作按钮，页面入口本身由所属页面权限点把关；
     * 单独授予不会出现任何界面变化，角色权限页据此提示，避免「设置了权限却看不到效果」
     */
    private const BUTTON_PERM_PAGE = [
        'user.edit'           => 'user.view',
        'user.audit'          => 'user.view',
        'user.balance.adjust' => 'user.view',
        'user.risk.set'       => 'user.view',
        'order.manage'        => 'order.view',
        'channel.manage'      => 'channel.view',
        'ticket.handle'       => 'ticket.view',
    ];

    /**
     * 全部启用角色（含已授权权限数）
     */
    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT r.id, r.name, r.code, r.remark, r.status,
                    (SELECT COUNT(*) FROM ' . Database::table('role_permissions') . ' rp WHERE rp.role_id = r.id) AS perm_count
             FROM ' . Database::table('roles') . ' r ORDER BY r.id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 全部权限点（按模块组分组）
     * 按钮级权限点附带所属页面权限点（page_perm_code / page_perm_id / page_perm_name），页面权限点为 null
     */
    public static function permissionsGrouped(): array
    {
        $rows = Database::pdo()->query(
            'SELECT id, perm_code, name, group_name FROM ' . Database::table('permissions') . ' WHERE status = 1 ORDER BY sort ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $byCode = [];
        foreach ($rows as $row) {
            $byCode[$row['perm_code']] = $row;
        }
        foreach ($rows as &$row) {
            $pageCode = self::BUTTON_PERM_PAGE[$row['perm_code']] ?? null;
            $page = $pageCode === null ? null : ($byCode[$pageCode] ?? null);
            $row['page_perm_code'] = $page['perm_code'] ?? null;
            $row['page_perm_id'] = $page === null ? null : (int) $page['id'];
            $row['page_perm_name'] = $page['name'] ?? null;
        }
        unset($row);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['group_name']][] = $row;
        }
        return $grouped;
    }

    /**
     * 指定角色已授权的权限点 ID 集合
     * @return int[]
     */
    public static function permissionIdsForRole(int $roleId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT permission_id FROM ' . Database::table('role_permissions') . ' WHERE role_id = :role_id'
        );
        $stmt->execute([':role_id' => $roleId]);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_id'));
    }

    /**
     * 保存角色权限矩阵：整体替换该角色的授权集合
     * @param int[] $permissionIds
     */
    public static function saveMatrix(int $roleId, array $permissionIds): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM ' . Database::table('role_permissions') . ' WHERE role_id = :role_id')
                ->execute([':role_id' => $roleId]);
            $ins = $pdo->prepare(
                'INSERT IGNORE INTO ' . Database::table('role_permissions') . ' (role_id, permission_id) VALUES (:role_id, :pid)'
            );
            foreach ($permissionIds as $pid) {
                $ins->execute([':role_id' => $roleId, ':pid' => (int) $pid]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function findByCode(string $code): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT id, name, code, status FROM ' . Database::table('roles') . ' WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
