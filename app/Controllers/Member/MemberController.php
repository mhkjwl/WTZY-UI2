<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/MemberController.php
 * @description 会员中心：会员权益、优惠券、积分明细、邀请返利
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Controllers\Member;

use App\Core\Request;
use App\Models\CouponModel;
use App\Models\InviteModel;
use App\Models\MemberLevelModel;
use App\Models\PointsModel;
use InvalidArgumentException;

final class MemberController extends BaseController
{
    /**
     * 会员权益与成长进度
     */
    public function index(): never
    {
        $this->requireLogin();
        $this->memberView('member/member', [
            'title'     => '会员权益',
            'levels'    => MemberLevelModel::all(),
            'current'   => MemberLevelModel::find((int) $this->user['member_level']),
            'nextLevel' => MemberLevelModel::nextLevel((int) $this->user['member_level'], (int) $this->user['points']),
            'points'    => (int) $this->user['points'],
            'couponCounts' => CouponModel::counts($this->userId()),
        ], 'member', '会员权益');
    }

    /**
     * 我的优惠券（含可领取）
     */
    public function coupons(): never
    {
        $this->requireLogin();
        $this->memberView('member/coupons', [
            'title'       => '我的优惠券',
            'mine'        => CouponModel::mine($this->userId()),
            'receivable'  => CouponModel::receivable($this->userId()),
            'counts'      => CouponModel::counts($this->userId()),
        ], 'coupons', '我的优惠券');
    }

    /**
     * 我的券包局部渲染（领券后区域替换）
     */
    public function couponList(): never
    {
        $this->requireLogin();
        $this->renderTable('member/coupons_mine', [
            'mine' => CouponModel::mine($this->userId()),
        ]);
    }

    public function receiveCoupon(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        try {
            CouponModel::receive($this->userId(), (int) Request::input('id', 0));
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '优惠券已放入我的券包');
    }

    /**
     * 积分明细
     */
    public function points(): never
    {
        $this->requireLogin();
        $page = $this->pageParams();
        $result = PointsModel::logs($this->userId(), $page['limit'], $page['offset']);

        $data = [
            'title'  => '积分明细',
            'points' => (int) $this->user['points'],
            'rows'   => $result['rows'],
            'total'  => $result['total'],
            'page'   => $page['page'],
            'pages'  => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'nextLevel' => MemberLevelModel::nextLevel((int) $this->user['member_level'], (int) $this->user['points']),
            'pendingPoints' => PointsModel::pendingSum($this->userId()),
        ];
        if ($this->isPartial()) {
            $this->renderTable('member/points_table', $data);
        }
        $this->memberView('member/points', $data, 'points', '积分明细');
    }

    /**
     * 邀请返利
     */
    public function invite(): never
    {
        $this->requireLogin();
        $page = $this->pageParams(10);
        $users = InviteModel::invitedUsers($this->userId(), $page['limit'], $page['offset']);
        // 返利明细只展示最近 10 条，避免与邀请列表共用分页造成歧义
        $rewards = InviteModel::rewards($this->userId(), 10, 0);

        $data = [
            'title'      => '邀请返利',
            'inviteCode' => InviteModel::inviteCode((string) $this->user['user_no']),
            'summary'    => InviteModel::summary($this->userId()),
            'invitees'   => $users['rows'],
            'inviteeTotal' => $users['total'],
            'page'       => $page['page'],
            'pages'      => (int) ceil($users['total'] / $page['limit']) ?: 1,
            'rewards'    => $rewards['rows'],
            'rewardTotal' => $rewards['total'],
        ];
        // 翻页只回邀请记录区域，不整页刷新（规范 §2.5）
        if ($this->isPartial()) {
            $this->renderTable('member/invite_region', $data);
        }
        $this->memberView('member/invite', $data, 'invite', '邀请返利');
    }
}
