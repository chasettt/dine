<?php
namespace app\api\controller;

/**
 * 快餐
 * Class Fastfood
 * @package app\api\controller
 */

use think\Db;
use think\Queue;

class Fastfood extends Base
{
    public function getOp()
    {
        $orderNo = input('post.order_sn', 0, 'int');

        if (!$orderNo) {
            return $this->returnMsg(0, '参数错误');
        }

        $field     = 'store_code,table_id,table_name,order_no,choice_id,order_state,pay_state,order_amount,order_amount_member,balance_amount,credit_amount,coupon_amount,pay_amount,pay_member,order_create_time,member_rules,order_type,takeaway_id,pay_time,invoice_url,take_dish_no';
        $orderInfo = model('common/order')->getOrderInfo([
            'order_no' => $orderNo,
        ], $field);

        if (empty($orderInfo)) {
            return $this->returnMsg(0, '未查询到订单信息');
        }
        $foodList = model('common/orderFood')->getFoodList([
            'order_no' => $orderInfo['order_no'],
        ], 'food_code,food_name,food_price,food_member_price,food_number,food_unit,food_weigh,food_state,food_discount,food_discount_member,food_modifiers,is_combo');

        $orderInfo['total_number'] = count($foodList);
        $orderInfo['food_list']    = $foodList;

        if ($orderInfo['order_state'] != 1) {
            $orderInfo['choice_id']  = '';
            $orderInfo['table_id']   = '';
            $orderInfo['table_name'] = '';
        }
        foreach ($orderInfo['food_list'] as &$food) {
            // 菜品折扣价
            $food['food_discount_price']        = intval(strval($food['food_price'] * 100)) - intval(strval($food['food_discount'] * 100));
            $food['food_discount_price']        = $food['food_discount_price'] / 100;
            $food['food_discount_member_price'] = intval(strval($food['food_member_price'] * 100)) - intval(strval($food['food_discount_member'] * 100));
            $food['food_discount_member_price'] = $food['food_discount_member_price'] / 100;
        }

        return $this->returnMsg(200, 'success', $orderInfo);
    }

    public function commitOp()
    {
        \think\Log::record('=============== 快餐订单提交开始： ===============', 'notice');
        $storeCode   = input('post.store_id', 0, 'int');
        $tableId     = input('post.table_id', 0, 'int');
        $people      = input('post.people', 0, 'int');
        $man         = input('post.man', 0, 'int');
        $child       = input('post.child', 0, 'int');
        $orderRemark = input('post.remark', '', 'string');
        $package     = input('post.package', 0, 'int');
        $orderType   = input('post.type', 0, 'string');

        \think\Log::record('=============== 订单提交信息： ===============' . print_r(input('post.'), true), 'notice');
        if (! $storeCode or ! $orderType) {
            return $this->returnMsg(0, '参数错误');
        }
        if ($orderType != 'takeout' && ! $people) {
            return $this->returnMsg(0, '参数错误');
        }

        Db::startTrans();
        try {
            $storeInfo = model('common/store')->getStore([
                'store_code'  => $storeCode,
                'store_state' => 1,
            ], 'payment_version, enabled_take_dish, enabled_welife_pay, enabled_packing_fee');

            if (empty($storeInfo)) {
                throw new \Exception('门店不存在');
            }

            // 购物车
            $shoppingService = model('common/shopping', 'service');
            $userCartInfo    = $shoppingService->getUserCart($this->openid, $storeCode);
            \think\Log::record('=============== 购物车信息： ===============' . print_r($userCartInfo, true), 'notice');
            if (empty($userCartInfo)) {
                throw new \Exception('购物车没有商品呦');
            }
            $userCartInfo = $userCartInfo['details'];

            // 判断是否需要取餐
            if ($orderType == 'takeout' || $storeInfo['enabled_take_dish'] == 1) {
                // 需要客人取餐
                $isTakeDish = 1;
            } else {
                // 服务员送餐
                $isTakeDish = 0;
            }

            // 快餐外带打包桶处理
            if ($storeInfo['enabled_packing_fee'] == 1 && ($package == 1 || $orderType == 'takeout')) {
                $packageInfo = $this->getRedis()->get(config('cache_keys.packing_fee') . ":{$storeCode}");
                if ($packageInfo) {
                    // 打包桶个数
                    $packTotal = 0;
                    foreach ($userCartInfo as $food_code => $food_info) {
                        if ($food_info['is_packing'] == 1) {
                            $packTotal += $food_info['food_number'];
                        }
                    }
                    if ($packTotal) {
                        $userCartInfo[$packageInfo['food_code']] = [
                            'food_code'         => $packageInfo['food_code'],
                            'food_name'         => $packageInfo['name'],
                            'food_number'       => $packTotal,
                            'food_unit'         => $packageInfo['unit'],
                            'food_price'        => $packageInfo['price'],
                            'food_member_price' => $packageInfo['member_price'],
                            'food_weigh'        => 0,
                            'food_remark'       => '',
                            'is_combo'          => 0,
                            'is_packing'        => 0,
                            'is_multiple_combo' => 0,
                        ];
                    }
                }
            }

            // 估清
            $this->_checkEstimates($storeCode, $userCartInfo);
            $orderData = [
                'store_code'   => $storeCode,
                'table_id'     => $tableId,
                'openid'       => $this->openid,
                'source'       => 'fastfood_' . $orderType,
                'order_state'  => 0,
                'order_type'   => 5,
                'order_people' => $people,
                'order_man'    => $man,
                'order_child'  => $child,
                'expire_time'  => time() + config('payment.expire_time'),
                'food_list'    => $userCartInfo,
                'order_remark' => $orderRemark,
                'is_take_dish' => $isTakeDish,
                'is_package'   => $package,
            ];
            \think\Log::record('=============== 订单信息： ===============' . print_r($orderData, true), 'notice');
            $orderInfo = model('common/order')->getOrderInfo(['create_users' => $this->openid, 'pay_state' => 0, 'order_type' => 5], 'order_no');
            if (!empty($orderInfo)) {
                // 创建订单
                $orderData['order_no'] = $orderInfo['order_no'];
            } else {
                $orderData['order_no'] = '';
            }

            $foodList[$this->openid]['shopping'] = $orderData['food_list'];
            unset($orderData['food_list']);

            $foodList = model('common/discount', 'service')->process($storeCode, $foodList, $this->openid, 2);

            $orderData['food_list']             = $foodList['food_list'];
            $orderData['order_discount']        = $foodList['order_discount'];
            $orderData['order_discount_member'] = $foodList['order_discount_member'];
            \think\Log::record('=============== 菜品优免处理后的订单信息： ===============' . print_r($orderData, true), 'notice');

            $orderSn = model('common/order', 'service')->createFastfoodOrder($orderData);
            \think\Log::record('=============== 生成的订单号是： ===============' . print_r($orderSn, true), 'notice');
            Db::commit();
        } catch (\Exception $e) {
            $this->failed($e->getCode(), $e->getMessage());
            \think\Log::record('=============== 快餐订单提交Exception： ===============' . print_r('错误码:' . $e->getCode() . '错误信息:' . $e->getMessage(), true), 'notice');
            Db::rollback();

            return $this->returnMsg($e->getCode(), $e->getMessage());
        }

        // 放入队列, 调用微信数据上报接口
        $jobData                = [
            'order_sn' => $orderSn,
            'food_data' => [
                'store_code' => $storeCode,
                'table_id'   => $tableId,
                'people'     => $people,
            ]
        ];
        $jobData['order_type']  = 5;
        $jobData['create_time'] = time();
        $jobData['openid']      = $this->openid;
        $jobData['state']       = 'order';

        $isPushed = Queue::push('app\common\job\WxOrderSync', $jobData, 'wx_order_sync');
        if (false === $isPushed) {
            \think\Log::error('添加消息队列失败: queue:wx_order_sync jobdata:' . json_encode($jobData));
        }

        return $this->returnMsg(200, 'success', ['order_sn' => $orderSn, 'welife_pay' => $storeInfo['enabled_welife_pay']]);
    }

    /**
     * 订单列表
     */
    public function getOrderListOp()
    {
        $page  = input('post.page', 1, 'int');
        $limit = input('post.limit', 10, 'int');

        $field     = 'store_code,table_id,order_no,choice_id,order_state,pay_state,pay_time,order_amount,order_amount_member,balance_amount,credit_amount,coupon_amount,pay_amount,pay_member,member_rules,order_source,order_create_time,invoice_url';
        $orderList = model('common/order')->getPersonalOrder([
            'create_users' => $this->openid,
            'order_source' => ['in', ['fastfood', 'fastfood_eatIn', 'fastfood_takeout']],
            'pay_state'    => 1,
        ], $page, $limit, 'order_id desc', $field);

        if (empty($orderList)) {
            return $this->returnMsg(200, 'success', []);
        }

        // 菜品列表
        $orderNoArr = implode(',', array_column($orderList, 'order_no'));
        $foodList   = model('common/orderFood')->getFoodList([
            'order_no' => ['in', $orderNoArr],
        ], 'order_no,food_code,food_name,food_price,food_member_price,food_number,food_unit,food_state,food_discount,food_discount_member');

        if (empty($foodList)) {
            return $this->returnMsg(200, 'success', $orderList);
        }

        foreach ($orderList as &$orderInfo) {

            if (is_null($orderInfo['choice_id'])) {
                $orderInfo['choice_id'] = '';
            }

            if (is_null($orderInfo['table_id'])) {
                $orderInfo['table_id'] = '';
            }

            // 门店信息
            $storeInfo               = $this->getRedis()->get(config('cache_keys.store_info') . ":{$orderInfo['store_code']}");
            $orderInfo['store_name'] = $storeInfo['store_name'];

            // 菜品信息
            foreach ($foodList as $foodInfo) {
                // 菜品折扣价
                $foodInfo['food_discount_price']        = intval(strval($foodInfo['food_price'] * 100)) - intval(strval($foodInfo['food_discount'] * 100));
                $foodInfo['food_discount_price']        = $foodInfo['food_discount_price'] / 100;
                $foodInfo['food_discount_member_price'] = intval(strval($foodInfo['food_member_price'] * 100)) - intval(strval($foodInfo['food_discount_member'] * 100));
                $foodInfo['food_discount_member_price'] = $foodInfo['food_discount_member_price'] / 100;
                if ($foodInfo['order_no'] == $orderInfo['order_no']) {
                    unset($foodInfo['order_no']);
                    $orderInfo['food_list'][] = $foodInfo;
                }
            }
        }
        unset($foodList);

        return $orderList;
    }

    /**
     * 菜品估清
     * @param $storeId
     * @param $foodList
     * @throws \Exception
     */
    private function _checkEstimates($storeId, $foodList)
    {
        $estimatesList   = $this->getRedis()->get(config('cache_keys.choice_estimates') . ":{$storeId}");
        $shoppingService = model('common/shopping', 'service');
        if (!empty($estimatesList) and !empty($foodList)) {
            $soldOutStr = '';
            foreach ($foodList as $info) {
                if ($info['food_weigh'] == 1) {
                    throw new \Exception(
                        $info['food_name'] . "需称重计价, 请联系服务员下单"
                    );
                }
                if (array_key_exists($info['food_code'], $estimatesList)) {
                    $dishNumber = $estimatesList[$info['food_code']];

                    if ($dishNumber < 1) {
                        $soldOutStr .= $info['food_name'] . ',';
                        $shoppingService->delFastFood($storeId, $this->openid, [
                            'food_code'   => $info['food_code'],
                            'food_number' => $info['food_number'],
                            'combo_key'   => isset($info['combo_key']) ? $info['combo_key'] : '',
                        ], $info['is_multiple_combo']);
                        continue;
                    }

                    if ($dishNumber < $info['food_number']) {
                        throw new \Exception(
                            $info['food_name'] . '数量剩余' . $dishNumber . $info['food_unit']
                        );
                    }
                }

                //判断自选套餐中的菜品是否估清
                if ($info['is_multiple_combo'] == 1 && !empty($info['combo_detail']) && !array_key_exists($info['food_code'], $estimatesList)) {
                    foreach ($info['combo_detail'] as $comboDishCode) {
                        if (array_key_exists($comboDishCode['dish_code'], $estimatesList)) {
                            $comboDishNumber = $estimatesList[$comboDishCode['dish_code']];

                            if ($comboDishNumber < 1) {
                                $soldOutStr .= $info['food_name'] . ',';
                                $shoppingService->delFastFood($storeId, $this->openid, [
                                    'food_code'   => $info['food_code'],
                                    'food_number' => $info['food_number'],
                                    'combo_key'   => isset($info['combo_key']) ? $info['combo_key'] : '',
                                ], $info['is_multiple_combo']);
                                break;
                            }
                        }
                    }
                }
                //判断普通套餐中的菜品是否估清
                if ($info['is_multiple_combo'] == 0 && $info['is_combo'] == 1 && !empty($info['food_modifiers']) && !array_key_exists($info['food_code'], $estimatesList)) {
                    $comboDetail = json_decode($info['food_modifiers'], true);
                    if (is_array($comboDetail)) {
                        foreach ($comboDetail as $value) {
                            $comboDish = current($value);
                            if (array_key_exists($comboDish['dish_code'], $estimatesList)) {
                                $comboDishNumber = $estimatesList[$comboDish['dish_code']];

                                if ($comboDishNumber < 1) {
                                    $soldOutStr .= $info['food_name'] . ',';
                                    $shoppingService->delFastFood($storeId, $this->openid, [
                                        'food_code'   => $info['food_code'],
                                        'food_number' => $info['food_number'],
                                        'combo_key'   => isset($info['combo_key']) ? $info['combo_key'] : '',
                                    ], $info['is_multiple_combo']);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            if (!empty($soldOutStr)) {
                throw new \Exception(
                    $soldOutStr, "-8"
                );
            }
        }
    }
}
