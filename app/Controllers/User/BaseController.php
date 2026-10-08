<?php declare(strict_types=1);
/**
 * @file app/Controllers/User/BaseController.php
 * @description 用户端控制器基类：统一登录校验入口（含账号禁用/黑名单拦截）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

namespace App\Controllers\User;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\UserAuth;

abstract class BaseController extends Controller
{
    protected ?array $user = null;

    /**
     * 需要登录的页面在方法首行调用；未登录或账号被后台停用都跳登录页（页面级跳转，非操作后刷新）
     */
    protected function requireLogin(): void
    {
        $fresh = UserAuth::fresh();
        if ($fresh === null) {
            $message = UserAuth::notice() ?? '登录状态已失效，请重新登录';
            if (Request::header('X-Requested-With') === 'fetch' || Request::method() !== 'GET') {
                Response::json(['success' => false, 'message' => $message, 'data' => ['redirect' => '/user/login']], 403);
            }
            Response::redirect('/user/login');
        }
        $this->user = $fresh;
    }
}
