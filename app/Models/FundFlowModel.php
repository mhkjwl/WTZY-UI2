<?php declare(strict_types=1);
/**
 * @file app/Models/FundFlowModel.php
 * @description 资金流水与账户余额变动（唯一账本入口：余额只允许经此模型流水化变更）
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

namespace App\Models;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class FundFlowModel
{
    /**
     * 记一笔流水并同步变更余额（事务 + 行锁，保证账实一致）
     * 流水类型：1充值 2运费支付 3退款 4仓储费 5理赔赔偿 6人工调整 7提现 8返利
     */
    public static function record(
        int $userId,
        int $flowType,
        int $direction,
        float $amount,
        ?int $bizType,
        ?string $bizNo,
        ?int $operatorId,
        string $remark
    ): void {
        if ($amount <= 0) {
            throw new RuntimeException('金额必须大于 0');
        }
        $pdo = Database::pdo();
        // 事务感知：调用方已在事务中时复用其事务，避免嵌套开启导致提交失控
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $lock = $pdo->prepare('SELECT id, balance FROM ' . Database::table('user_accounts') . ' WHERE user_id = :uid FOR UPDATE');
            $lock->execute([':uid' => $userId]);
            $account = $lock->fetch(PDO::FETCH_ASSOC);
            if ($account === false) {
                throw new RuntimeException('用户账户不存在');
            }
            $before = (float) $account['balance'];
            $after = $direction === 1 ? $before + $amount : $before - $amount;
            if ($after < 0) {
                throw new RuntimeException('余额不足，当前余额 ' . number_format($before, 2));
            }
            $upd = $pdo->prepare('UPDATE ' . Database::table('user_accounts') . ' SET balance = :b WHERE id = :id');
            $upd->execute([':b' => $after, ':id' => $account['id']]);
            if ($direction === 1) {
                $pdo->prepare('UPDATE ' . Database::table('user_accounts') . ' SET total_recharge = total_recharge + :a WHERE id = :id')
                    ->execute([':a' => $flowType === 1 ? $amount : 0, ':id' => $account['id']]);
            } else {
                $pdo->prepare('UPDATE ' . Database::table('user_accounts') . ' SET total_consume = total_consume + :a WHERE id = :id')
                    ->execute([':a' => $amount, ':id' => $account['id']]);
            }
            $ins = $pdo->prepare(
                'INSERT INTO ' . Database::table('fund_flows') . '
                 (flow_no, user_id, flow_type, direction, amount, balance_before, balance_after, biz_type, biz_no, operator_id, remark)
                 VALUES (:no, :uid, :type, :dir, :amount, :before, :after, :biz_type, :biz_no, :oid, :remark)'
            );
            // 流水号 = 秒级时间 + 4 位随机尾号；同一秒内极小概率撞号，由唯一索引兜底并换号重试
            for ($attempt = 1; ; $attempt++) {
                try {
                    $ins->execute([
                        ':no' => 'FF' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                        ':uid' => $userId, ':type' => $flowType, ':dir' => $direction, ':amount' => $amount,
                        ':before' => $before, ':after' => $after, ':biz_type' => $bizType, ':biz_no' => $bizNo,
                        ':oid' => $operatorId, ':remark' => $remark,
                    ]);
                    break;
                } catch (PDOException $e) {
                    if ($attempt >= 3 || (int) ($e->errorInfo[1] ?? 0) !== 1062) {
                        throw $e;
                    }
                }
            }
            if ($ownTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * 流水检索（可按用户/类型过滤）
     * @return array{rows: array, total: int}
     */
    public static function search(int $userId, int $flowType, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];
        if ($userId > 0) {
            $where[] = 'f.user_id = :uid';
            $params[':uid'] = $userId;
        }
        if ($flowType > 0) {
            $where[] = 'f.flow_type = :ft';
            $params[':ft'] = $flowType;
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('fund_flows') . " f WHERE {$whereSql}");
        $countStmt->execute($params);
        $listStmt = $pdo->prepare(
            'SELECT f.id, f.flow_no, f.user_id, u.user_no, u.nickname, f.flow_type, f.direction, f.amount,
                    f.balance_before, f.balance_after, f.biz_type, f.biz_no, f.remark, f.created_at
             FROM ' . Database::table('fund_flows') . ' f
             LEFT JOIN ' . Database::table('users') . " u ON u.id = f.user_id
             WHERE {$whereSql} ORDER BY f.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $listStmt->execute($params);
        return ['rows' => $listStmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $countStmt->fetchColumn()];
    }

    /**
     * 财务总览统计
     */
    public static function overview(): array
    {
        $pdo = Database::pdo();
        return [
            'total_balance' => (float) $pdo->query('SELECT IFNULL(SUM(balance),0) FROM ' . Database::table('user_accounts'))->fetchColumn(),
            'total_recharge' => (float) $pdo->query('SELECT IFNULL(SUM(amount),0) FROM ' . Database::table('fund_flows') . ' WHERE flow_type = 1 AND direction = 1')->fetchColumn(),
            'total_consume' => (float) $pdo->query('SELECT IFNULL(SUM(amount),0) FROM ' . Database::table('fund_flows') . ' WHERE direction = 2')->fetchColumn(),
            'total_refund' => (float) $pdo->query('SELECT IFNULL(SUM(amount),0) FROM ' . Database::table('fund_flows') . ' WHERE flow_type = 3 AND direction = 1')->fetchColumn(),
        ];
    }
}
