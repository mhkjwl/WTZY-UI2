<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/TicketController.php
 * @description 会员中心：客服工单（提交/列表/详情/回复/关闭）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

namespace App\Controllers\Member;

use App\Core\Request;
use App\Enums\Status;
use App\Models\PackageModel;
use App\Models\TicketModel;
use App\Models\WaybillModel;
use RuntimeException;

final class TicketController extends BaseController
{

    public function index(): never
    {
        $this->requireLogin();
        $status = (int) Request::query('status', '-1');
        $page = $this->pageParams();
        $result = TicketModel::userSearch($this->userId(), $status, $page['limit'], $page['offset']);

        $data = [
            'title'      => '客服工单',
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page['page'],
            'pages'      => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'status'     => $status,
            'counts'     => TicketModel::userStatusCounts($this->userId()),
            'categories' => Status::TICKET_CATEGORY,
            'statusMap'  => Status::TICKET,
        ];
        if ($this->isPartial()) {
            $this->renderTable('member/tickets_table', $data);
        }
        $this->memberView('member/tickets', $data, 'tickets', '客服工单');
    }

    /**
     * 新建工单页
     */
    public function create(): never
    {
        $this->requireLogin();
        $this->memberView('member/ticket_create', [
            'title'      => '提交工单',
            'categories' => Status::TICKET_CATEGORY,
            'packages'   => PackageModel::search($this->userId(), '', -1, 50, 0)['rows'],
            'waybills'   => WaybillModel::userSearch($this->userId(), '', -1, 50, 0)['rows'],
        ], 'tickets', '提交工单');
    }

    public function save(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $category = (int) Request::input('category', 0);
        $title = trim((string) Request::input('title', ''));
        $content = trim((string) Request::input('content', ''));

        if (!isset(Status::TICKET_CATEGORY[$category])) {
            $this->fail('请选择问题类型');
        }
        if (mb_strlen($title) < 4 || mb_strlen($title) > 100) {
            $this->fail('标题需为 4~100 字');
        }
        if (mb_strlen($content) < 5 || mb_strlen($content) > 2000) {
            $this->fail('问题描述需为 5~2000 字');
        }

        try {
            $id = TicketModel::createForUser($this->userId(), [
                'category'   => $category,
                'title'      => $title,
                'content'    => $content,
                'waybill_id' => (int) Request::input('waybill_id', 0),
                'package_id' => (int) Request::input('package_id', 0),
            ]);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['id' => $id], '工单已提交，客服会尽快处理');
    }

    public function detail(string $id): never
    {
        $this->requireLogin();
        $ticket = TicketModel::findForUser((int) $id, $this->userId());
        if ($ticket === null) {
            $this->memberView('member/ticket_missing', ['title' => '工单不存在'], 'tickets', '工单详情');
        }
        $this->memberView('member/ticket_detail', [
            'title'      => '工单详情 · ' . $ticket['ticket_no'],
            'ticket'     => $ticket,
            'replies'    => TicketModel::replies((int) $ticket['id']),
            'categories' => Status::TICKET_CATEGORY,
            'statusMap'  => Status::TICKET,
        ], 'tickets', '工单详情');
    }

    /**
     * 对话记录局部渲染（回复后区域替换，不整页刷新）
     * 仍走归属校验，避免越权查看他人工单
     */
    public function thread(string $id): never
    {
        $this->requireLogin();
        if (TicketModel::findForUser((int) $id, $this->userId()) === null) {
            $this->fail('工单不存在', 404);
        }
        $this->renderTable('partials/ticket_thread', [
            'replies' => TicketModel::replies((int) $id),
            'viewer'  => 'user',
        ]);
    }

    public function reply(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $content = trim((string) Request::input('content', ''));
        if (mb_strlen($content) < 2) {
            $this->fail('请填写回复内容');
        }
        try {
            TicketModel::userReply((int) Request::input('id', 0), $this->userId(), $content);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '回复已提交');
    }

    public function close(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        try {
            TicketModel::userClose((int) Request::input('id', 0), $this->userId());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '工单已关闭');
    }
}
