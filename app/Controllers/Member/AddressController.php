<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/AddressController.php
 * @description 会员中心：海外仓专属收货地址 + 转运目的地收货地址
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Controllers\Member;

use App\Core\Request;
use App\Models\UserAddressModel;
use App\Models\WarehouseAddressModel;
use InvalidArgumentException;

final class AddressController extends BaseController
{
    /**
     * 海外仓专属收货地址（注册时按仓库分配）
     */
    public function warehouses(): never
    {
        $this->requireLogin();
        $this->memberView('member/addresses', [
            'title'      => '海外仓地址',
            'addresses'  => WarehouseAddressModel::listForUser($this->userId()),
            'userCode'   => (string) ($this->user['user_code'] ?? ''),
        ], 'addresses', '海外仓地址');
    }

    /**
     * 转运目的地收货地址
     */
    public function shipping(): never
    {
        $this->requireLogin();
        $this->memberView('member/shipping', [
            'title'     => '收货地址',
            'addresses' => UserAddressModel::listForUser($this->userId()),
        ], 'shipping', '收货地址');
    }

    /**
     * 收货地址列表局部渲染（保存/删除后区域替换，不整页刷新）
     */
    public function shippingList(): never
    {
        $this->requireLogin();
        $this->renderTable('member/shipping_list', [
            'addresses' => UserAddressModel::listForUser($this->userId()),
        ]);
    }

    public function saveShipping(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        $data = [
            'receiver_name'  => trim((string) Request::input('receiver_name', '')),
            'receiver_phone' => trim((string) Request::input('receiver_phone', '')),
            'country'        => strtoupper(trim((string) Request::input('country', ''))),
            'state'          => trim((string) Request::input('state', '')),
            'city'           => trim((string) Request::input('city', '')),
            'district'       => trim((string) Request::input('district', '')),
            'address1'       => trim((string) Request::input('address1', '')),
            'address2'       => trim((string) Request::input('address2', '')),
            'zip_code'       => trim((string) Request::input('zip_code', '')),
            'is_default'     => (int) Request::input('is_default', 0) === 1 ? 1 : 0,
        ];
        if ($data['receiver_name'] === '') {
            $this->fail('请填写收件人姓名');
        }
        if ($data['receiver_phone'] === '') {
            $this->fail('请填写收件人电话');
        }
        if (preg_match('/^[A-Z]{2}$/', $data['country']) !== 1) {
            $this->fail('国家/地区码须为 2 位字母，如 US / JP');
        }
        if ($data['state'] === '' || $data['city'] === '' || $data['address1'] === '' || $data['zip_code'] === '') {
            $this->fail('州/省、城市、地址行1与邮编均为必填');
        }

        try {
            UserAddressModel::save($this->userId(), $data, (int) Request::input('id', 0));
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '收货地址已保存');
    }

    public function deleteShipping(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();
        if (!UserAddressModel::softDelete((int) Request::input('id', 0), $this->userId())) {
            $this->fail('收货地址不存在或已删除');
        }
        $this->ok(null, '收货地址已删除');
    }
}
