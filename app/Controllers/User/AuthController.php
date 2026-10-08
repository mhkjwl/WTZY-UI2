<?php declare(strict_types=1);
/**
 * @file app/Controllers/User/AuthController.php
 * @description 用户端认证：注册 / 登录 / 退出
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

namespace App\Controllers\User;

use App\Core\Request;
use App\Core\Response;
use App\Core\UserAuth;
use App\Services\AuthService;
use App\Services\CaptchaService;
use App\Services\SiteChrome;
use InvalidArgumentException;
use RuntimeException;

final class AuthController extends BaseController
{
    public function showLogin(): never
    {
        if (UserAuth::check()) {
            Response::redirect('/user');
        }
        // 账号在登录期间被后台停用时会话会被强制失效，此处承接提示原因
        $this->view('user/login', [
            'title'  => '用户登录 · ' . SiteChrome::brand(),
            'notice' => UserAuth::pullNotice(),
        ], 'layouts/site');
    }

    public function showRegister(): never
    {
        if (UserAuth::check()) {
            Response::redirect('/user');
        }
        $this->view('user/register', [
            'title'            => '注册账号 · ' . SiteChrome::brand(),
            'emailVerifyEnabled' => AuthService::emailVerifyEnabled(),
        ], 'layouts/site');
    }

    public function register(): never
    {
        $this->verifyCsrf();
        try {
            CaptchaService::verify('register', (string) Request::input('captcha', ''));
            $user = AuthService::register(
                Request::input('username'),
                Request::input('account'),
                Request::input('password'),
                Request::input('password_confirm'),
                Request::input('nickname'),
                Request::input('email_code')
            );
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        UserAuth::login($user);
        $this->ok(['redirect' => '/user'], '注册成功，欢迎使用');
    }

    /**
     * 注册用邮箱验证码发送（开关开启时注册页可见；频率限制在服务层）
     */
    public function sendRegisterCode(): never
    {
        $this->verifyCsrf();
        try {
            AuthService::sendRegisterCode(Request::input('account'), Request::ip());
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '验证码已发送至邮箱，请查收（含垃圾邮件箱）');
    }

    public function login(): never
    {
        $this->verifyCsrf();
        try {
            CaptchaService::verify('login', (string) Request::input('captcha', ''));
            $user = AuthService::userLogin(Request::input('account'), Request::input('password'));
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        UserAuth::login($user);
        $this->ok(['redirect' => '/user'], '登录成功');
    }

    public function showForgot(): never
    {
        if (UserAuth::check()) {
            Response::redirect('/user');
        }
        $this->view('user/forgot', ['title' => '找回密码 · ' . SiteChrome::brand()], 'layouts/site');
    }

    /**
     * 找回密码第一步：校验图形验证码后向邮箱发送验证码
     */
    public function sendResetCode(): never
    {
        $this->verifyCsrf();
        try {
            CaptchaService::verify('reset', (string) Request::input('captcha', ''));
            AuthService::requestPasswordReset(
                Request::input('username'),
                Request::input('email'),
                Request::ip()
            );
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '验证码已发送至邮箱，请查收（含垃圾邮件箱）');
    }

    /**
     * 找回密码第二步：邮箱验证码 + 新密码
     */
    public function resetPassword(): never
    {
        $this->verifyCsrf();
        try {
            AuthService::resetPassword(
                Request::input('username'),
                Request::input('email'),
                Request::input('code'),
                Request::input('password'),
                Request::input('password_confirm')
            );
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['redirect' => '/user/login'], '密码已重置，请使用新密码登录');
    }

    public function logout(): never
    {
        $this->verifyCsrf();
        UserAuth::logout();
        $this->ok(['redirect' => '/']);
    }
}
