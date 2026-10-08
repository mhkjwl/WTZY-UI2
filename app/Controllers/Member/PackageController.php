<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/PackageController.php
 * @description 会员中心：我的包裹（列表/详情/认领/退运销毁）与异常件
 * @author AI
 * @version 0.6
 * @date 2026-09-15
 */

namespace App\Controllers\Member;

use App\Core\Request;
use App\Models\PackageModel;
use InvalidArgumentException;
use RuntimeException;

final class PackageController extends BaseController
{
    /** 用户可发起的处置类型：6 退运 7 销毁（与仓库端异常类型编码对齐） */
    private const DISPOSAL_TYPES = [6 => '退运', 7 => '销毁'];

    public function index(): never
    {
        $this->requireLogin();
        $keyword = (string) Request::query('kw', '');
        $status = (int) Request::query('status', '-1');
        $page = $this->pageParams();
        $result = PackageModel::search($this->userId(), $keyword, $status, $page['limit'], $page['offset']);

        $data = [
            'title'   => '我的包裹',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page['page'],
            'pages'   => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword' => $keyword,
            'status'  => $status,
            'counts'  => PackageModel::statusCounts($this->userId()),
            'summary' => PackageModel::stockSummary($this->userId()),
            'pendingDisposals' => PackageModel::pendingDisposals(array_column($result['rows'], 'id')),
        ];
        if ($this->isPartial()) {
            $this->renderTable('member/packages_table', $data);
        }
        $this->memberView('member/packages', $data, 'packages', '我的包裹');
    }

    public function detail(string $id): never
    {
        $this->requireLogin();
        $package = PackageModel::findForUser((int) $id, $this->userId());
        if ($package === null) {
            $this->memberView('member/package_missing', ['title' => '包裹不存在'], 'packages', '包裹详情');
        }
        $this->memberView('member/package_detail', [
            'title'   => '包裹详情 · ' . $package['package_no'],
            'package' => $package,
            'waybill' => PackageModel::latestWaybillOf((int) $package['id']),
            'photos'  => PackageModel::photos((int) $package['id']),
            'abnormals' => PackageModel::abnormalsByPackage((int) $package['id']),
            'pendingDisposal' => PackageModel::pendingDisposalOf((int) $package['id']),
            'disposalTypes' => self::DISPOSAL_TYPES,
            'storageInfo' => $this->storageInfo($package),
        ], 'packages', '包裹详情');
    }

    /**
     * 包裹免费仓储信息：免费天数（仓库 + 会员加成）、到期日、已超期天数与预估费用
     */
    private function storageInfo(array $package): ?array
    {
        if (empty($package['inbound_at'])) {
            return null;
        }
        $freeDays = PackageModel::freeStorageDays($package, null, (int) $this->user['member_level']);
        $due = (int) strtotime((string) $package['inbound_at']) + $freeDays * 86400;
        $graceDays = (int) \App\Models\SettingsModel::get('storage_grace_days', '3');
        return [
            'free_days'   => $freeDays,
            'due_at'      => date('Y-m-d H:i', $due),
            'overdue_days'=> max(0, (int) floor((time() - $due) / 86400) - $graceDays),
            'est_fee'     => PackageModel::estimatedStorageFee($package, null, (int) $this->user['member_level']),
        ];
    }

    /**
     * 认领包裹（待认领/已到仓 → 已认领）
     */
    public function claim(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        try {
            PackageModel::claim((int) Request::input('id', 0), $this->userId());
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '包裹已认领，可提交转运');
    }

    /**
     * 申请退运 / 销毁
     */
    public function disposal(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $type = (int) Request::input('type', 0);
        $reason = trim((string) Request::input('reason', ''));
        if (!isset(self::DISPOSAL_TYPES[$type])) {
            $this->fail('请选择退运或销毁');
        }
        if (mb_strlen($reason) < 4) {
            $this->fail('请填写申请原因（至少 4 个字）');
        }
        try {
            PackageModel::requestDisposal((int) Request::input('id', 0), $this->userId(), $type, $reason);
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, self::DISPOSAL_TYPES[$type] . '申请已提交，仓库处理后会通知您');
    }

    /**
     * 取消本人包裹进行中的退运/销毁申请
     */
    public function cancelDisposal(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        try {
            $cancelled = PackageModel::cancelDisposal((int) Request::input('id', 0), $this->userId());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, (self::DISPOSAL_TYPES[$cancelled['type']] ?? '处置') . '申请已取消');
    }

    /**
     * 异常件处理
     */
    public function abnormal(): never
    {
        $this->requireLogin();
        $page = $this->pageParams();
        $result = PackageModel::abnormals($this->userId(), $page['limit'], $page['offset']);
        $data = [
            'title' => '异常件处理',
            'rows'  => $result['rows'],
            'total' => $result['total'],
            'page'  => $page['page'],
            'pages' => (int) ceil($result['total'] / $page['limit']) ?: 1,
        ];
        // 翻页只回统计与列表区域，不整页刷新（规范 §2.5）
        if ($this->isPartial()) {
            $this->renderTable('member/abnormal_region', $data);
        }
        $this->memberView('member/abnormal', $data, 'abnormal', '异常件处理');
    }
}
