<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/TicketController.php
 * @description 客服工单：列表 / 受理 / 回复 / 关闭
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Enums\Status;
use App\Core\StaffAuth;
use App\Models\TicketModel;
use RuntimeException;

final class TicketController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('ticket.view');
        $this->renderList((int) Request::query('status', '-1'), (int) Request::query('category', '0'));
    }

    private function renderList(int $status, int $category): void
    {
        $page = $this->pageParams();
        $result = TicketModel::search($status, $category, $page['limit'], $page['offset']);
        // 标记「最后一条回复来自用户」的工单，列表上直接提示等待客服跟进
        $pendingUser = TicketModel::pendingUserReplyMap(array_column($result['rows'], 'id'));
        $data = [
            'title'       => '客服工单',
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page['page'],
            'pages'       => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'status'      => $status,
            'category'    => $category,
            'counts'      => TicketModel::statusCounts(),
            'pendingUser' => $pendingUser,
            'base'        => '/admin/tickets',
        ];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/tickets_table', $data);
        }
        $this->adminView('admin/tickets', $data, 'tickets', '客服工单');
    }

    /**
     * 工单详情：完整对话记录 + 用户信息 + 受理/回复/关闭
     * 与用户端共用同一份对话模板，按回复方区分标识，客服可看到用户后续追问
     */
    public function detail(string $id): never
    {
        $this->requireStaff();
        $this->enforce('ticket.view');
        $ticket = TicketModel::findDetail((int) $id);
        if ($ticket === null) {
            $this->adminView('admin/ticket_missing', ['title' => '工单不存在'], 'tickets', '工单详情');
        }
        $this->adminView('admin/ticket_detail', [
            'title'      => '工单详情 · ' . $ticket['ticket_no'],
            'ticket'     => $ticket,
            'replies'    => TicketModel::replies((int) $ticket['id']),
            'categories' => Status::TICKET_CATEGORY,
            'statusMap'  => Status::TICKET,
        ], 'tickets', '工单详情');
    }

    /**
     * 对话记录局部渲染（回复后仅替换本区域，不整页刷新）
     */
    public function thread(string $id): never
    {
        $this->requireStaff();
        $this->enforce('ticket.view');
        if (TicketModel::findById((int) $id) === null) {
            $this->fail('工单不存在', 404);
        }
        $this->renderTable('partials/ticket_thread', [
            'replies' => TicketModel::replies((int) $id),
            'viewer'  => 'staff',
        ]);
    }

    public function assign(): never
    {
        $this->requireStaff();
        $this->enforce('ticket.handle');
        $this->verifyCsrf();
        try {
            TicketModel::assign((int) Request::input('id', 0), StaffAuth::id());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '已受理该工单');
    }

    /**
     * 回复工单：写回复记录并回执用户（站内信通道随通知模块接入）
     */
    public function reply(): never
    {
        $this->requireStaff();
        $this->enforce('ticket.handle');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $content = trim((string) Request::input('content', ''));
        if ($content === '' || mb_strlen($content) > 2000) {
            $this->fail('回复内容需为 1~2000 字');
        }
        try {
            TicketModel::reply($id, StaffAuth::id(), $content);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '回复已提交');
    }

    public function close(): never
    {
        $this->requireStaff();
        $this->enforce('ticket.handle');
        $this->verifyCsrf();
        try {
            TicketModel::close((int) Request::input('id', 0), StaffAuth::id());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '工单已关闭');
    }
}
