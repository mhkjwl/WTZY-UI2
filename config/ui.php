<?php declare(strict_types=1);
/**
 * @file config/ui.php
 * @description 新界面（光年 v5）迁移进度清单：登记已切换新版的视图模板名。
 *              布局对已登记的页面停用旧样式表（assets/common 下的历史主题），
 *              未登记的页面继续维持原观感；每完成一批页面在此追加登记。
 * @author AI
 * @version 0.6
 * @date 2026-10-08
 */

return [
    // 认证与无权限提示（新版认证页外壳、空状态卡片）
    'user/login',
    'user/register',
    'user/forgot',
    'admin/denied',
    'warehouse/denied',
    // A1 批：控制台 + 用户管理 + 实名审核
    'admin/home',
    'admin/users',
    'admin/users_kyc',
    // A2 批：包裹管理 + 订单运单（列表局部表为 AJAX 片段，随父页生效，无需登记）
    'admin/packages',
    'admin/package_detail',
    'admin/package_missing',
    'admin/orders',
    'admin/order_missing',
    'admin/waybill_detail',
    // A3 批：渠道费率 + 报关 + 增值服务（列表局部表与详情内容区为 AJAX 片段，随父页生效，无需登记）
    'admin/channels',
    'admin/channel_detail',
    'admin/rates',
    'admin/customs',
    'admin/services',
    // A4 批：工单 + 理赔（列表局部表与对话记录区为 AJAX 片段，随父页生效，无需登记）
    'admin/tickets',
    'admin/ticket_detail',
    'admin/ticket_missing',
    'admin/claims',
    'admin/claim_detail',
    // A5 批：财务 + 发票 + 报表（内容区为 AJAX 片段，随父页生效，无需登记）
    'admin/finance',
    'admin/finance_recharge_settings',
    'admin/invoices',
    'admin/reports',
    'admin/warehouse_reports',
    // A6 批：营销 + 会员体系（公告区与券列表为 AJAX 片段，随父页生效，无需登记）
    'admin/marketing',
    'admin/member_levels',
    'admin/member_coupons',
    'admin/member_invite',
    // A7 批：系统设置 + 仓库管理（角色矩阵、员工表、仓库内容区为 AJAX 片段，随父页生效，无需登记）
    'admin/system_site',
    'admin/system_mail',
    'admin/system_tasks',
    'admin/system_staff',
    'admin/system_roles',
    'admin/warehouse',
    // Phase 3 B1 批：仓库端 工作台 + 入库 + 打包（列表局部表为 AJAX 片段，随父页生效，无需登记）
    'warehouse/home',
    'warehouse/inbound',
    'warehouse/packing',
    'warehouse/packing_detail',
    // Phase 3 B2 批：仓库端 仓储 + 出库 + 异常 + 任务（列表与库位区块为 AJAX 片段，无需登记）
    'warehouse/storage',
    'warehouse/outbound',
    'warehouse/abnormal',
    'warehouse/tasks',
    // Phase 3 B3 批：仓库端 包裹详情 + 缺件页（详情内容区为 AJAX 片段，随父页生效，无需登记）
    'warehouse/package_detail',
    'warehouse/package_missing',
    'warehouse/waybill_missing',
    // Phase 4 C1 批：会员端 工作台 + 运单 + 物流跟踪（列表与轨迹区为 AJAX 片段，无需登记）
    'member/dashboard',
    'member/waybills',
    'member/waybill_create',
    'member/waybill_detail',
    'member/waybill_missing',
    'member/tracking',
    // Phase 4 C2 批：会员端 包裹 + 预报 + 异常（列表与异常区为 AJAX 片段，随父页生效，无需登记）
    'member/packages',
    'member/package_detail',
    'member/package_missing',
    'member/forecast',
    'member/abnormal',
    // Phase 4 C3 批：会员端 消息 + 公告 + 工单（消息列表、公告列表、工单列表与对话记录为 AJAX 片段，无需登记）
    'member/messages',
    'member/notices',
    'member/notice_detail',
    'member/tickets',
    'member/ticket_create',
    'member/ticket_detail',
    'member/ticket_missing',
    // Phase 4 C4 批：会员端 财务 + 充值 + 发票（流水表、发票区、充值统计与记录区为 AJAX 片段，无需登记）
    'member/finance',
    'member/flows',
    'member/invoices',
    'member/recharge',
    // Phase 4 C5 批：会员端 会员等级 + 积分 + 优惠券 + 邀请 + 资料实名 + 地址 + 理赔（券包、积分表、邀请记录、收货地址表、理赔表为 AJAX 片段，无需登记）
    'member/member',
    'member/points',
    'member/coupons',
    'member/invite',
    'member/profile',
    'member/kyc',
    'member/addresses',
    'member/shipping',
    'member/claims',
    'member/claim_create',
    'member/claim_detail',
    'member/claim_missing',
    // 系统页补录：支付回跳结果页（经 base 布局）与错误页（独立外壳，不依赖布局）
    'pay/result',
    'errors/404',
    'errors/500',
];