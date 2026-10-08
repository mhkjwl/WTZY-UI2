<?php declare(strict_types=1);
/**
 * @file app/Models/ValueAddedServiceModel.php
 * @description 增值服务字典数据访问（下单页勾选项的唯一来源，后台「增值服务」页维护）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class ValueAddedServiceModel
{
    /** 计价方式：决定勾选该服务时费用按什么口径乘算 */
    public const PRICE_MODES = [1 => '按次', 2 => '按包裹', 3 => '按运单'];

    /** 服务代码长度上限（与库中 VARCHAR(32) 对齐） */
    private const CODE_LENGTH = 16;

    /**
     * 下单页可选服务：仅启用项，按排序值升序
     */
    public static function enabled(): array
    {
        $stmt = Database::pdo()->query(
            'SELECT id, service_code, name, description, price, price_mode
             FROM ' . Database::table('value_added_services') . '
             WHERE status = 1 ORDER BY sort ASC, id ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 后台维护列表：含停用项，停用项排在启用项之后便于先看到在售服务
     */
    public static function all(): array
    {
        $stmt = Database::pdo()->query(
            'SELECT id, service_code, name, description, price, price_mode, status, sort, created_at,
                    (SELECT COUNT(*) FROM ' . Database::table('waybill_services') . ' ws WHERE ws.service_id = s.id) AS used
             FROM ' . Database::table('value_added_services') . ' s
             ORDER BY status DESC, sort ASC, id ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 按 ID 批量取「启用中」的服务：用户提交的勾选值不可信，
     * 停用服务即使被伪造进请求也不参与计费
     * @param array<int, int> $ids
     */
    public static function findEnabledByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach (array_values($ids) as $i => $id) {
            $key = ':s' . $i;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id, name, price, price_mode FROM ' . Database::table('value_added_services') . '
             WHERE status = 1 AND id IN (' . implode(',', $placeholders) . ')'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, service_code, name, description, price, price_mode, status, sort
             FROM ' . Database::table('value_added_services') . ' WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 同名判重：同名服务会让用户在勾选卡片上无法区分，编辑时排除自身
     */
    public static function nameExists(string $name, int $exceptId = 0): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('value_added_services') . ' WHERE name = :n AND id <> :id'
        );
        $stmt->execute([':n' => $name, ':id' => $exceptId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * 该服务被多少张运单勾选过（删除前置校验，避免历史单据失去关联）
     */
    public static function usedCount(int $id): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('waybill_services') . ' WHERE service_id = :id'
        );
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 后台页头统计
     * @return array{total: int, active: int, disabled: int, used: int}
     */
    public static function stats(): array
    {
        $pdo = Database::pdo();
        $table = Database::table('value_added_services');
        return [
            'total'    => (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(),
            'active'   => (int) $pdo->query('SELECT COUNT(*) FROM ' . $table . ' WHERE status = 1')->fetchColumn(),
            'disabled' => (int) $pdo->query('SELECT COUNT(*) FROM ' . $table . ' WHERE status = 0')->fetchColumn(),
            'used'     => (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::table('waybill_services'))->fetchColumn(),
        ];
    }

    /**
     * 新建服务：服务代码供内部留痕，由系统生成，不占用运营的填写成本
     */
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . Database::table('value_added_services') . '
             (service_code, name, description, price_mode, price, status, sort)
             VALUES (:code, :name, :desc, :mode, :price, :status, :sort)'
        );
        $stmt->execute([
            ':code'   => self::generateCode(),
            ':name'   => $data['name'],
            ':desc'   => $data['description'] !== '' ? $data['description'] : null,
            ':mode'   => $data['price_mode'],
            ':price'  => $data['price'],
            ':status' => $data['status'],
            ':sort'   => $data['sort'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * 修改服务：服务代码不变，历史运单已存名称与价格快照，改价只影响后续下单
     */
    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('value_added_services') . '
             SET name = :name, description = :desc, price_mode = :mode, price = :price, status = :status, sort = :sort
             WHERE id = :id'
        );
        $stmt->execute([
            ':name'   => $data['name'],
            ':desc'   => $data['description'] !== '' ? $data['description'] : null,
            ':mode'   => $data['price_mode'],
            ':price'  => $data['price'],
            ':status' => $data['status'],
            ':sort'   => $data['sort'],
            ':id'     => $id,
        ]);
    }

    public static function toggle(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('value_added_services') . ' SET status = 1 - status WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * 物理删除：仅用于从未被勾选过的服务，调用前须经 usedCount 校验
     */
    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM ' . Database::table('value_added_services') . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * 生成未被占用的服务代码（随机不重试上限 5 次，冲突概率极低）
     */
    private static function generateCode(): string
    {
        $pdo = Database::pdo();
        for ($i = 0; $i < 5; $i++) {
            $code = 'svc' . bin2hex(random_bytes(intdiv(self::CODE_LENGTH - 3, 2)));
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('value_added_services') . ' WHERE service_code = :c');
            $stmt->execute([':c' => $code]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $code;
            }
        }
        throw new RuntimeException('服务代码生成失败，请稍后重试');
    }
}
