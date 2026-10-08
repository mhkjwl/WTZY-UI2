<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/CustomsController.php
 * @description 清关管理：报关明细列表 + 运单清关状态维护
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Models\WaybillModel;

final class CustomsController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('customs.manage');
        $this->renderList((string) Request::query('kw', ''));
    }

    private function renderList(string $keyword): void
    {
        $page = $this->pageParams();
        $result = WaybillModel::declareList($keyword, $page['limit'], $page['offset']);
        $data = [
            'title'   => '清关管理',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page['page'],
            'pages'   => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword' => $keyword,
            'base'    => '/admin/customs',
        ];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/customs_table', $data);
        }
        $this->adminView('admin/customs', $data, 'customs', '清关管理');
    }

    /**
     * 更新运单清关子状态（0无 1待清关 2清关中 3已放行 4异常 5已完税）
     */
    public function updateClear(): never
    {
        $this->requireStaff();
        $this->enforce('customs.manage');
        $this->verifyCsrf();
        $waybillId = (int) Request::input('waybill_id', 0);
        $clearStatus = (int) Request::input('clear_status', -1);
        if (!in_array($clearStatus, [0, 1, 2, 3, 4, 5], true)) {
            $this->fail('无效的清关状态');
        }
        WaybillModel::updateClearStatus($waybillId, $clearStatus);
        $this->ok(null, '清关状态已更新');
    }
}
