<?php declare(strict_types=1);
/**
 * @file app/Models/UserAddressModel.php
 * @description 用户收货地址（转运目的地）数据访问
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class UserAddressModel
{
    public static function listForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, receiver_name, receiver_phone, country, state, city, district, address1, address2,
                    zip_code, is_default, created_at
             FROM ' . Database::table('user_addresses') . '
             WHERE user_id = :uid AND deleted_at IS NULL
             ORDER BY is_default DESC, id DESC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findForUser(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM ' . Database::table('user_addresses') . '
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 新增或更新；设为默认时先清掉该用户其它默认地址
     */
    public static function save(int $userId, array $data, int $id = 0): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE ' . Database::table('user_addresses') . '
                     SET receiver_name = :name, receiver_phone = :phone, country = :country, state = :state,
                         city = :city, district = :district, address1 = :a1, address2 = :a2, zip_code = :zip,
                         is_default = :def
                     WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
                );
                $stmt->execute(self::bind($data, [':id' => $id, ':uid' => $userId]));
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO ' . Database::table('user_addresses') . '
                     (user_id, receiver_name, receiver_phone, country, state, city, district, address1, address2, zip_code, is_default)
                     VALUES (:uid, :name, :phone, :country, :state, :city, :district, :a1, :a2, :zip, :def)'
                );
                $stmt->execute(self::bind($data, [':uid' => $userId]));
                $id = (int) $pdo->lastInsertId();
            }
            if ($data['is_default'] === 1) {
                $pdo->prepare('UPDATE ' . Database::table('user_addresses') . ' SET is_default = 0 WHERE user_id = :uid AND id <> :id')
                    ->execute([':uid' => $userId, ':id' => $id]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $id;
    }

    /**
     * 软删除本人收货地址
     * @return bool 是否确有本人的地址被删除
     */
    public static function softDelete(int $id, int $userId): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('user_addresses') . ' SET deleted_at = NOW()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        return $stmt->rowCount() === 1;
    }

    private static function bind(array $data, array $extra): array
    {
        return array_merge($extra, [
            ':name'    => $data['receiver_name'],
            ':phone'   => $data['receiver_phone'],
            ':country' => $data['country'],
            ':state'   => $data['state'],
            ':city'    => $data['city'],
            ':district' => $data['district'],
            ':a1'      => $data['address1'],
            ':a2'      => $data['address2'],
            ':zip'     => $data['zip_code'],
            ':def'     => $data['is_default'],
        ]);
    }
}
