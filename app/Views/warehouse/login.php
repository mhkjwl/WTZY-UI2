<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/login.php
 * @description 仓库端登录页（与管理员端/用户端共用同一分栏版式）
 * @author AI
 * @version 0.6.1
 * @date 2026-10-09
 */

use App\Core\View;
?>
<?= View::partial('partials/back_login', [
    'formId'       => 'wh-login-form',
    'loginUrl'     => '/warehouse/login',
    'title'        => '仓库端登录',
    'sub'          => '操作员 / 主管 / 打包员 / 出库员账号',
    'accountLabel' => '工号',
    'accountField' => 'username',
    'mark'         => '🏭',
    'captchaScene' => 'login_warehouse',
    'heroTag'      => '仓库端',
    'heroTitle'    => '仓库作业系统',
    'heroDesc'     => '面向海外仓的现场作业台：扫码收货、称重上架、打包计费、出库交接与异常处置。',
    'heroPoints'   => [
        '扫码快速匹配包裹归属用户',
        '称重量尺自动换算体积重',
        '打包实重复核并重算运费',
        '异常登记与退运销毁处置',
    ],
]) ?>
