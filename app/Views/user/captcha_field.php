<?php declare(strict_types=1);
/**
 * @file app/Views/user/captcha_field.php
 * @description 图形验证码输入项（登录 / 注册 / 找回密码共用）
 * @author AI
 * @version 0.6
 * @date 2026-10-08
 */

use App\Core\View;

/**
 * @var string $scene 场景标识，决定验证码会话槽位
 */
$scene = $scene ?? 'login';
?>
<div class="mb-3">
    <label class="form-label" for="captcha-<?= View::e($scene) ?>">验证码</label>
    <div class="ly-captcha">
        <input class="form-control" type="text" id="captcha-<?= View::e($scene) ?>" maxlength="4"
               inputmode="numeric" autocomplete="off" placeholder="4 位数字" required>
        <img class="ly-captcha__img" id="captcha-img-<?= View::e($scene) ?>"
             src="/captcha?scene=<?= View::e($scene) ?>" alt="图形验证码" title="点击刷新"
             onclick="refreshCaptcha('<?= View::e($scene) ?>')">
    </div>
    <p class="form-text text-muted mb-0">看不清？点击图片更换</p>
</div>