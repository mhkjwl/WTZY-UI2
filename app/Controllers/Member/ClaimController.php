<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/ClaimController.php
 * @description 会员中心：理赔申请（提交/列表/详情）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Controllers\Member;

use App\Core\Request;
use App\Models\ClaimModel;
use App\Models\PackageModel;
use App\Models\WaybillModel;
use RuntimeException;

final class ClaimController extends BaseController
{
    public function index(): never
    {
        $this->requireLogin();
        $status = (int) Request::query('status', '-1');
        $page = $this->pageParams();
        $result = ClaimModel::search($this->userId(), $status, $page['limit'], $page['offset']);

        $data = [
            'title'   => '理赔申请',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page['page'],
            'pages'   => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'status'  => $status,
            'counts'  => ClaimModel::counts($this->userId()),
            'types'   => ClaimModel::TYPES,
            'statusMap' => ClaimModel::STATUS,
        ];
        if ($this->isPartial()) {
            $this->renderTable('member/claims_table', $data);
        }
        $this->memberView('member/claims', $data, 'claims', '理赔申请');
    }

    /**
     * 提交理赔
     */
    public function create(): never
    {
        $this->requireLogin();
        $preselect = (int) Request::query('package', '0');
        $this->memberView('member/claim_create', [
            'title'    => '提交理赔申请',
            'types'    => ClaimModel::TYPES,
            'packages' => PackageModel::search($this->userId(), '', -1, 50, 0)['rows'],
            'waybills' => WaybillModel::userSearch($this->userId(), '', -1, 50, 0)['rows'],
            'preselect' => $preselect,
        ], 'claims', '提交理赔申请');
    }

    public function save(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $type = (int) Request::input('claim_type', 0);
        $amount = round((float) Request::input('amount', 0), 2);
        $description = trim((string) Request::input('description', ''));

        if (!isset(ClaimModel::TYPES[$type])) {
            $this->fail('请选择理赔类型');
        }
        if ($amount <= 0 || $amount > 100000) {
            $this->fail('申请赔付金额需大于 0 且不超过 ¥100,000');
        }
        if (mb_strlen($description) < 10 || mb_strlen($description) > 500) {
            $this->fail('问题描述需为 10~500 字，请尽量写清情况');
        }

        $packageId = (int) Request::input('package_id', 0);
        $waybillId = (int) Request::input('waybill_id', 0);
        if ($packageId <= 0 && $waybillId <= 0) {
            $this->fail('请选择关联的包裹或运单，以便客服核对');
        }
        // 归属校验：只能对自己的包裹/运单发起理赔
        if ($packageId > 0 && PackageModel::findForUser($packageId, $this->userId()) === null) {
            $this->fail('关联包裹不存在或不属于当前账号');
        }
        if ($waybillId > 0 && WaybillModel::findForUser($waybillId, $this->userId()) === null) {
            $this->fail('关联运单不存在或不属于当前账号');
        }

        // 同一包裹/运单未结案的理赔不允许重复提交
        // 注意：原生预处理下同名占位符不可复用，故按条件生成独立占位符
        $conditions = [];
        $params = [':uid' => $this->userId()];
        if ($packageId > 0) {
            $conditions[] = 'package_id = :pid';
            $params[':pid'] = $packageId;
        }
        if ($waybillId > 0) {
            $conditions[] = 'waybill_id = :wid';
            $params[':wid'] = $waybillId;
        }
        $pending = \App\Core\Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . \App\Core\Database::table('claims') . '
             WHERE user_id = :uid AND status IN (0, 1) AND (' . implode(' OR ', $conditions) . ')'
        );
        $pending->execute($params);
        if ((int) $pending->fetchColumn() > 0) {
            $this->fail('该包裹/运单已有理赔在处理中，请等待处理结果');
        }

        try {
            $id = ClaimModel::create($this->userId(), [
                'claim_type' => $type, 'amount' => $amount, 'description' => $description,
                'waybill_id' => $waybillId, 'package_id' => $packageId,
                'images' => array_values(array_filter(array_map('trim', (array) Request::input('images', [])))),
            ]);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['id' => $id], '理赔申请已提交，客服会尽快跟进');
    }

    public function detail(string $id): never
    {
        $this->requireLogin();
        $claim = ClaimModel::findForUser((int) $id, $this->userId());
        if ($claim === null) {
            $this->memberView('member/claim_missing', ['title' => '理赔单不存在'], 'claims', '理赔详情');
        }
        $this->memberView('member/claim_detail', [
            'title'     => '理赔详情 · ' . $claim['claim_no'],
            'claim'     => $claim,
            'types'     => ClaimModel::TYPES,
            'statusMap' => ClaimModel::STATUS,
            'images'    => json_decode((string) ($claim['images'] ?? '[]'), true) ?: [],
        ], 'claims', '理赔详情');
    }
}
