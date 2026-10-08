<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/ProfileController.php
 * @description 会员中心：个人资料、绑定邮箱、修改密码、实名认证（证件材料上传 + 后台审核）
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

namespace App\Controllers\Member;

use App\Core\Database;
use App\Core\Request;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\UploadService;
use PDOException;
use RuntimeException;

final class ProfileController extends BaseController
{
    public function index(): never
    {
        $this->requireLogin();
        $this->memberView('member/profile', [
            'title'    => '个人资料',
            'bindings' => $this->bindings(),
            'account'  => $this->account(),
        ], 'profile', '个人资料');
    }

    /**
     * 修改昵称
     */
    public function saveProfile(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $nickname = trim((string) Request::input('nickname', ''));
        // 昵称非必填，留空表示不设置
        if (mb_strlen($nickname) > 50) {
            $this->fail('昵称不超过 50 字');
        }
        Database::pdo()->prepare('UPDATE ' . Database::table('users') . ' SET nickname = :n WHERE id = :id')
            ->execute([':n' => $nickname, ':id' => $this->userId()]);
        $this->ok(null, '昵称已更新');
    }

    /**
     * 发送绑定邮箱验证码（仅未绑定邮箱的用户可发）
     */
    public function sendBindCode(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        if ((string) ($this->user['email'] ?? '') !== '') {
            $this->fail('您已绑定邮箱。绑定后不支持解绑或更换，如需变更请联系客服处理。');
        }
        try {
            AuthService::sendBindEmailCode($this->userId(), Request::input('email'), Request::ip());
        } catch (\InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '验证码已发送至邮箱，请查收（含垃圾邮件箱）');
    }

    /**
     * 绑定邮箱：验证码核销 + 全局唯一校验；绑定后不支持解绑与更换
     */
    public function bindEmail(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        if ((string) ($this->user['email'] ?? '') !== '') {
            $this->fail('您已绑定邮箱。绑定后不支持解绑或更换，如需变更请联系客服处理。');
        }
        try {
            AuthService::bindEmail($this->userId(), Request::input('email'), Request::input('code'));
        } catch (\InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['reload' => true], '邮箱绑定成功');
    }

    /**
     * 实名认证页
     */
    public function kyc(): never
    {
        $this->requireLogin();
        $this->memberView('member/kyc', [
            'title' => '实名认证',
        ], 'kyc', '实名认证');
    }

    /**
     * 上传实名认证材料（身份证正反面 / 护照照片），返回可直接访问的图片地址
     */
    public function uploadKycPhoto(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            $this->fail('请选择要上传的照片');
        }
        try {
            $url = UploadService::storeFile($_FILES['file'], 'kyc', 'kyc' . $this->userId());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['url' => $url], '照片已上传');
    }

    /**
     * 实名认证提交（后台审核）
     * 证件类型：1 中国身份证（正反面 2 张） 2 中国护照（照片 1 张）
     * 证件号「类型 + 号码」全局唯一：提交时预校验，落库唯一索引兜底，审核通过时复核
     */
    public function submitKyc(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        if ((int) $this->user['kyc_status'] === 2) {
            $this->fail('您已完成实名认证，无需重复提交');
        }

        $idType = (int) Request::input('id_type', 1);
        if (!in_array($idType, [1, 2], true)) {
            $this->fail('请选择证件类型');
        }
        $realName = trim((string) Request::input('real_name', ''));
        $idNo = strtoupper(trim((string) Request::input('id_card_no', '')));
        $front = trim((string) Request::input('id_photo_front', ''));
        $back = trim((string) Request::input('id_photo_back', ''));

        if ($realName === '' || mb_strlen($realName) < 2 || mb_strlen($realName) > 30) {
            $this->fail('请填写真实姓名（2~30 字）');
        }
        if ($idType === 1) {
            if (preg_match('/^(\d{15}|\d{17}[0-9X])$/', $idNo) !== 1) {
                $this->fail('身份证号格式不正确（15 或 18 位，X 须大写）');
            }
            if ($front === '' || $back === '') {
                $this->fail('请上传身份证人像面与国徽面两张照片');
            }
        } else {
            if (preg_match('/^[A-Z0-9]{5,20}$/', $idNo) !== 1) {
                $this->fail('护照号格式不正确（5~20 位字母或数字）');
            }
            if ($front === '') {
                $this->fail('请上传护照照片');
            }
        }
        if (mb_strlen($front) > 255 || mb_strlen($back) > 255) {
            $this->fail('照片地址异常，请重新上传');
        }
        // 黑名单拦截先于占用校验：被拉黑者的证件号要给出「身份封禁」的明确原因，
        // 否则只会看到含糊的「已被其他账号绑定」，客服也无法向用户解释处置结果
        if (UserModel::findBlacklistedByIdCard($idNo) !== null) {
            $this->fail('该证件号已被列入黑名单，无法用于实名认证，如有疑问请联系客服');
        }
        if (UserModel::kycOccupied($idType, $idNo, $this->userId())) {
            $this->fail('该证件号已被其他账号绑定，不可重复使用');
        }

        try {
            Database::pdo()->prepare(
                'UPDATE ' . Database::table('users') . '
                 SET id_type = :t, real_name = :name, id_card_no = :card,
                     id_photo_front = :front, id_photo_back = :back,
                     kyc_status = 1, kyc_at = NULL, kyc_remark = NULL
                 WHERE id = :id'
            )->execute([
                ':t' => $idType, ':name' => $realName, ':card' => $idNo,
                ':front' => $front, ':back' => $idType === 1 ? $back : null, ':id' => $this->userId(),
            ]);
        } catch (PDOException $e) {
            // 落库前恰有其他账号占用同一证件（唯一索引兜底并发）
            $this->fail('该证件号已被其他账号绑定，不可重复使用');
        }

        $this->ok(null, '实名信息已提交，等待审核');
    }

    /**
     * 修改登录密码（校验原密码 → 更新哈希 → 重置会话 ID）
     */
    public function password(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $oldPassword = (string) Request::input('old_password', '');
        $newPassword = (string) Request::input('new_password', '');
        $confirm = (string) Request::input('new_password_confirm', '');

        if (strlen($newPassword) < 8 || preg_match('/[A-Za-z]/', $newPassword) !== 1 || preg_match('/\d/', $newPassword) !== 1) {
            $this->fail('新密码至少 8 位，且需同时包含字母和数字');
        }
        if ($newPassword !== $confirm) {
            $this->fail('两次输入的新密码不一致');
        }

        $row = UserModel::findById($this->userId());
        if ($row === null || !password_verify($oldPassword, (string) $row['password_hash'])) {
            $this->fail('原密码不正确');
        }

        UserModel::updatePassword($this->userId(), password_hash($newPassword, PASSWORD_DEFAULT));
        // 守卫阶段已提前释放会话锁，重建会话 ID 前先重新打开（本操作低频，短暂持锁可接受）
        $this->reopenSession();
        session_regenerate_id(true);
        $this->ok(null, '密码修改成功，请牢记新密码');
    }

    /**
     * 登录方式绑定（手机 / 邮箱）
     */
    private function bindings(): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT auth_type, identifier, is_verified, created_at
             FROM ' . Database::table('user_auths') . ' WHERE user_id = :uid ORDER BY auth_type'
        );
        $stmt->execute([':uid' => $this->userId()]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function account(): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT balance, frozen_amount, total_recharge, total_consume
             FROM ' . Database::table('user_accounts') . ' WHERE user_id = :uid LIMIT 1'
        );
        $stmt->execute([':uid' => $this->userId()]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['balance' => 0, 'frozen_amount' => 0, 'total_recharge' => 0, 'total_consume' => 0];
    }
}
