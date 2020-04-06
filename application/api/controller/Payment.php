<?php
namespace app\api\controller;

use think\Db;

/**
 * 堂食订单
 * Class Takeout
 * @package app\api\controller
 */
class Payment extends Base
{
    protected $auth = true;

    public function get()
    {
        $orderNo = input('post.order_sn', 0, 'int');

        if (! $orderNo) {
            return $this->returnMsg(0, '参数错误');
        }

        $field     = 'store_code,table_id,table_name,order_no,choice_id,order_state,pay_state,order_amount,order_amount_member,balance_amount,credit_amount,coupon_amount,pay_amount,pay_member,order_create_time,member_rules,create_users,pay_time,invoice_url,order_type';
        $orderInfo = model('common/order')->getOrderInfo([
            'order_no' => $orderNo,
        ], $field);

        if (empty($orderInfo)) {
            return $this->returnMsg(0, '未查询到订单信息');
        }
        $foodList = model('common/orderFood')->getFoodList([
            'order_no' => $orderInfo['order_no'],
        ], 'food_code,food_name,food_price,food_member_price,food_number,food_unit,food_weigh,food_state,food_discount,food_discount_member');
        // 这里特价展示的时候是有问题的,因为订单的价格已经是打折的价格了,又去处理,不过只需要到这里确定折扣价不为0
        $foodList                  = model('common/discount', 'service')->process($orderInfo['store_code'], $foodList, $orderInfo['create_users'], 1);
        $orderInfo['total_number'] = count($foodList);
        $orderInfo['food_list']    = $foodList;

        if ($orderInfo['order_state'] != 1) {
            $orderInfo['choice_id']  = '';
            $orderInfo['table_id']   = '';
            $orderInfo['table_name'] = '';
        }

        return $this->returnMsg(200, 'success', $orderInfo);
    }

    public function commit()
    {

        \think\Log::notice('===================== 提交订单 ===================');
        $storeCode   = input('post.store_id', 0, 'int');
        $tableNo     = input('post.table_id', '');
        $people      = input('post.people', 0, 'int');
        $man         = input('post.man', 0, 'int');
        $child       = input('post.child', 0, 'int');
        $orderRemark = input('post.remark', '', 'string');
        \think\Log::notice(print_r(input('post.'), true));

        if (! $storeCode or ! $tableNo or ! $people) {
            return $this->returnMsg(0, '参数错误');
        }

        Db::startTrans();
        try {
            \think\Log::notice('===================== 门店信息 ===================');
            $storeInfo = model('common/store')->getStore([
                'store_code'  => $storeCode,
                'store_state' => 1,
            ], 'enabled_packing_fee, enabled_fee, fee_code, payment_version');

            if (empty($storeInfo)) {
                throw new \Exception('门店不存在');
            }

            \think\Log::notice(print_r($storeInfo, true));

            // 购物车
            \think\Log::notice('===================== 购物车信息 ===================');
            $shoppingService = model('common/shopping', 'service');
            $userCartInfo    = $shoppingService->getTableUserCart($storeCode, $tableNo);

            if (empty($userCartInfo)) {
                throw new \Exception('购物车没有商品呦');
            }
            \think\Log::notice(print_r($userCartInfo, true));

            // 估清
            \think\Log::notice('===================== 估清信息 ===================');
            $this->_checkEstimates($storeCode, $userCartInfo);

            // 折扣菜品处理
            \think\Log::notice('===================== 折扣菜信息 ===================');
            $cartInfo     = model('common/discount', 'service')->process($storeCode, $userCartInfo, $this->openid, 2);
            $userCartInfo = $cartInfo['food_list'];
            \think\Log::notice(print_r($userCartInfo, true));

            // 茶位费处理
            if ($storeInfo['enabled_fee'] == 1 && ! empty($storeInfo['fee_code'])) {
                \think\Log::notice('===================== 茶位费信息 ===================');
                // 门店开启了茶位费 && 茶位费菜品存在
                $userCartInfo = model('common/fee', 'service')->addFee($storeCode, $tableNo, $userCartInfo, $storeInfo['fee_code'], ['people' => $people, 'man' => $man, 'child' => $child]);
                \think\Log::notice(print_r($userCartInfo, true));
            }

            \think\Log::notice('===================== 订单信息 ===================');
            $orderData = [
                'store_code'            => $storeCode,
                'table_id'              => $tableNo,
                'openid'                => $this->openid,
                'source'                => 'online',
                'order_state'           => 0,
                'order_type'            => 3,
                'order_people'          => $people,
                'order_man'             => $man,
                'order_child'           => $child,
                'expire_time'           => time() + config('payment.expire_time'),
                'food_list'             => $userCartInfo,
                'order_remark'          => $orderRemark,
                'order_discount'        => $cartInfo['order_discount'],
                'order_discount_member' => $cartInfo['order_discount_member'],
            ];
            \think\Log::notice(print_r($orderData, true));

            $orderInfo = model('common/order')->getOrderInfo([
                'create_users'      => $this->openid,
                'pay_state'         => 0,
                'order_type'        => 3,
                'order_create_time' => ['>', strtotime('-15 minute')],
            ], 'order_no');

            if (! empty($orderInfo)) {
                \think\Log::notice(print_r($orderInfo, true));
                // 创建订单
                $orderData['order_no'] = $orderInfo['order_no'];
            } else {
                $orderData['order_no'] = '';
            }

            $orderSn = model('common/order', 'service')->createEatOrder($orderData);
            \think\Log::notice(print_r($orderSn, true));
            Db::commit();

            return $this->returnMsg(200, 'success', ['order_sn' => $orderSn]);
        } catch (\Exception $e) {
            \think\Log::notice(print_r(['code' => $e->getCode(), 'msg' => $e->getMessage()], true));
            // \think\Log::notice('微生活支付：'.print_r(['code' => $e->getCode(), 'msg' => $e->getMessage()], true));
            $this->failed($e->getCode(), $e->getMessage());
            Db::rollback();

            return $this->returnMsg(0, $e->getMessage());
        }
    }


    /**
     * 菜品估清
     * @param $storeId
     * @param $foodList
     * @throws \Exception
     */
    private function _checkEstimates($storeId, $foodList)
    {
        $estimatesList = $this->getRedis()->get(config('cache_keys.choice_estimates') . ":{$storeId}");
        \think\Log::notice(print_r($estimatesList, true));
        if (! empty($estimatesList) and ! empty($foodList)) {
            foreach ($foodList as $info) {
                foreach ($info['shopping'] as $shop) {
                    if (array_key_exists($shop['food_code'], $estimatesList)) {
                        $dishNumber = $estimatesList[$shop['food_code']];

                        if ($dishNumber < 1) {
                            throw new \Exception(
                                $shop['food_name'] . '已估清'
                            );
                        }

                        if ($dishNumber < $shop['food_number']) {
                            throw new \Exception(
                                $shop['food_name'] . '数量剩余' . $dishNumber . $shop['food_unit']
                            );
                        }
                    }
                }
            }
        }
    }
}
