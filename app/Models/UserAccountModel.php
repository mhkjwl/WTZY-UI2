<?php declare(strict_types=1);
/**
 * @file app/Models/UserAccountModel.php
 * @description 用户余额账户数据访问
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */

namespace App\Models;

use App\Core\Database;

final class UserAccountModel
{
    /**
     * 开户（余额为零；任何后续资金变动必须经资金流水表记录）
     */
    public static function open(int $userId): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('user_accounts') . ' (user_id) VALUES (:user_id)'
        );
        $stmt->execute([':user_id' => $userId]);
    }
}
