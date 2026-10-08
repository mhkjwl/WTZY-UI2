<?php declare(strict_types=1);
/**
 * @file app/Models/WarehouseAddressModel.php
 * @description 用户海外仓收货地址数据访问（每用户每仓库一条）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class WarehouseAddressModel
{
    /**
     * 用户的全部海外仓专属收货地址（含仓库信息，用于会员中心展示与复制）
     */
    public static function listForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.id, a.user_code, a.full_name, a.phone, a.country, a.state, a.city, a.address, a.zip_code,
                    a.is_default, a.status, w.name AS warehouse_name, w.code AS warehouse_code
             FROM ' . Database::table('warehouse_addresses') . ' a
             JOIN ' . Database::table('warehouses') . ' w ON w.id = a.warehouse_id
             WHERE a.user_id = :uid
             ORDER BY a.is_default DESC, a.id ASC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
