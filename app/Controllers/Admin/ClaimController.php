<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/ClaimController.php
 * @description 理赔处理：列表 / 详情 / 受理 / 初审 / 财务审核 / 管理员终审 / 驳回 / 赔付（多级审核）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\StaffAuth;
use App\Models\ClaimModel;
use App\Models\MessageModel;
use InvalidArgumentException;
use RuntimeException;

final class ClaimController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('claim.view');
        $this->renderList((int) Request::query('status', '-1'));
    }

    /**
     * 理赔详情页：完整信息 + 审核时间线
     */
    public function detail(string $id): never
    {
        $this->requireStaff();
        $this->enforce('claim.view');
        $claim = ClaimModel::adminFind((int) $id);
        if ($claim === null) {
            Response::redirect('/admin/claims');
        }
        $this->adminView('admin/claim_detail', [
            'title'      => $claim['claim_no'],
            'claim'      => $claim,
        ], 'claims', '理赔详情');
    }

    private function renderList(int $status): void
    {
        $page = $this->pageParams();
        $result = ClaimModel::adminSearch($status, $page['limit'], $page['offset']);
        $data = [
            'title'   => '理赔处理',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page['page'],
            'pages'   => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'status'  => $status,
            'counts'  => ClaimModel::adminCounts(),
            'base'    => '/admin/claims',
        ];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/claims_table', $data);
        }
        $this->adminView('admin/claims', $data, 'claims', '理赔处理');
    }

    /** 受理：待受理 → 处理中 */
    public function accept(): never
    {
        $this->requireStaff();
        $this->enforce('claim.review');
        $this->verifyCsrf();
        try {
            ClaimModel::accept((int) Request::input('id', 0), StaffAuth::id());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['status' => 1], '已受理该理赔单');
    }

    /**
     * 初审：处理中 → 初审通过，核定赔付金额（提交财务审核）
     */
    public function review(): never
    {
        $this->requireStaff();
        $this->enforce('claim.review');
        $this->verifyCsrf();
        $amount = (float) Request::input('approved_amount', 0);
        try {
            ClaimModel::review(
                (int) Request::input('id', 0),
                $amount,
                trim((string) Request::input('remark', ''))
            );
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->notifyUser((int) Request::input('id', 0), '理赔初审通过', '您的理赔申请已通过初审，正在提交财务审核。');
        $this->ok(['status' => 2, 'approved_amount' => $amount], '初审通过，已提交财务审核');
    }

    /**
     * 财务审核：初审通过 → 财务已审
     */
    public function financeAudit(): never
    {
        $this->requireStaff();
        $this->enforce('claim.finance_audit');
        $this->verifyCsrf();
        try {
            ClaimModel::financeAudit((int) Request::input('id', 0), StaffAuth::id());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['status' => 5], '财务审核通过，已提交管理员终审');
    }

    /**
     * 管理员终审：财务已审 → 管理员已审（待赔付）
     */
    public function adminAudit(): never
    {
        $this->requireStaff();
        $this->enforce('claim.admin_audit');
        $this->verifyCsrf();
        try {
            ClaimModel::adminAudit((int) Request::input('id', 0), StaffAuth::id());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['status' => 6], '管理员终审通过，可执行赔付');
    }

    /**
     * 驳回：附原因并通知用户
     */
    public function reject(): never
    {
        $this->requireStaff();
        $this->enforce('claim.review');
        $this->verifyCsrf();
        try {
            ClaimModel::reject((int) Request::input('id', 0), trim((string) Request::input('remark', '')));
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->notifyUser((int) Request::input('id', 0), '理赔审核未通过', '您的理赔申请未通过审核，原因请见理赔详情。如有疑问请联系客服。');
        $this->ok(['status' => 3], '已驳回并通知用户');
    }

    /**
     * 赔付：管理员已审(6) → 已赔付(4)，按核定金额给用户余额入账
     */
    public function pay(): never
    {
        $this->requireStaff();
        $this->enforce('claim.pay');
        $this->verifyCsrf();
        try {
            ClaimModel::payOut((int) Request::input('id', 0), StaffAuth::id());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->notifyUser((int) Request::input('id', 0), '理赔赔付已到账', '您的理赔赔付金额已入账余额，可在财务中心查看流水。');
        $this->ok(['status' => 4], '赔付完成，用户余额已入账');
    }

    private function notifyUser(int $claimId, string $title, string $content): void
    {
        $claim = ClaimModel::adminFind($claimId);
        if ($claim !== null) {
            MessageModel::sendToUser((int) $claim['user_id'], $title, $content, 3, (string) $claim['claim_no']);
        }
    }
}
