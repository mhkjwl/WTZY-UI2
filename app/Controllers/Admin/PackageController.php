<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/PackageController.php
 * @description 包裹管理：全用户包裹检索与详情（与会员中心「我的包裹」同源数据，客服据此答复工单）
 * @author AI
 * @version 0.6
 * @date 2026-09-15
 */

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Models\PackageModel;
use App\Enums\Status;

final class PackageController extends BaseController
{
    /** 后台包裹详情展示的处置类型（与用户端申请口径一致） */
    private const DISPOSAL_TYPES = [6 => '申请退运', 7 => '申请销毁'];

    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('package.view');
        $this->renderList((string) Request::query('kw', ''), (int) Request::query('status', '-1'));
    }

    /**
     * 检索/分页后仅局部刷新表格区
     */
    private function renderList(string $keyword, int $status): void
    {
        $page = $this->pageParams();
        $result = PackageModel::adminSearch($keyword, $status, $page['limit'], $page['offset']);
        $data = [
            'title'   => '包裹管理',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page['page'],
            'pages'   => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword' => $keyword,
            'status'  => $status,
            'counts'  => PackageModel::adminStatusCounts(),
            'pendingDisposals' => $result['pendingDisposals'],
            'base'    => '/admin/packages',
        ];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/packages_table', $data);
        }
        $this->adminView('admin/packages', $data, 'packages', '包裹管理');
    }

    /**
     * 包裹详情：商品/物流/计费/库位 + 照片 + 异常与处置记录
     */
    public function detail(string $id): never
    {
        $this->requireStaff();
        $this->enforce('package.view');
        $package = PackageModel::findDetail((int) $id);
        if ($package === null) {
            $this->adminView('admin/package_missing', ['title' => '包裹不存在'], 'packages', '包裹详情');
        }
        $this->adminView('admin/package_detail', [
            'title'         => '包裹详情 · ' . $package['package_no'],
            'package'       => $package,
            'photos'        => PackageModel::photos((int) $package['id']),
            'abnormals'     => PackageModel::abnormalsByPackage((int) $package['id']),
            'pendingDisposal' => PackageModel::pendingDisposalOf((int) $package['id']),
            'disposalTypes' => self::DISPOSAL_TYPES,
            'categories'    => Status::PACKAGE_CATEGORY,
        ], 'packages', '包裹详情');
    }
}
