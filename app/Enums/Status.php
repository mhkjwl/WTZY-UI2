<?php declare(strict_types=1);
/**
 * @file app/Enums/Status.php
 * @description 全局状态机文案与编码（四端共用，禁止各端硬编码状态字符串）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-15
 */

namespace App\Enums;

final class Status
{
    public const USER = [
        0 => '待验证', 1 => '正常', 2 => '已禁用', 3 => '黑名单',
    ];

    /**
     * 用户状态编码（users.status）
     * 2 与 3 都会拦截登录并踢下线，但处置性质不同：2 禁用只是停用这一个账号，
     * 解除后可照常使用；3 黑名单是身份级封禁，同一手机号/邮箱/证件号都无法重新注册
     */
    public const USER_PENDING   = 0;
    public const USER_ACTIVE    = 1;
    public const USER_DISABLED  = 2;
    public const USER_BLACKLIST = 3;

    /** 风控等级（后台人工设定的管控强度） */
    public const RISK = [
        0 => '正常', 1 => '关注', 2 => '限制',
    ];

    /** 实名认证状态 */
    public const KYC = [
        0 => '未认证', 1 => '待审核', 2 => '已认证', 3 => '已驳回',
    ];

    /** 包裹物品类别（影响可选渠道与是否加收敏感货附加费） */
    public const PACKAGE_CATEGORY = [
        1 => '普货', 2 => '敏感货', 3 => '带电', 4 => '液体膏体', 5 => '品牌类', 6 => '其他',
    ];

    /** 包裹异常 / 用户处置申请类型 */
    public const ABNORMAL_TYPE = [
        1 => '破损', 2 => '缺件', 3 => '错发', 4 => '禁运', 5 => '无主件',
        6 => '申请退运', 7 => '申请销毁',
    ];

    /** 异常/处置单状态（status 列实际用法：0 待仓库处理、1 仓库已处理；3 已关闭含用户取消） */
    public const ABNORMAL_STATUS = [
        0 => '处理中', 1 => '已处理', 2 => '已解决', 3 => '已关闭',
    ];

    /** 资金流水类型 */
    public const FLOW_TYPE = [
        1 => '充值', 2 => '运费支付', 3 => '退款', 4 => '仓储费',
        5 => '理赔赔偿', 6 => '人工调整', 7 => '提现', 8 => '邀请返利',
    ];

    /** 资金流向 */
    public const FLOW_DIRECTION = [1 => '收入', 2 => '支出'];

    /** 充值支付方式（1/2 与 7/8 走在线网关，5 银行转账人工入账，6 后台人工入账；3/4 为历史保留渠道） */
    public const PAY_METHOD = [
        1 => '支付宝', 2 => '微信支付', 3 => 'PayPal', 4 => '信用卡',
        5 => '银行转账', 6 => '后台人工入账', 7 => 'QQ钱包', 8 => 'TRX-TRC20',
    ];

    /** 积分业务类型 */
    public const POINTS_TYPE = [
        1 => '注册奖励', 2 => '下单奖励', 3 => '邀请奖励', 4 => '积分抵扣', 5 => '人工调整',
        6 => '充值赠送', 7 => '消费赠送', 8 => '签收到账', 9 => '积分取消', 10 => '积分扣回',
    ];

    /** 登录方式编码（与 user_auths.auth_type 对应） */
    public const AUTH_PHONE = 1;
    public const AUTH_EMAIL = 2;

    public const PACKAGE = [
        0 => '待预报', 1 => '已预报', 2 => '已到仓', 3 => '待认领', 4 => '已认领',
        5 => '待打包', 6 => '打包中', 7 => '待支付', 8 => '已支付', 9 => '待出库',
        10 => '已出库', 11 => '运输中', 12 => '清关中', 13 => '派送中', 14 => '已签收',
        15 => '异常', 16 => '已退回', 17 => '已销毁', 18 => '已拦截',
    ];

    public const WAYBILL = [
        0 => '待提交', 1 => '待称重', 2 => '待补款', 3 => '已支付', 4 => '待出库',
        5 => '已出库', 6 => '运输中', 7 => '清关中', 8 => '派送中', 9 => '已签收',
        10 => '售后中', 11 => '已取消', 12 => '已拦截',
    ];

    public const CLEAR = [
        0 => '无', 1 => '待清关', 2 => '清关中', 3 => '已放行', 4 => '清关异常', 5 => '已完税',
    ];

    public const TICKET = [
        0 => '待受理', 1 => '处理中', 2 => '已回复', 3 => '已关闭',
    ];

    /** 工单类型 */
    public const TICKET_CATEGORY = [
        1 => '包裹异常', 2 => '运输理赔', 3 => '费用咨询', 4 => '其他',
    ];

    public const RECHARGE = [
        0 => '待支付', 1 => '已支付', 2 => '支付失败', 3 => '已退款', 4 => '已关闭', 5 => '待入账',
    ];

    /** 发票申请状态 */
    public const INVOICE = [
        0 => '待开票', 1 => '已开票', 2 => '已驳回',
    ];

    public static function user(int $code): string
    {
        return self::USER[$code] ?? '未知';
    }

    /**
     * 账号不可用时的拦截文案：登录校验与在途会话校验共用同一份判定，
     * 保证「被禁用后既登不进来、也留不住会话」两种场景给出的提示完全一致
     * @return string|null 状态正常返回 null
     */
    public static function blockedReason(int $status): ?string
    {
        return match ($status) {
            self::USER_PENDING   => '此账号尚未完成验证，请先完成邮箱或手机验证',
            self::USER_DISABLED  => '此账号已被禁用，请联系客服',
            self::USER_BLACKLIST => '此账号已被列入黑名单，请联系客服',
            default => null,
        };
    }

    public static function risk(int $code): string
    {
        return self::RISK[$code] ?? '未知';
    }

    public static function package(int $code): string
    {
        return self::PACKAGE[$code] ?? '未知';
    }

    /**
     * 包裹状态色调：按「预报 → 在仓 → 打包 → 出库 → 在途 → 签收」的推进阶段分段着色，
     * 待办类（待认领/待打包/待支付）用暖色提醒、在途类用冷色、终态（签收/退回/销毁）用绿或灰
     */
    public static function packageTone(int $code): string
    {
        return match ($code) {
            0  => 'gray',    // 待预报
            1  => 'sky',     // 已预报
            2  => 'blue',    // 已到仓
            3  => 'orange',  // 待认领
            4  => 'indigo',  // 已认领
            5  => 'amber',   // 待打包
            6  => 'violet',  // 打包中
            7  => 'rose',    // 待支付
            8  => 'blue',    // 已支付
            9  => 'orange',  // 待出库
            10 => 'cyan',    // 已出库
            11 => 'sky',     // 运输中
            12 => 'violet',  // 清关中
            13 => 'indigo',  // 派送中
            14 => 'green',   // 已签收
            15 => 'red',     // 异常
            16 => 'gray',    // 已退回
            17 => 'gray',    // 已销毁
            18 => 'rose',    // 已拦截
            default => 'gray',
        };
    }

    public static function waybill(int $code): string
    {
        return self::WAYBILL[$code] ?? '未知';
    }

    /**
     * 运单状态色调：待补款/已拦截用红色示警，已签收用绿色，
     * 出库前后各阶段用不同冷/暖色区分，取消与待提交用灰色
     */
    public static function waybillTone(int $code): string
    {
        return match ($code) {
            0  => 'gray',    // 待提交
            1  => 'amber',   // 待称重
            2  => 'red',     // 待补款
            3  => 'blue',    // 已支付
            4  => 'orange',  // 待出库
            5  => 'cyan',    // 已出库
            6  => 'sky',     // 运输中
            7  => 'violet',  // 清关中
            8  => 'indigo',  // 派送中
            9  => 'green',   // 已签收
            10 => 'purple',  // 售后中
            11 => 'gray',    // 已取消
            12 => 'rose',    // 已拦截
            default => 'gray',
        };
    }

    /**
     * 出库交接前的四步作业流程（仓库端打包作业页进度条，与 waybillStep 的返回值对应）
     */
    public const WAYBILL_STEPS = [
        1 => '① 用户提交订单', 2 => '② 仓库拣货打包称重', 3 => '③ 用户支付运费', 4 => '④ 仓库出库交接',
    ];

    /**
     * 运单在四步作业流程中的位置：1 已提交待打包 → 2 拣货打包中 → 3 待用户支付/补款 → 4 待出库交接
     * 返回 5 表示四步均已完成；返回 0 表示流程已终止（取消/拦截），此时不指向任何进行中的步骤
     */
    public static function waybillStep(int $code): int
    {
        return match (true) {
            $code === 0 => 1,
            $code === 1 => 2,
            $code === 2 => 3,
            $code === 3, $code === 4 => 4,
            $code >= 5 && $code <= 10 => 5,
            default => 0,
        };
    }

    /**
     * 打包作业不可继续时的说明：待补款要讲清还差多少，已打包与已出库要区分清楚
     */
    public static function packingBlockedNote(int $code, float $dueAmount): string
    {
        return match (true) {
            $code === 2 => '该运单已完成打包测重，需向用户补收差额 ¥' . number_format($dueAmount, 2) . '，用户补缴后即可出库。',
            $code === 4 => '该运单已完成打包测重，等待出库交接。',
            $code >= 5 => '该运单已出库，打包作业已完成。',
            default => '当前状态不可打包。',
        };
    }

    public static function clear(int $code): string
    {
        return self::CLEAR[$code] ?? '未知';
    }

    /** 清关状态色调：待清关暖色、清关中紫色、放行完税绿色、异常红色 */
    public static function clearTone(int $code): string
    {
        return match ($code) {
            0 => 'gray',    // 无
            1 => 'orange',  // 待清关
            2 => 'violet',  // 清关中
            3 => 'green',   // 已放行
            4 => 'red',     // 清关异常
            5 => 'emerald', // 已完税
            default => 'gray',
        };
    }

    public static function ticket(int $code): string
    {
        return self::TICKET[$code] ?? '未知';
    }

    /** 工单状态色调：待受理暖色、处理中蓝色、已回复青色、关闭灰色 */
    public static function ticketTone(int $code): string
    {
        return match ($code) {
            0 => 'orange',  // 待受理
            1 => 'blue',    // 处理中
            2 => 'emerald', // 已回复
            3 => 'gray',    // 已关闭
            default => 'gray',
        };
    }

    public static function recharge(int $code): string
    {
        return self::RECHARGE[$code] ?? '未知';
    }

    public static function invoice(int $code): string
    {
        return self::INVOICE[$code] ?? '未知';
    }

    /** 充值单状态色调：待支付/待入账为待办暖色，成功绿色、失败红色、退款关闭灰色 */
    public static function rechargeTone(int $code): string
    {
        return match ($code) {
            0 => 'orange',  // 待支付
            1 => 'green',   // 已支付
            2 => 'red',     // 支付失败
            3 => 'gray',    // 已退款
            4 => 'gray',    // 已关闭
            5 => 'violet',  // 待入账
            default => 'gray',
        };
    }

    /** 发票申请状态色调：待开票为待办暖色，已开票绿色，已驳回玫红 */
    public static function invoiceTone(int $code): string
    {
        return match ($code) {
            0 => 'orange',  // 待开票
            1 => 'green',   // 已开票
            2 => 'rose',    // 已驳回
            default => 'gray',
        };
    }

    /** 用户账号状态色调：待验证琥珀、正常绿色、禁用玫瑰、黑名单红色 */
    public static function userTone(int $code): string
    {
        return match ($code) {
            0 => 'amber',   // 待验证
            1 => 'green',   // 正常
            2 => 'rose',    // 已禁用
            3 => 'red',     // 黑名单
            default => 'gray',
        };
    }

    /** 风控等级色调：正常绿色、关注琥珀、限制红色 */
    public static function riskTone(int $code): string
    {
        return match ($code) {
            0 => 'green',   // 正常
            1 => 'amber',   // 关注
            2 => 'red',     // 限制
            default => 'gray',
        };
    }

    /** 实名认证状态色调：未认证灰色、待审核暖色、已认证绿色、已驳回红色 */
    public static function kycTone(int $code): string
    {
        return match ($code) {
            0 => 'gray',    // 未认证
            1 => 'orange',  // 待审核
            2 => 'green',   // 已认证
            3 => 'red',     // 已驳回
            default => 'gray',
        };
    }

    /** 物品类别色调：普货绿色安全，敏感/带电/液体/品牌依次用不同警示色 */
    public static function categoryTone(int $code): string
    {
        return match ($code) {
            1 => 'green',   // 普货
            2 => 'orange',  // 敏感货
            3 => 'amber',   // 带电
            4 => 'cyan',    // 液体膏体
            5 => 'purple',  // 品牌类
            default => 'gray',
        };
    }

    /** 包裹异常类型色调：处置类（破损/禁运）红色，缺件错发暖色，退运销毁紫色系 */
    public static function abnormalTone(int $code): string
    {
        return match ($code) {
            1 => 'red',     // 破损
            2 => 'orange',  // 缺件
            3 => 'amber',   // 错发
            4 => 'red',     // 禁运
            5 => 'gray',    // 无主件
            6 => 'violet',  // 申请退运
            7 => 'rose',    // 申请销毁
            default => 'gray',
        };
    }

    /** 异常/处置单状态色调：进行中暖色、解决绿、关闭灰（用户取消的申请保留记录但不再处理） */
    public static function abnormalStatusTone(int $code): string
    {
        return match ($code) {
            0 => 'orange',  // 待处理
            1 => 'blue',    // 处理中
            2 => 'green',   // 已解决
            3 => 'gray',    // 已关闭
            default => 'gray',
        };
    }

    /** 理赔状态色调：流转中各审核节点用不同色，已驳回红、已赔付绿 */
    public static function claimTone(int $code): string
    {
        return match ($code) {
            0 => 'orange',  // 待受理
            1 => 'blue',    // 处理中
            2 => 'cyan',    // 初审通过
            3 => 'red',     // 已驳回
            4 => 'green',   // 已赔付
            5 => 'purple',  // 财务已审
            6 => 'violet',  // 管理员已审
            default => 'gray',
        };
    }

    public static function payMethod(int $code): string
    {
        return self::PAY_METHOD[$code] ?? '—';
    }
}
