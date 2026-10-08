<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/MessageController.php
 * @description 会员中心：消息中心（仅本人定向消息）+ 网站公告（全员广播列表与详情）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

namespace App\Controllers\Member;

use App\Core\Request;
use App\Core\Response;
use App\Models\MessageModel;

final class MessageController extends BaseController
{
    private const PAGE_SIZE = 15;

    public function index(): never
    {
        $this->requireLogin();
        $userId = $this->userId();
        $page = max(1, (int) Request::query('page', '1'));
        $result = MessageModel::inbox($userId, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        if ($this->isPartial()) {
            $this->renderTable('member/messages_content', [
                'rows'  => $result['rows'],
                'total' => $result['total'],
                'page'  => $page,
                'pages' => (int) ceil($result['total'] / self::PAGE_SIZE) ?: 1,
                'unread' => MessageModel::unreadCount($userId),
            ]);
        }

        $this->memberView('member/messages', [
            'title'  => '消息中心',
            'rows'   => $result['rows'],
            'total'  => $result['total'],
            'page'   => $page,
            'pages'  => (int) ceil($result['total'] / self::PAGE_SIZE) ?: 1,
            'unread' => MessageModel::unreadCount($userId),
        ], 'messages', '消息中心');
    }

    /**
     * 网站公告列表（全员广播，与个人消息分开展示）
     */
    public function notices(): never
    {
        $this->requireLogin();
        $page = max(1, (int) Request::query('page', '1'));
        $result = MessageModel::broadcastList(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        if ($this->isPartial()) {
            $this->renderTable('member/notices_content', [
                'rows'  => $result['rows'],
                'total' => $result['total'],
                'page'  => $page,
                'pages' => (int) ceil($result['total'] / self::PAGE_SIZE) ?: 1,
            ]);
        }

        $this->memberView('member/notices', [
            'title' => '网站公告',
            'rows'  => $result['rows'],
            'total' => $result['total'],
            'page'  => $page,
            'pages' => (int) ceil($result['total'] / self::PAGE_SIZE) ?: 1,
        ], 'notices', '网站公告');
    }

    /**
     * 公告详情：全文查看
     */
    public function noticeDetail(string $id): never
    {
        $this->requireLogin();
        $notice = MessageModel::broadcastGet((int) $id);
        if ($notice === null) {
            Response::redirect('/user/notices');
        }
        $this->memberView('member/notice_detail', [
            'title'  => '公告详情',
            'notice' => $notice,
        ], 'notices', '公告详情');
    }

    /**
     * 标记个人消息已读（全站公告为全员共享记录，不在此处理）
     */
    public function read(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $userId = $this->userId();
        if (!MessageModel::markRead($userId, (int) Request::input('id', 0))) {
            $this->fail('消息不存在或已读');
        }
        $this->ok(['unread' => MessageModel::unreadCount($userId)], '已标记为已读');
    }

    /**
     * 一键全部已读：当前用户的所有未读个人消息一次标记
     */
    public function readAll(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $userId = $this->userId();
        $count = MessageModel::markAllRead($userId);
        $this->ok(['unread' => MessageModel::unreadCount($userId)], $count > 0 ? "已将 {$count} 条消息标记为已读" : '没有未读消息');
    }
}
