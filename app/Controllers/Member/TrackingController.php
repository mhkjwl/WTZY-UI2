<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/TrackingController.php
 * @description 会员中心：物流跟踪（按运单查询轨迹）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Controllers\Member;

use App\Core\Request;
use App\Models\WaybillModel;

final class TrackingController extends BaseController
{
    public function index(): never
    {
        $this->requireLogin();
        $page = $this->pageParams(10);
        $result = WaybillModel::userSearch($this->userId(), (string) Request::query('kw', ''), -1, $page['limit'], $page['offset']);

        // 只看已出库之后的运单才可能有轨迹，但仍列出全部并在详情里提示
        $selectedId = (int) Request::query('waybill', '0');
        $selected = null;
        $records = [];
        if ($selectedId > 0) {
            $selected = WaybillModel::findForUser($selectedId, $this->userId());
            if ($selected !== null) {
                $records = WaybillModel::tracking($selectedId);
            }
        }

        $data = [
            'title'    => '物流跟踪',
            'rows'     => $result['rows'],
            'total'    => $result['total'],
            'page'     => $page['page'],
            'pages'    => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword'  => (string) Request::query('kw', ''),
            'selected' => $selected,
            'records'  => $records,
        ];

        // 检索/翻页/切换运单：只回列表与详情区域，不整页刷新（规范 §2.5）
        if ($this->isPartial()) {
            $this->renderTable('member/tracking_region', $data);
        }

        $this->memberView('member/tracking', $data, 'tracking', '物流跟踪');
    }
}
