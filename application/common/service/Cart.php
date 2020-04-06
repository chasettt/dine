<?php
/**
 * Created by PhpStorm.
 * User: teng
 * Date: 2020/4/6
 * Time: 6:34 PM
 */

namespace app\common\service;

use lib\Redis;

/**
 * shopping cart
 * Class Cart
 * @package app\common\service
 */
class Cart extends Base
{
    public function addFood($data)
    {
        $token        = $data['token'] ?? '';
        $param        = $data['param'] ?? 'users'; // users , table, takeaway
        $source       = $data['source'] ?? 'online';
        $foodCode     = $data['food_code'] ?? 0;
        $foodNumber   = $data['food_number'] ?? 1;
        $foodRemark   = $data['food_remark'] ?? '';
        $openid       = $data['openid'] ?? '';
        $tableNo      = $data['table_id'] ?? '';
        $storeId      = $data['store_code'] ?? 0;
        $isMultiCombo = $data['is_multi_combo'] ?? 0; //是否是自选套餐
        $comboDetail  = $data['combo_detail'] ?? []; //自选套餐详情
        $comboKey     = $data['combo_key'] ?? ''; //前端生成套餐唯一KEY

        $this->oauth($token);

        if ('' == $param) {
            return $this->returnMsg(0, '请求参数错误');
        }

        $shoppingService = model('common/shopping', 'service');
        switch ($param) {
            case 'users':
                if (!$source or !$foodCode or !$foodNumber or !$storeId) {
                    return $this->returnMsg(0, '请求参数错误');
                }

                if ('' != $openid) {
                    $weChatOpenid = $openid;
                } else {
                    $weChatOpenid = $this->openid;
                }

                $shoppingService->addFood($storeId, $weChatOpenid, [
                    'source'       => $source,
                    'food_code'    => $foodCode,
                    'food_number'  => (int)$foodNumber,
                    'food_remark'  => $foodRemark,
                    'order_remark' => '',
                    'combo_detail' => $comboDetail,
                    'combo_key'    => $comboKey,
                ], $tableNo, $isMultiCombo);

                break;
            case 'table':
                if (!$tableNo or !$storeId) {
                    return $this->returnMsg(0, '请求参数错误');
                }
                $shoppingService->addUserToTable($storeId, $tableNo, $this->openid);
                break;
        }

        return $this->returnMsg(200, 'success');
    }

    public function delFood($data)
    {
        $token        = $data['token'] ?? '';
        $param        = $data['param'] ?? 'users'; // users , table, takeaway
        $foodCode     = $data['food_code'] ?? 0;
        $foodNumber   = $data['food_number'] ?? 1;
        $openid       = $data['openid'] ?? '';
        $storeId      = $data['store_code'] ?? 0;
        $isMultiCombo = $data['is_multi_combo'] ?? 0; //是否是自选套餐
        $comboKey     = $data['combo_key'] ?? ''; //前端生成套餐唯一KEY

        $this->oauth($token);

        if ('' == $param) {
            return $this->returnMsg(0, '请求参数错误');
        }

        $shoppingService = model('common/shopping', 'service');
        switch ($param) {
            case 'users':
                if (!$foodCode || !$foodNumber) {
                    return $this->returnMsg(0, '请求参数错误');
                }

                if ('' != $openid) {
                    $weChatOpenid = $openid;
                } else {
                    $weChatOpenid = $this->openid;
                }
                $shoppingService->delFood($storeId, $weChatOpenid, [
                    'food_code'   => $foodCode,
                    'food_number' => $foodNumber,
                    'combo_key'   => $comboKey,
                ], $isMultiCombo);

                break;
        }

        return $this->returnMsg(200, 'success');
    }

    public function addUserToTable($data, $fd)
    {
        $storeId = $data['store_id'] ?? 0;
        $tableNo = $data['table_no'] ?? '';
        $token   = $data['token'] ?? '';
        $this->oauth($token);

        if (empty($storeId) || empty($token) || empty($tableNo)) {
            return $this->returnMsg(0, '参数错误');
        }

        $this->getRedis()->select(0);
        $this->getRedis()->hSet(config('cache_keys.con_table_user') . ":{$storeId}:{$tableNo}",
            $this->openid, $fd);

        $this->getRedis()->expire(config('cache_keys.con_table_user') . ":{$storeId}:{$tableNo}", 7200);

        return $this->returnMsg(200, 'success');
    }

    public function notifyCart($server, $storeId, $tableNo)
    {
        $this->getRedis()->select(0);
        $connections = $this->getRedis()->hGetAll(config('cache_keys.con_table_user') . ":{$storeId}:{$tableNo}");

        $list = model('common/shopping', 'service')->getTableUserCart($storeId, $tableNo, $this->openid);

        if (!empty($connections)) {
            foreach ($connections as $conn) {
                $server->push($conn, json_encode($this->returnMsg(200, 'success', $list, 'food_notify')));
            }
        }
        return true;
    }
}