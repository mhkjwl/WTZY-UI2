<?php declare(strict_types=1);
/**
 * @file app/Models/WarehouseAddressModel.php
 * @description 用户海外仓收货地址数据访问（每用户每仓库一条）
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class WarehouseAddressModel
{
    /**
     * 用户的海外仓收货地址：实时按仓库表组装（仓库调整后会员端即同步），
     * 用户地址记录仅提供默认仓标记与失效状态
     */
    public static function listForUser(int $userId, string $userCode): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT w.id, w.name AS warehouse_name, w.code AS warehouse_code, w.status AS warehouse_status,
                    w.country, w.state, w.city, w.address, w.zip_code, w.contact_phone AS phone,
                    COALESCE(a.is_default, 0) AS is_default, COALESCE(a.status, 1) AS status
             FROM ' . Database::table('warehouses') . ' w
             LEFT JOIN ' . Database::table('warehouse_addresses') . ' a ON a.warehouse_id = w.id AND a.user_id = :uid
             WHERE w.deleted_at IS NULL
             ORDER BY is_default DESC, w.sort ASC, w.id ASC'
        );
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['user_code'] = $userCode;
            $row['full_name'] = self::recipientName((string) $row['warehouse_code'], $userCode);
        }
        unset($row);
        return $rows;
    }

    public static function existsForUser(int $userId, int $warehouseId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM ' . Database::table('warehouse_addresses')
            . ' WHERE user_id = :user_id AND warehouse_id = :warehouse_id LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId, ':warehouse_id' => $warehouseId]);
        return $stmt->fetchColumn() !== false;
    }

    public static function createForUser(int $userId, int $warehouseId, string $userCode, array $warehouse): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('warehouse_addresses') . '
             (user_id, warehouse_id, user_code, full_name, phone, country, state, city, address, zip_code, is_default, status)
             VALUES (:user_id, :warehouse_id, :user_code, :full_name, :phone, :country, :state, :city, :address, :zip_code, 1, 1)'
        );
        $stmt->execute([
            ':user_id'       => $userId,
            ':warehouse_id'  => $warehouseId,
            ':user_code'     => $userCode,
            ':full_name'     => self::recipientName((string) $warehouse['code'], $userCode),
            ':phone'         => $warehouse['contact_phone'],
            ':country'       => $warehouse['country'],
            ':state'         => $warehouse['state'],
            ':city'          => $warehouse['city'],
            ':address'       => $warehouse['address'],
            ':zip_code'      => $warehouse['zip_code'],
        ]);
    }

    /**
     * 面单收件人名称：前缀 + 仓库编码 + 用户编号，供仓库扫面单归属包裹
     * 前缀由后台「站点信息 → 海外仓地址前缀」配置，仅允许字母数字，留空回落 WTZY
     */
    public static function recipientName(string $warehouseCode, string $userCode): string
    {
        $prefix = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', SettingsModel::get('warehouse_address_prefix', 'WTZY')));
        if ($prefix === '') {
            $prefix = 'WTZY';
        }
        return $prefix . '-' . $warehouseCode . ' (' . $userCode . ')';
    }
}
