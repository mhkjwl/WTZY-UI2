<?php declare(strict_types=1);
/**
 * @file app/Views/admin/login.php
 * @description 管理后台登录页（与仓库端/用户端共用同一分栏版式）
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

use App\Core\View;
?>
<?= View::partial('partials/back_login', [
    'formId'       => 'admin-login-form',
    'loginUrl'     => '/admin/login',
    'title'        => '管理后台登录',
    'sub'          => '运营 / 客服 / 财务 / 管理员账号',
    'accountLabel' => '账号',
    'accountField' => 'username',
    'mark'         => '🛠️',
    'captchaScene' => 'login_admin',
    'heroTag'      => '管理后台',
    'heroTitle'    => '全球转运管理系统',
    'heroDesc'     => '从海外仓收货到末端派送的全链路管理：用户与订单、仓库与渠道、财务对账、客服工单一站完成。',
    'heroPoints'   => [
        '用户、包裹与运单全流程管理',
        '仓库、物流渠道与清关配置',
        '充值审核、退款对账与数据报表',
        '客服工单、实名审核与营销内容',
    ],
]) ?>
