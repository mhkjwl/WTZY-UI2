<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/MarketingController.php
 * @description 营销与内容：全站公告发布/下架 + 内容页（协议/隐私/禁运品/FAQ）编辑
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Controllers\Admin;

use App\Core\Mailer;
use App\Core\Request;
use App\Models\MarketingModel;
use App\Models\MessageModel;
use App\Services\NoticeService;

final class MarketingController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('marketing.manage');
        $this->renderList(1);
    }

    private function renderList(int $page): void
    {
        $page = max(1, $page);
        $notices = MessageModel::notices(15, ($page - 1) * 15);
        $data = [
            'title'    => '营销与内容',
            'notices'  => $notices['rows'],
            'total'    => $notices['total'],
            'page'     => $page,
            'pages'    => (int) ceil($notices['total'] / 15) ?: 1,
            'contents' => MarketingModel::contentList(),
            'mailConfigured' => Mailer::isConfigured(),
            'base'     => '/admin/marketing',
        ];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/marketing_content', $data);
        }
        $this->adminView('admin/marketing', $data, 'marketing', '营销与内容');
    }

    /**
     * 发布全站公告：站内信即时可见，邮箱渠道同步投递至用户注册邮箱
     * 公告类型：1 普通 / 2 重要，用户端公告列表按类型打标签展示
     */
    public function createNotice(): never
    {
        $this->requireStaff();
        $this->enforce('marketing.manage');
        $this->verifyCsrf();
        $title = trim((string) Request::input('title', ''));
        $content = trim((string) Request::input('content', ''));
        $msgType = (int) Request::input('msg_type', 1);
        $noticeType = (int) Request::input('notice_type', 1);
        $this->validateNotice($title, $content, $msgType);
        $id = MessageModel::createNotice($title, $content, $msgType, $noticeType);
        $this->okNotice($id, $title, $content, $msgType, '公告已发布');
    }

    /**
     * 重发邮件公告：用于首次投递失败（SMTP 未配置、连接异常）后的补投
     */
    public function resendNotice(): never
    {
        $this->requireStaff();
        $this->enforce('marketing.manage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $notice = MessageModel::noticeDetail($id);
        if ($notice === null) {
            $this->fail('公告不存在或已下架');
        }
        if ((int) $notice['msg_type'] !== 3) {
            $this->fail('该公告不是邮箱渠道，无需重发邮件');
        }
        $result = NoticeService::deliver($id, (string) $notice['title'], (string) $notice['content']);
        $this->ok($result, $this->mailSummary($result, '邮件重发完成'));
    }

    /**
     * 公告标题、正文与通知渠道校验（发布与编辑共用同一套规则）
     */
    private function validateNotice(string $title, string $content, int $msgType): void
    {
        if ($title === '' || mb_strlen($title) > 100) {
            $this->fail('公告标题需为 1~100 字');
        }
        if ($content === '' || mb_strlen($content) > 5000) {
            $this->fail('公告内容需为 1~5000 字');
        }
        if (!in_array($msgType, [1, 3], true)) {
            $this->fail('无效的通知渠道');
        }
    }

    /**
     * 公告保存后的统一响应：邮箱渠道回报投递结果，站内信渠道直接提示成功
     */
    private function okNotice(int $id, string $title, string $content, int $msgType, string $prefix): never
    {
        if ($msgType !== 3) {
            $this->ok(null, $prefix);
        }
        $result = NoticeService::deliver($id, $title, $content);
        $this->ok($result, $this->mailSummary($result, $prefix));
    }

    /**
     * 投递结果文案（含失败原因，便于管理员直接定位 SMTP 配置问题）
     */
    private function mailSummary(array $result, string $prefix): string
    {
        if ($result['total'] === 0) {
            return $prefix . '，但邮件未投递：' . $result['error'];
        }
        $text = $prefix . '，邮件投递成功 ' . $result['sent'] . ' 封';
        if ($result['failed'] > 0) {
            $text .= '，失败 ' . $result['failed'] . ' 封（' . $result['error'] . '）';
        }
        if ($result['truncated']) {
            $text .= '；单次上限 ' . NoticeService::BATCH_LIMIT . ' 封，剩余用户请稍后重发';
        }
        return $text;
    }

    /**
     * 编辑已发布公告：刷新更新时间，用户端消息中心按此时间重新排序
     */
    public function updateNotice(): never
    {
        $this->requireStaff();
        $this->enforce('marketing.manage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $title = trim((string) Request::input('title', ''));
        $content = trim((string) Request::input('content', ''));
        $msgType = (int) Request::input('msg_type', 1);
        $noticeType = (int) Request::input('notice_type', 1);
        if (MessageModel::noticeDetail($id) === null) {
            $this->fail('公告不存在或已下架');
        }
        $this->validateNotice($title, $content, $msgType);
        MessageModel::updateNotice($id, $title, $content, $msgType, $noticeType);
        $this->okNotice($id, $title, $content, $msgType, '公告已更新，用户端将按最新时间展示');
    }

    /**
     * 下架公告（从用户可见列表移除）
     */
    public function deleteNotice(): never
    {
        $this->requireStaff();
        $this->enforce('marketing.manage');
        $this->verifyCsrf();
        MessageModel::deleteNotice((int) Request::input('id', 0));
        $this->ok(null, '公告已下架');
    }

    /**
     * 保存内容页（协议/隐私政策/禁运品说明/FAQ，键固定于 content 组）
     */
    public function saveContent(): never
    {
        $this->requireStaff();
        $this->enforce('marketing.manage');
        $this->verifyCsrf();
        $key = (string) Request::input('key', '');
        $value = (string) Request::input('value', '');
        $allowed = ['content_user_agreement', 'content_privacy_policy', 'content_contraband', 'content_faq'];
        if (!in_array($key, $allowed, true)) {
            $this->fail('无效的内容页标识');
        }
        if (mb_strlen($value) > 50000) {
            $this->fail('内容过长（上限 50000 字）');
        }
        MarketingModel::upsertContent($key, $value);
        $this->ok(null, '内容已保存');
    }
}
