<?php declare(strict_types=1);
/**
 * @file app/Models/ClaimModel.php
 * @description 理赔申请：提交、我的理赔列表、详情
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class ClaimModel
{
    /** 类型 1破损 2丢失 3缺件 4错发 5其他 */
    public const TYPES = [1 => '破损', 2 => '丢失', 3 => '缺件', 4 => '错发', 5 => '其他'];

    /** 状态 0待受理 1处理中 2初审通过(待财务审核) 3已驳回 4已赔付 5财务已审(待管理员终审) 6管理员已审(待赔付) */
    public const STATUS = [0 => '待受理', 1 => '处理中', 2 => '初审通过', 3 => '已驳回', 4 => '已赔付', 5 => '财务已审', 6 => '管理员已审'];

    /**
     * @return array{rows: array, total: int}
     */
    public static function search(int $userId, int $status, int $limit, int $offset): array
    {
        $where = ['c.user_id = :uid'];
        $params = [':uid' => $userId];
        if ($status >= 0) {
            $where .= ' AND c.status = :status';
            $params[':status'] = $status;
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $count = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('claims') . " c WHERE {$whereSql}");
        $count->execute($params);
        $list = $pdo->prepare(
            'SELECT c.id, c.claim_no, c.claim_type, c.amount, c.approved_amount, c.description, c.status,
                    c.handle_remark, c.handled_at, c.created_at, w.waybill_no
             FROM ' . Database::table('claims') . ' c
             LEFT JOIN ' . Database::table('waybills') . " w ON w.id = c.waybill_id
             WHERE {$whereSql} ORDER BY c.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $list->execute($params);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    public static function findForUser(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT c.*, w.waybill_no, p.package_no
             FROM ' . Database::table('claims') . ' c
             LEFT JOIN ' . Database::table('waybills') . ' w ON w.id = c.waybill_id
             LEFT JOIN ' . Database::table('packages') . ' p ON p.id = c.package_id
             WHERE c.id = :id AND c.user_id = :uid LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function create(int $userId, array $data): int
    {
        $pdo = Database::pdo();
        $claimNo = '';
        for ($i = 0; $i < 5; $i++) {
            $candidate = 'CL' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $check = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('claims') . ' WHERE claim_no = :no');
            $check->execute([':no' => $candidate]);
            if ((int) $check->fetchColumn() === 0) {
                $claimNo = $candidate;
                break;
            }
        }
        if ($claimNo === '') {
            throw new RuntimeException('理赔单号生成失败，请稍后重试');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO ' . Database::table('claims') . '
             (claim_no, user_id, waybill_id, package_id, claim_type, amount, description, images, status)
             VALUES (:no, :uid, :wid, :pid, :type, :amount, :desc, :images, 0)'
        );
        $stmt->execute([
            ':no'     => $claimNo,
            ':uid'    => $userId,
            ':wid'    => $data['waybill_id'] > 0 ? $data['waybill_id'] : null,
            ':pid'    => $data['package_id'] > 0 ? $data['package_id'] : null,
            ':type'   => $data['claim_type'],
            ':amount' => $data['amount'],
            ':desc'   => $data['description'],
            ':images' => json_encode($data['images'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function counts(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('claims') . ' WHERE user_id = :uid GROUP BY status'
        );
        $stmt->execute([':uid' => $userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'c', 'status');
    }

    // ── 后台处理（理赔审核 / 赔付）──────────────────────────

    /**
     * 后台理赔检索（跨全部用户，可按状态过滤）
     * @return array{rows: array, total: int}
     */
    public static function adminSearch(int $status, int $limit, int $offset): array
    {
        $where = '1=1';
        $params = [];
        if ($status >= 0) {
            $where = 'c.status = :status';
            $params[':status'] = $status;
        }
        $pdo = Database::pdo();
        $count = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('claims') . " c WHERE {$where}");
        $count->execute($params);
        $list = $pdo->prepare(
            'SELECT c.id, c.claim_no, c.user_id, u.user_no, u.nickname, c.claim_type, c.amount, c.approved_amount,
                    c.description, c.status, c.handle_remark, c.handled_at, c.created_at, w.waybill_no
             FROM ' . Database::table('claims') . ' c
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = c.user_id
             LEFT JOIN ' . Database::table('waybills') . " w ON w.id = c.waybill_id
             WHERE {$where} ORDER BY c.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $list->execute($params);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    /**
     * 各状态理赔数（后台待办徽章用）
     */
    public static function adminCounts(): array
    {
        $rows = Database::pdo()->query(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('claims') . ' GROUP BY status'
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'c', 'status');
    }

    public static function adminFind(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT c.*, u.user_no, u.nickname, w.waybill_no
             FROM ' . Database::table('claims') . ' c
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = c.user_id
             LEFT JOIN ' . Database::table('waybills') . ' w ON w.id = c.waybill_id
             WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 受理：待受理 → 处理中
     */
    public static function accept(int $id, int $adminId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('claims') . ' SET status = 1 WHERE id = :id AND status = 0'
        );
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('理赔单状态已变化，请刷新后重试');
        }
    }

    /**
     * 初审：处理中 → 初审通过，登记核定赔付金额（金额 > 0 才可进入财务审核）
     */
    public static function review(int $id, float $approvedAmount, string $remark): void
    {
        if ($approvedAmount <= 0) {
            throw new InvalidArgumentException('核定赔付金额必须大于 0（如无需赔付请直接驳回）');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('claims') . '
             SET status = 2, approved_amount = :amt, handle_remark = :remark, handled_at = NOW()
             WHERE id = :id AND status = 1'
        );
        $stmt->execute([':amt' => $approvedAmount, ':remark' => $remark, ':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('仅处理中状态的理赔单可初审');
        }
    }

    /**
     * 财务审核：初审通过(2) → 财务已审(5)
     */
    public static function financeAudit(int $id, int $adminId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('claims') . '
             SET status = 5, finance_approved_at = NOW(), finance_approved_by = :aid
             WHERE id = :id AND status = 2'
        );
        $stmt->execute([':aid' => $adminId, ':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('仅初审通过状态的理赔单可进行财务审核');
        }
    }

    /**
     * 管理员终审：财务已审(5) → 管理员已审(6)
     */
    public static function adminAudit(int $id, int $adminId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('claims') . '
             SET status = 6, admin_approved_at = NOW(), admin_approved_by = :aid
             WHERE id = :id AND status = 5'
        );
        $stmt->execute([':aid' => $adminId, ':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('仅财务已审状态的理赔单可进行管理员终审');
        }
    }

    /**
     * 驳回：待受理/处理中/初审通过 → 已驳回
     */
    public static function reject(int $id, string $remark): void
    {
        if ($remark === '') {
            throw new InvalidArgumentException('请填写驳回原因');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('claims') . " SET status = 3, handle_remark = :remark, handled_at = NOW()
             WHERE id = :id AND status IN (0, 1, 2)"
        );
        $stmt->execute([':remark' => $remark, ':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('该理赔单当前状态不可驳回');
        }
    }

    /**
     * 赔付：管理员已审(6) → 已赔付(4)，按核定金额给用户余额入账（理赔赔偿流水）
     * 流水失败时回滚理赔状态为管理员已审，管理员可重试赔付
     */
    public static function payOut(int $id, int $operatorId): void
    {
        $claim = self::adminFind($id);
        if ($claim === null) {
            throw new RuntimeException('理赔单不存在');
        }
        if ((int) $claim['status'] !== 6) {
            throw new RuntimeException('仅管理员已审状态的理赔单可执行赔付');
        }
        if ((float) $claim['approved_amount'] <= 0) {
            throw new RuntimeException('核定赔付金额异常，请先重新审核');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('claims') . ' SET status = 4 WHERE id = :id AND status = 6'
        );
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('理赔单状态已变化，请刷新后重试');
        }
        try {
            FundFlowModel::record(
                (int) $claim['user_id'],
                5,
                1,
                (float) $claim['approved_amount'],
                3,
                (string) $claim['claim_no'],
                $operatorId,
                '理赔赔付：' . self::TYPES[(int) $claim['claim_type']]
            );
        } catch (Throwable $e) {
            Database::pdo()->prepare('UPDATE ' . Database::table('claims') . ' SET status = 6 WHERE id = :id')
                ->execute([':id' => $id]);
            throw new RuntimeException('赔付入账失败：' . $e->getMessage());
        }
    }
}
