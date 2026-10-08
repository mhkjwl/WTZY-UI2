<?php declare(strict_types=1);
/**
 * @file app/Models/InvoiceModel.php
 * @description 发票申请：会员端提交与查询、后台检索与开票处理
 * @author AI
 * @version 0.6.2
 * @date 2026-09-16
 */

namespace App\Models;

use App\Core\Database;
use App\Core\Request;
use App\Enums\Status;
use PDO;
use RuntimeException;

final class InvoiceModel
{
    /** 状态文案统一取自 Status::INVOICE（单一事实来源，色调见 Status::invoiceTone） */
    public const STATUS = Status::INVOICE;

    /** 待开票：后台待办统计与状态流转的唯一判据 */
    public const PENDING = 0;

    /** 已开票 */
    public const ISSUED = 1;

    /** 已驳回：驳回后额度自动释放，可重新申请 */
    public const REJECTED = 2;

    /**
     * 会员端：本人申请记录
     * @return array{rows: array, total: int}
     */
    public static function search(int $userId, int $limit, int $offset): array
    {
        $pdo = Database::pdo();
        $count = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('invoices') . ' WHERE user_id = :uid');
        $count->execute([':uid' => $userId]);
        $list = $pdo->prepare(
            'SELECT id, invoice_no, title, tax_no, type, amount, email, remark, status, handle_remark, handled_at, created_at
             FROM ' . Database::table('invoices') . '
             WHERE user_id = :uid ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $list->execute([':uid' => $userId]);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    /**
     * 后台检索：跨用户查询，关键词匹配申请单号 / 抬头 / 纳税人识别号 / 用户编号 / 昵称
     * 待开票排在前面，便于客服按提交顺序处理
     * @return array{rows: array, total: int}
     */
    public static function adminSearch(int $status, string $keyword, int $limit, int $offset): array
    {
        $pdo = Database::pdo();
        $where = [];
        $args = [];
        if ($status >= 0) {
            $where[] = 'i.status = :st';
            $args[':st'] = $status;
        }
        if ($keyword !== '') {
            // 每个字段用独立占位符：PDO 的命名参数在同一语句中不可重复绑定
            $where[] = '(i.invoice_no LIKE :kw1 OR i.title LIKE :kw2 OR i.tax_no LIKE :kw3'
                . ' OR u.user_no LIKE :kw4 OR u.nickname LIKE :kw5)';
            $like = '%' . $keyword . '%';
            $args[':kw1'] = $like;
            $args[':kw2'] = $like;
            $args[':kw3'] = $like;
            $args[':kw4'] = $like;
            $args[':kw5'] = $like;
        }
        $from = ' FROM ' . Database::table('invoices') . ' i'
            . ' LEFT JOIN ' . Database::table('users') . ' u ON u.id = i.user_id'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where));

        $count = $pdo->prepare('SELECT COUNT(*)' . $from);
        $count->execute($args);
        $list = $pdo->prepare(
            'SELECT i.id, i.user_id, i.invoice_no, i.title, i.tax_no, i.type, i.amount, i.email, i.remark,
                    i.status, i.handle_remark, i.handled_at, i.created_at, u.user_no, u.nickname' . $from . '
             ORDER BY i.status ASC, i.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $list->execute($args);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    /**
     * 各状态申请数量：后台侧边栏待开票徽标与页面统计共用
     * @return array<int, int>
     */
    public static function statusCounts(): array
    {
        $rows = Database::pdo()
            ->query('SELECT status, COUNT(*) AS c FROM ' . Database::table('invoices') . ' GROUP BY status')
            ->fetchAll(PDO::FETCH_ASSOC);
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['status']] = (int) $row['c'];
        }
        return $counts;
    }

    /**
     * 已开票金额合计：对账只认已开票，待开票与已驳回不计入
     */
    public static function issuedAmount(): float
    {
        return (float) Database::pdo()
            ->query('SELECT IFNULL(SUM(amount), 0) FROM ' . Database::table('invoices')
                . ' WHERE status = ' . self::ISSUED)
            ->fetchColumn();
    }

    /**
     * 后台处理开票申请：仅「待开票」可流转为已开票或已驳回，处理意见与处理时间一并落库
     * 行锁 + 带状态条件的更新，保证客服重复提交不会造成二次处理
     */
    public static function handle(int $id, int $status, string $remark, int $adminId, string $adminName): void
    {
        if (!in_array($status, [self::ISSUED, self::REJECTED], true)) {
            throw new RuntimeException('无效的处理结果');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT status FROM ' . Database::table('invoices') . ' WHERE id = :id FOR UPDATE');
            $lock->execute([':id' => $id]);
            $current = $lock->fetchColumn();
            if ($current === false) {
                throw new RuntimeException('发票申请不存在');
            }
            if ((int) $current !== self::PENDING) {
                throw new RuntimeException('该申请已处理，无需重复操作');
            }
            $update = $pdo->prepare(
                'UPDATE ' . Database::table('invoices') . '
                 SET status = :st, handle_remark = :remark, handled_at = NOW()
                 WHERE id = :id AND status = ' . self::PENDING
            );
            $update->execute([
                ':st'     => $status,
                ':remark' => $remark !== '' ? $remark : null,
                ':id'     => $id,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('该申请已处理，无需重复操作');
            }
            // 开票与驳回属财务动作，写入员工操作日志供对账与追溯
            $pdo->prepare(
                'INSERT INTO ' . Database::table('admin_operation_logs') . '
                 (admin_id, admin_name, module, action, target_type, target_id, before_data, after_data, ip)
                 VALUES (:aid, :aname, :module, :action, :ttype, :tid, :bdata, :adata, :ip)'
            )->execute([
                ':aid'    => $adminId,
                ':aname'  => $adminName !== '' ? $adminName : '—',
                ':module' => 'finance',
                ':action' => $status === self::ISSUED ? 'invoice_issue' : 'invoice_reject',
                ':ttype'  => 'invoice',
                ':tid'    => $id,
                ':bdata'  => json_encode(['status' => self::PENDING], JSON_UNESCAPED_UNICODE),
                ':adata'  => json_encode(['status' => $status, 'remark' => $remark], JSON_UNESCAPED_UNICODE),
                ':ip'     => Request::ip(),
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function create(int $userId, array $data): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // 锁定该用户资料行，串行化同一用户的并发申请，避免同时提交绕过额度校验重复开票
            $lock = $pdo->prepare('SELECT id FROM ' . Database::table('users') . ' WHERE id = :uid FOR UPDATE');
            $lock->execute([':uid' => $userId]);
            if ($lock->fetchColumn() === false) {
                throw new RuntimeException('用户不存在');
            }
            $amount = (float) $data['amount'];
            $available = self::invoiceableAmount($userId);
            if ($amount > $available + 0.001) {
                throw new RuntimeException('开票金额不得超过可开票额度 ¥' . number_format($available, 2));
            }

            $no = '';
            for ($i = 0; $i < 5; $i++) {
                $candidate = 'IV' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $check = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('invoices') . ' WHERE invoice_no = :no');
                $check->execute([':no' => $candidate]);
                if ((int) $check->fetchColumn() === 0) {
                    $no = $candidate;
                    break;
                }
            }
            if ($no === '') {
                throw new RuntimeException('申请单号生成失败，请稍后重试');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO ' . Database::table('invoices') . '
                 (invoice_no, user_id, title, tax_no, type, amount, email, remark, status)
                 VALUES (:no, :uid, :title, :tax, :type, :amount, :email, :remark, 0)'
            );
            $stmt->execute([
                ':no'     => $no,
                ':uid'    => $userId,
                ':title'  => $data['title'],
                ':tax'    => $data['tax_no'] !== '' ? $data['tax_no'] : null,
                ':type'   => $data['type'],
                ':amount' => $amount,
                ':email'  => $data['email'] !== '' ? $data['email'] : null,
                ':remark' => $data['remark'] !== '' ? $data['remark'] : null,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 可开票额度：累计已支付充值金额，扣除已申请（待开票 0 / 已开票 1）的金额
     * 已驳回（2）的申请不占用额度，驳回后额度自动释放
     */
    public static function invoiceableAmount(int $userId): float
    {
        $pdo = Database::pdo();
        $paid = $pdo->prepare(
            'SELECT IFNULL(SUM(amount), 0) FROM ' . Database::table('recharge_orders') . '
             WHERE user_id = :uid AND status = 1'
        );
        $paid->execute([':uid' => $userId]);
        $used = $pdo->prepare(
            'SELECT IFNULL(SUM(amount), 0) FROM ' . Database::table('invoices') . '
             WHERE user_id = :uid AND status IN (0, 1)'
        );
        $used->execute([':uid' => $userId]);
        return max(0.0, (float) $paid->fetchColumn() - (float) $used->fetchColumn());
    }
}
