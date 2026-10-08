<?php declare(strict_types=1);
/**
 * @file app/Core/Response.php
 * @description 响应输出：JSON 统一信封与重定向
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */

namespace App\Core;

final class Response
{
    /**
     * 全系统接口统一信封：{success, message, data}，前端据此走 Notify 反馈
     */
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 仅用于页面间真实跳转（如未登录跳登录页）；操作后的反馈一律走局部更新（规范 §2.5）
     */
    public static function redirect(string $to): never
    {
        header('Location: ' . $to);
        exit;
    }
}
