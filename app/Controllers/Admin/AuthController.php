<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/AuthController.php
 * @description 管理端认证：员工登录 / 退出（staff_type=1）
 * @author AI
 * @version 0.6.1
 * @date 2026-10-09
 */

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\StaffAuth;
use App\Services\AuthService;
use App\Services\CaptchaService;
use InvalidArgumentException;

final class AuthController extends BaseController
{

    public function showLogin(): never
    {
        // 仅本端登录态才回跳控制台；仓库端会话不应被当成管理后台已登录
        if (StaffAuth::boundTo(StaffAuth::END_ADMIN)) {
            Response::redirect('/admin');
        }
        // 后台独立主题，经 back_auth 布局输出（顶部条只放品牌与返回官网，不混入前台导航）
        $this->view('admin/login', [
            'title'   => '登录 · 管理后台',
            'product' => '管理后台',
        ], 'layouts/back_auth');
    }

    public function login(): never
    {
        $this->verifyCsrf();
        try {
            CaptchaService::verify('login_admin', (string) Request::input('captcha', ''));
            [$staff, $permissions] = AuthService::staffLogin(
                Request::input('username'),
                Request::input('password'),
                StaffAuth::TYPE_ADMIN
            );
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        StaffAuth::login($staff, StaffAuth::END_ADMIN, $permissions);
        $this->ok(['redirect' => '/admin'], '登录成功');
    }

    public function logout(): never
    {
        $this->verifyCsrf();
        StaffAuth::logout();
        $this->ok(['redirect' => '/']);
    }
}
