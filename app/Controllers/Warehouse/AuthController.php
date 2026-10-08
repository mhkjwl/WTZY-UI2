<?php declare(strict_types=1);
/**
 * @file app/Controllers/Warehouse/AuthController.php
 * @description 仓库端认证：员工登录 / 退出（staff_type=2）
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

namespace App\Controllers\Warehouse;

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
        // 仅本端登录态才回跳作业台；管理端会话不应被当成仓库端已登录
        if (StaffAuth::boundTo(StaffAuth::END_WAREHOUSE)) {
            Response::redirect('/warehouse');
        }
        // 后台独立主题，经 back_auth 布局输出（顶部条只放品牌与返回官网，不混入前台导航）
        $this->view('warehouse/login', [
            'title'   => '登录 · 仓库端',
            'product' => '仓库端',
        ], 'layouts/back_auth');
    }

    public function login(): never
    {
        $this->verifyCsrf();
        try {
            CaptchaService::verify('login_warehouse', (string) Request::input('captcha', ''));
            [$staff, $permissions] = AuthService::staffLogin(
                Request::input('username'),
                Request::input('password'),
                StaffAuth::TYPE_WAREHOUSE
            );
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        StaffAuth::login($staff, StaffAuth::END_WAREHOUSE, $permissions);
        $this->ok(['redirect' => '/warehouse'], '登录成功');
    }

    public function logout(): never
    {
        $this->verifyCsrf();
        StaffAuth::logout();
        $this->ok(['redirect' => '/']);
    }
}
