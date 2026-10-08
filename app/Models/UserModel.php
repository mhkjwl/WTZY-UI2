<?php declare(strict_types=1);
/**
 * @file app/Models/UserModel.php
 * @description 用户表数据访问（前缀由安装配置 DB_PREFIX 决定）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

namespace App\Models;

use App\Core\Database;
use App\Enums\Status;
use PDO;

final class UserModel
{
    /**
     * 用户展示名：优先用户名，未设置用户名（手机号/邮箱注册）时回落到用户编号
     * 昵称非必填，故不参与展示名，避免出现空白标识
     */
    public static function displayName(array $row): string
    {
        $username = trim((string) ($row['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }
        return (string) ($row['user_no'] ?? '');
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, user_no, username, user_code, nickname, phone, email, password_hash, member_level, status, risk_level,
                    points, real_name, id_card_no, kyc_status, kyc_at, kyc_remark, inviter_id, created_at, last_login_at
             FROM ' . Database::table('users') . ' WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 按用户编号查找（仓库端无主件指定归属时按编号定位用户）
     */
    public static function findByUserNo(string $userNo): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, user_no, username, user_code, nickname, phone, email, member_level, status
             FROM ' . Database::table('users') . ' WHERE user_no = :no AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':no' => trim($userNo)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 按入库识别码查找（仓库端凭面单上的识别码确认包裹归属）
     */
    public static function findByCode(string $code): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, user_no, username, user_code, nickname, phone, email, member_level, status
             FROM ' . Database::table('users') . ' WHERE user_code = :code AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':code' => strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 按用户名查找（用户名可用于登录与找回密码）
     */
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, user_no, username, user_code, nickname, phone, email, password_hash, member_level, status, risk_level,
                    points, real_name, id_card_no, kyc_status, kyc_at, kyc_remark, inviter_id, created_at, last_login_at
             FROM ' . Database::table('users') . ' WHERE username = :u AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('users') . ' (user_no, username, user_code, nickname, phone, email, password_hash, status)
             VALUES (:user_no, :username, :user_code, :nickname, :phone, :email, :password_hash, 1)'
        );
        $stmt->execute([
            ':user_no'        => $data['user_no'],
            ':username'       => $data['username'] !== '' ? $data['username'] : null,
            ':user_code'      => $data['user_code'],
            ':nickname'       => $data['nickname'],
            ':phone'          => $data['phone'],
            ':email'          => $data['email'],
            ':password_hash'  => $data['password_hash'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function updateLoginInfo(int $id, string $ip): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('users') . ' SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id'
        );
        $stmt->execute([':ip' => $ip, ':id' => $id]);
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('users') . ' SET password_hash = :hash WHERE id = :id'
        );
        $stmt->execute([':hash' => $passwordHash, ':id' => $id]);
    }

    /**
     * 变更登录用户名（登录账号之一，唯一性由调用方的预校验与 uk_username 唯一索引共同保证）
     */
    public static function updateUsername(int $id, string $username): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('users') . ' SET username = :u WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':u' => $username, ':id' => $id]);
    }

    /**
     * 后台用户检索（关键词命中编号/识别码/昵称/手机/邮箱，可按状态过滤）
     * @return array{rows: array, total: int}
     */
    public static function search(string $keyword, int $status, int $limit, int $offset): array
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if ($keyword !== '') {
            $where[] = KeywordFilter::any(['u.user_no', 'u.username', 'u.user_code', 'u.nickname', 'u.phone', 'u.email'], $params, $keyword);
        }
        if ($status >= 0) {
            $where[] = 'u.status = :status';
            $params[':status'] = $status;
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('users') . " u WHERE {$whereSql}"
        );
        $countStmt->execute($params);

        $listStmt = $pdo->prepare(
            'SELECT u.id, u.user_no, u.username, u.user_code, u.nickname, u.phone, u.email, u.member_level, u.status,
                    u.risk_level, u.blacklist_reason, u.kyc_status, u.real_name, u.id_type, u.id_card_no, u.kyc_at,
                    u.created_at, u.last_login_at,
                    IFNULL(a.balance, 0) AS balance, IFNULL(a.frozen_amount, 0) AS frozen_amount
             FROM ' . Database::table('users') . ' u
             LEFT JOIN ' . Database::table('user_accounts') . " a ON a.user_id = u.id
             WHERE {$whereSql} ORDER BY u.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $listStmt->execute($params);

        return ['rows' => $listStmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $countStmt->fetchColumn()];
    }

    /**
     * 各状态用户数（状态机编码为键）
     */
    public static function statusCounts(): array
    {
        $rows = Database::pdo()->query(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('users') . ' WHERE deleted_at IS NULL GROUP BY status'
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'c', 'status');
    }

    /**
     * 账号状态写入唯一入口
     * 拉黑时一并落盘原因与时间；其余状态强制清空这两列，保证「表里存有拉黑原因」与
     * 「status = 3」始终等价，不会留下「状态正常却挂着拉黑原因」的脏数据。
     * 封禁与解除的历史以 admin_operation_logs 为准，用户表只存当前有效状态
     */
    public static function updateStatus(int $id, int $status, ?string $reason = null): void
    {
        $blacklist = $status === Status::USER_BLACKLIST;
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('users') . '
             SET status = :status,
                 blacklist_reason = :reason,
                 blacklisted_at = CASE WHEN :bl = 1 THEN NOW() ELSE NULL END
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':status' => $status,
            ':reason' => $blacklist ? $reason : null,
            ':bl'     => $blacklist ? 1 : 0,
            ':id'     => $id,
        ]);
    }

    /**
     * 黑名单身份命中查询（注册入口使用）
     * 拉黑是身份级封禁，注销账号并不解除封禁，故此处不过滤 deleted_at：
     * 否则用户可注销后拿同一手机号/邮箱重新注册来绕过处置
     */
    public static function findBlacklistedByIdentifier(int $authType, string $identifier): ?array
    {
        $column = $authType === Status::AUTH_EMAIL ? 'email' : 'phone';
        $stmt = Database::pdo()->prepare(
            'SELECT id, user_no, blacklist_reason FROM ' . Database::table('users') . "
             WHERE {$column} = :v AND status = :status LIMIT 1"
        );
        $stmt->execute([':v' => $identifier, ':status' => Status::USER_BLACKLIST]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 证件号黑名单命中查询（实名提交入口使用）
     * 被拉黑者的证件号不能再给其他账号实名，这正是「禁用」不具备的能力：
     * 禁用只停用账号本身，其证件号仍可被其他账号绑定使用
     */
    public static function findBlacklistedByIdCard(string $idNo): ?array
    {
        $idNo = strtoupper(trim($idNo));
        if ($idNo === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id, user_no, blacklist_reason FROM ' . Database::table('users') . '
             WHERE id_card_no = :v AND status = :status LIMIT 1'
        );
        $stmt->execute([':v' => $idNo, ':status' => Status::USER_BLACKLIST]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function updateRiskLevel(int $id, int $level): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('users') . ' SET risk_level = :level WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':level' => $level, ':id' => $id]);
    }

    /**
     * 后台编辑弹窗详情：基础资料 + 权益字段 + 账户余额
     */
    public static function findDetail(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT u.id, u.user_no, u.username, u.user_code, u.nickname, u.phone, u.email, u.member_level,
                    u.status, u.risk_level, u.blacklist_reason, u.blacklisted_at,
                    u.default_lang, u.default_currency, u.created_at, u.last_login_at,
                    u.points, u.real_name, u.id_type, u.id_card_no, u.id_photo_front, u.id_photo_back,
                    u.kyc_status, u.kyc_at, u.kyc_remark,
                    IFNULL(a.balance, 0) AS balance, IFNULL(a.frozen_amount, 0) AS frozen_amount
             FROM ' . Database::table('users') . ' u
             LEFT JOIN ' . Database::table('user_accounts') . " a ON a.user_id = u.id
             WHERE u.id = :id AND u.deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 更新用户资料与权益（昵称/联系方式/会员等级/账号状态/风控等级/实名状态）
     */
    public static function updateProfile(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('users') . '
             SET nickname = :nickname, phone = :phone, email = :email,
                 member_level = :member_level, status = :status, risk_level = :risk_level,
                 kyc_status = :kyc_status, kyc_remark = :kyc_remark
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':nickname'     => $data['nickname'],
            ':phone'        => $data['phone'] !== '' ? $data['phone'] : null,
            ':email'        => $data['email'] !== '' ? $data['email'] : null,
            ':member_level' => $data['member_level'],
            ':status'       => $data['status'],
            ':risk_level'   => $data['risk_level'],
            ':kyc_status'   => $data['kyc_status'],
            ':kyc_remark'   => $data['kyc_remark'] !== '' ? $data['kyc_remark'] : null,
            ':id'           => $id,
        ]);
    }

    /**
     * 实名认证审核检索（默认待审核；status=-1 表示全部已提交过的）
     * @return array{rows: array, total: int}
     */
    public static function kycSearch(string $keyword, int $status, int $limit, int $offset): array
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if ($status === -1) {
            $where[] = 'u.kyc_status > 0';
        } else {
            $where[] = 'u.kyc_status = :kstatus';
            $params[':kstatus'] = $status;
        }
        if ($keyword !== '') {
            $where[] = KeywordFilter::any(['u.user_no', 'u.username', 'u.nickname', 'u.real_name', 'u.id_card_no'], $params, $keyword, 'kkw');
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $count = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('users') . " u WHERE {$whereSql}");
        $count->execute($params);
        $list = $pdo->prepare(
            'SELECT u.id, u.user_no, u.username, u.nickname, u.real_name, u.id_type, u.id_card_no,
                    u.id_photo_front, u.id_photo_back, u.kyc_status,
                    u.kyc_at, u.kyc_remark, u.phone, u.email, u.created_at
             FROM ' . Database::table('users') . " u
             WHERE {$whereSql} ORDER BY u.kyc_at DESC, u.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $list->execute($params);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    public static function kycCounts(): array
    {
        $rows = Database::pdo()->query(
            'SELECT kyc_status, COUNT(*) AS c FROM ' . Database::table('users') . '
             WHERE deleted_at IS NULL GROUP BY kyc_status'
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'c', 'kyc_status');
    }

    /**
     * 实名审核结果（通过 2 / 驳回 3），驳回原因回写给用户；认证时间以审核通过时刻为准
     */
    public static function reviewKyc(int $id, int $status, string $remark): void
    {
        // MySQL 原生预处理下命名占位符不可复用，:s 与 :s2 需分别绑定
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('users') . '
             SET kyc_status = :s, kyc_remark = :r, kyc_at = IF(:s2 = 2, NOW(), kyc_at)
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':s' => $status, ':s2' => $status, ':r' => $remark !== '' ? $remark : null, ':id' => $id]);
    }

    /**
     * 证件号脱敏展示：保留前 3 位与后 4 位，中间以星号填充（如 110***********1234）
     */
    public static function maskIdNo(?string $no): string
    {
        $no = (string) $no;
        $len = mb_strlen($no);
        if ($len < 8) {
            return $len === 0 ? '' : $no[0] . str_repeat('*', max(1, $len - 2)) . $no[$len - 1];
        }
        return mb_substr($no, 0, 3) . str_repeat('*', $len - 7) . mb_substr($no, -4);
    }

    /**
     * 证件占用校验：其他账号以同类型同证件号处于待审核或已认证状态即视为占用；
     * 已驳回的提交不占用（驳回方本人仍可修正重提），数据库唯一索引兜底防并发
     */
    public static function kycOccupied(int $idType, string $idNo, int $exceptUserId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('users') . '
             WHERE id_type = :t AND id_card_no = :no AND id != :uid
               AND kyc_status IN (1, 2) AND deleted_at IS NULL'
        );
        $stmt->execute([':t' => $idType, ':no' => $idNo, ':uid' => $exceptUserId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * 按邮箱查用户（绑定/注册的唯一性预校验）
     */
    public static function findByEmail(?string $email): ?array
    {
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM ' . Database::table('users') . ' WHERE email = :e AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':e' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 详情（含账户），后台余额调整前校验用
     */
    public static function findWithAccount(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT u.id, u.user_no, u.nickname, u.status, u.blacklist_reason, u.deleted_at, a.id AS account_id, a.balance
             FROM ' . Database::table('users') . ' u
             LEFT JOIN ' . Database::table('user_accounts') . " a ON a.user_id = u.id
             WHERE u.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
