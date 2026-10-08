<?php declare(strict_types=1);
/**
 * @file app/Controllers/Web/CaptchaController.php
 * @description 图形验证码输出（SVG，按场景独立存储：login / register / reset）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\Request;
use App\Services\CaptchaService;

final class CaptchaController extends Controller
{
    private const SCENES = ['login', 'register', 'reset'];

    public function image(): never
    {
        $scene = (string) Request::query('scene', 'login');
        if (!in_array($scene, self::SCENES, true)) {
            $scene = 'login';
        }

        $svg = CaptchaService::issue($scene);

        header('Content-Type: image/svg+xml; charset=utf-8');
        // 验证码不可缓存，否则刷新拿到的还是旧图
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        echo $svg;
        exit;
    }
}
