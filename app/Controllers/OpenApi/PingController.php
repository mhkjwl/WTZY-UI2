<?php declare(strict_types=1);
/**
 * @file app/Controllers/OpenApi/PingController.php
 * @description 开放 API 健康检查（连通性自测端点）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Controllers\OpenApi;

final class PingController extends BaseController
{
    public function ping(): never
    {
        $this->ok([
            'service' => 'wtzy-openapi',
            'version' => APP_VERSION,
            'time'    => date('c'),
        ], 'pong');
    }
}
