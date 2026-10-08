<?php declare(strict_types=1);
/**
 * @file app/Core/Controller.php
 * @description 控制器抽象基类：视图渲染、统一 JSON 信封、CSRF 校验
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Core;

abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/base'): never
    {
        $data['csrf'] = $this->csrfToken();
        View::render($template, $data, $layout);
        exit;
    }

    protected function ok(mixed $data = null, string $message = '操作成功'): never
    {
        Response::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    protected function fail(string $message, int $status = 400, mixed $data = null): never
    {
        Response::json(['success' => false, 'message' => $message, 'data' => $data], $status);
    }

    protected function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }
        return (string) $_SESSION['_csrf'];
    }

    /**
     * 写操作 CSRF 校验（规范 §8.2.3）：令牌取 X-CSRF-Token 头或表单 _token 字段
     */
    protected function verifyCsrf(): void
    {
        $token = Request::header('X-CSRF-Token') ?? (string) (Request::input('_token') ?? '');
        if ($token === '' || !hash_equals($this->csrfToken(), $token)) {
            $this->fail('CSRF 校验失败，请刷新页面后重试', 403);
        }
    }
}
