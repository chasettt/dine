<?php
namespace app\common\service;

use sdk\Choice;
use sdk\Data;
use sdk\Redis;

class Order
{
    const NODIET = '没有忌口';
    /**
     * 创建订单
     * @param array $orderData
     * @return mixed
     * @throws \Exception
     */
    public function createOrder($storeCode = '', $orderData = [])
    {
        if (!$storeCode or empty($orderData) or empty($orderData['food_list'])) {
            throw new \Exception('下单失败');
        }

        // 查询台位订单
        $orderModel = model('common/order');
        $orderInfo  = $orderModel->getOrderInfo([
            'store_code'  => $orderData['store_code'],
            'table_id'    => $orderData['table_id'],
            'order_state' => [
                'neq', 1,
            ],
            'order_type'  => ['eq', 1],
        ], 'order_no');

        $orderRemark = isset($orderData['remark']) ? $orderData['remark'] : '';

        if (!empty($orderInfo)) {
            $orderSn              = $orderInfo['order_no'];
            $data['order_remark'] = $orderRemark;
            $data['pay_type']     = $orderData['pay_type'];
            $orderModel->upOrderInfo(['order_no' => $orderSn], $data);
        } else {
            // 写入订单
            $orderSn = makeOrderSn();

            $orderModel->addOrder([
                'order_no'          => $orderSn,
                'store_code'        => $orderData['store_code'],
                'table_id'          => $orderData['table_id'],
                'order_people'      => $orderData['people'],
                'order_man'         => $orderData['man'],
                'order_child'       => $orderData['child'],
                'order_source'      => $orderData['source'],
                'order_create_time' => time(),
                'create_users'      => $orderData['openid'],
                'order_remark'      => $orderRemark,
                'brand_id'          => session('users.brand_id'),
            ]);
        }

        foreach ($orderData['food_list'] as &$item) {

            $item = [
                'order_no'          => $orderSn,
                'users_openid'      => $item['users_openid'],
                'food_code'         => $item['food_code'],
                'food_name'         => $item['food_name'],
                'food_number'       => $item['food_number'],
                'food_unit'         => $item['food_unit'],
                'food_weigh'        => $item['food_weigh'],
                'food_state'        => $item['food_state'],
                'food_price'        => $item['food_price'],
                'food_modifiers'    => $this->getComboModify($item),
                // 如果订单备注是 没有忌口 并且菜品没有备注 给菜品增加备注--没有忌口
                'food_remark'       => (empty($item['food_remark']) && $orderRemark == self::NODIET) ? self::NODIET : $item['food_remark'],
                'is_combo'          => $item['is_combo'],
                'is_packing'        => $item['is_packing'],
                'is_multiple_combo' => $item['is_multiple_combo'],
            ];
        }

        model('common/orderFood')->addFoodList($orderData['food_list']);
        return ['order_sn' => $orderSn, 'food_data' => $orderData];
    }

    /**
     * 创建外带订单
     * @param $orderInfo
     * @return string
     * @throws \Exception
     */
    public function createTakeoutOrder($orderInfo)
    {

        \think\Log::notice('= 堂食外带-订单信息 =');
        \think\Log::notice(print_r($orderInfo, true));

        // 订单价格
        $memberPrice = 0; // 会员价
        $publicPrice = 0; // 普通价
        $foodList    = [];
        $orderSn     = makeOrderSn();

        if (!empty($orderInfo['order_no'])) {
            $orderSn = $orderInfo['order_no'];
        }

        $orderRemark = isset($orderInfo['order_remark']) ? $orderInfo['order_remark'] : '';

        foreach ($orderInfo['food_list'] as $foodInfo) {
            $memberPrice += $foodInfo['food_member_price'] * $foodInfo['food_number'];
            $publicPrice += $foodInfo['food_price'] * $foodInfo['food_number'];
            $foodList[$foodInfo['food_code']] = [
                'order_no'          => $orderSn,
                'users_openid'      => $orderInfo['openid'],
                'food_code'         => $foodInfo['food_code'],
                'food_name'         => $foodInfo['food_name'],
                'food_price'        => $foodInfo['food_price'],
                'food_member_price' => $foodInfo['food_member_price'],
                'food_number'       => $foodInfo['food_number'],
                'food_weigh'        => $foodInfo['food_weigh'],
                //如果订单备注是 没有忌口 并且菜品没有备注 给菜品增加备注--没有忌口
                'food_remark'       => (empty($foodInfo['food_remark']) && $orderRemark == self::NODIET) ? self::NODIET : $foodInfo['food_remark'],
                'food_unit'         => $foodInfo['food_unit'],
                'food_state'        => 0,
                'is_combo'          => $foodInfo['is_combo'],
                'is_packing'        => $foodInfo['is_packing'],
                'food_modifiers'    => $this->getComboModify($foodInfo),
                'is_multiple_combo' => $foodInfo['is_multiple_combo'],
            ];
        }

        //创建订单
        $orderData = [
            'order_no'            => $orderSn,
            'order_people'        => $orderInfo['order_people'],
            'store_code'          => $orderInfo['store_code'],
            'order_source'        => $orderInfo['source'],
            'order_state'         => 0,
            'order_type'          => $orderInfo['order_type'],
            'order_create_time'   => time(),
            'create_users'        => $orderInfo['openid'],
            'order_amount'        => $publicPrice,
            'order_amount_member' => $memberPrice,
            'order_expire_time'   => $orderInfo['expire_time'],
            'order_remark'        => $orderRemark,
            'takeaway_id'         => $orderInfo['takeaway_id'],
            'contact_name'        => isset($orderInfo['contact_name']) ? $orderInfo['contact_name'] : '',
            'contact_tel'         => isset($orderInfo['contact_tel']) ? $orderInfo['contact_tel'] : '',
            'is_take_dish'        => isset($orderInfo['is_take_dish']) ? $orderInfo['is_take_dish'] : '',
            'brand_id'            => session('users.brand_id'),
        ];

        // 重置订单
        if (!empty($orderInfo['order_no'])) {
            \think\Log::notice('= 堂食外带-重置订单 =');

            unset($orderData['order_no']);

            // 更新订单
            $orderUpState = model('common/order')->upOrderInfo([
                'order_no' => $orderSn,
            ], $orderData);

            if (!$orderUpState) {
                \think\Log::notice("= orderUpState-更新订单失败-{$orderInfo['order_no']} =");
                throw new \Exception('更新订单失败');
            }

            // 更新菜品
            $delFoodListState = model('common/orderFood')->delFoodList([
                'order_no' => $orderSn,
            ]);

            if (!$delFoodListState) {
                \think\Log::notice("= delFoodListState-更新订单失败-{$orderInfo['order_no']} =");
                throw new \Exception('更新订单失败');
            }

            $foodState = model('common/orderFood')->addFoodList($foodList);
            if (!$foodState) {
                \think\Log::notice("= foodState-更新订单失败- =");
                \think\Log::notice(print_r($foodList, true));
                throw new \Exception('更新订单失败');
            }

            $orderSn = $orderInfo['order_no'];
        } else {
            $orderState = model('common/order')->addOrder($orderData);
            if (!$orderState) {
                throw new \Exception('创建订单失败');
            }

            $foodState = model('common/orderFood')->addFoodList($foodList);
            if (!$foodState) {
                throw new \Exception('创建订单失败');
            }
        }

        return $orderSn;
    }

    /**
     * 创建堂食订单
     * @param $orderInfo
     * @return string
     * @throws \Exception
     */
    public function createEatOrder($orderInfo)
    {
        // 订单价格
        $memberPrice = 0; // 会员价
        $publicPrice = 0; // 普通价
        $foodList    = [];
        $orderSn     = makeOrderSn();

        if (!empty($orderInfo['order_no'])) {
            $orderSn = $orderInfo['order_no'];
        }

        foreach ($orderInfo['food_list'] as $list) {
            foreach ($list['shopping'] as $foodInfo) {
                if (empty($foodInfo['food_discount'])) {
                    $foodInfo['food_discount'] = 0;
                }
                if (empty($foodInfo['food_discount_member'])) {
                    $foodInfo['food_discount_member'] = 0;
                }
                if (empty($foodInfo['discount_id'])) {
                    $foodInfo['discount_id'] = 0;
                }
                $memberPrice += ($foodInfo['food_member_price'] - $foodInfo['food_discount_member']) * $foodInfo['food_number'];
                $publicPrice += ($foodInfo['food_price'] - $foodInfo['food_discount']) * $foodInfo['food_number'];
                $foodList[$foodInfo['food_code']] = [
                    'order_no'             => $orderSn,
                    'users_openid'         => $orderInfo['openid'],
                    'food_code'            => $foodInfo['food_code'],
                    'food_name'            => $foodInfo['food_name'],
                    'food_price'           => $foodInfo['food_price'],
                    'food_member_price'    => $foodInfo['food_member_price'],
                    'food_discount'        => $foodInfo['food_discount'],
                    'food_discount_member' => $foodInfo['food_discount_member'],
                    'discount_id'          => $foodInfo['discount_id'],
                    'food_number'          => $foodInfo['food_number'],
                    'food_weigh'           => $foodInfo['food_weigh'],
                    'food_remark'          => $foodInfo['food_remark'],
                    'food_unit'            => $foodInfo['food_unit'],
                    'food_state'           => 0,
                    'is_combo'             => $foodInfo['is_combo'],
                    'is_packing'           => $foodInfo['is_packing'],
                    'food_modifiers'       => $foodInfo['food_modifiers'],
                ];
            }
        }

        //创建订单
        $orderData = [
            'order_no'              => $orderSn,
            'order_people'          => $orderInfo['order_people'],
            'order_man'             => $orderInfo['order_man'],
            'order_child'           => $orderInfo['order_child'],
            'store_code'            => $orderInfo['store_code'],
            'table_id'              => $orderInfo['table_id'],
            'order_source'          => $orderInfo['source'],
            'order_state'           => 0,
            'order_type'            => $orderInfo['order_type'],
            'order_create_time'     => time(),
            'create_users'          => $orderInfo['openid'],
            'order_amount'          => $publicPrice,
            'order_amount_member'   => $memberPrice,
            'order_expire_time'     => $orderInfo['expire_time'],
            'order_remark'          => isset($orderInfo['order_remark']) ? $orderInfo['order_remark'] : '',
            'order_discount'        => $orderInfo['order_discount'],
            'order_discount_member' => $orderInfo['order_discount_member'],
            'is_take_dish'          => empty($orderInfo['is_take_dish']) ? 0 : $orderInfo['is_take_dish'],
            'brand_id'              => session('users.brand_id'),
        ];

        // 重置订单
        if (!empty($orderInfo['order_no'])) {

            unset($orderData['order_no']);
            // 更新订单
            $orderUpState = model('common/order')->upOrderInfo([
                'order_no' => $orderSn,
            ], $orderData);

            if (!$orderUpState) {
                throw new \Exception('更新订单失败');
            }

            // 更新菜品
            $delFoodListState = model('common/orderFood')->delFoodList([
                'order_no' => $orderSn,
            ]);

            if (!$delFoodListState) {
                throw new \Exception('更新订单失败');
            }

            $foodState = model('common/orderFood')->addFoodList($foodList);
            if (!$foodState) {
                throw new \Exception('更新订单失败');
            }

            $orderSn = $orderInfo['order_no'];
        } else {
            $orderState = model('common/order')->addOrder($orderData);
            if (!$orderState) {
                throw new \Exception('创建订单失败');
            }

            $foodState = model('common/orderFood')->addFoodList($foodList);
            if (!$foodState) {
                throw new \Exception('创建订单失败');
            }
        }

        return $orderSn;
    }

    /**
     * 创建堂食订单
     * @param $orderInfo
     * @return string
     * @throws \Exception
     */
    public function createFastfoodOrder($orderInfo, $brandId = 0)
    {
        // 订单价格
        $memberPrice = 0; // 会员价
        $publicPrice = 0; // 普通价
        $foodList    = [];
        $orderSn     = makeOrderSn();

        if (!empty($orderInfo['order_no'])) {
            $orderSn = $orderInfo['order_no'];
        }

        foreach ($orderInfo['food_list'] as $list) {
            foreach ($list['shopping'] as $foodInfo) {
                \think\Log::notice(print_r($foodInfo, true));
                if (empty($foodInfo['food_discount'])) {
                    $foodInfo['food_discount'] = 0;
                }
                if (empty($foodInfo['food_discount_member'])) {
                    $foodInfo['food_discount_member'] = 0;
                }
                if (empty($foodInfo['discount_id'])) {
                    $foodInfo['discount_id'] = 0;
                }

                $memberPrice += ($foodInfo['food_member_price'] - $foodInfo['food_discount_member']) * $foodInfo['food_number'];
                $publicPrice += ($foodInfo['food_price'] - $foodInfo['food_discount']) * $foodInfo['food_number'];

                $foodList[] = [
                    'order_no'             => $orderSn,
                    'user_id'              => empty($orderInfo['user_id']) ? 0 : $orderInfo['user_id'],
                    'users_openid'         => empty($orderInfo['openid']) ? '' : $orderInfo['openid'],
                    'food_code'            => $foodInfo['food_code'],
                    'food_name'            => $foodInfo['food_name'],
                    'food_price'           => $foodInfo['food_price'],
                    'food_member_price'    => $foodInfo['food_member_price'],
                    'food_discount'        => $foodInfo['food_discount'],
                    'food_discount_member' => $foodInfo['food_discount_member'],
                    'discount_id'          => $foodInfo['discount_id'],
                    'food_number'          => $foodInfo['food_number'],
                    'food_weigh'           => $foodInfo['food_weigh'],
                    'food_remark'          => $foodInfo['food_remark'],
                    'food_unit'            => $foodInfo['food_unit'],
                    'food_state'           => 0,
                    'is_combo'             => $foodInfo['is_combo'],
                    'is_packing'           => $foodInfo['is_packing'],
                    'food_modifiers'       => $this->getComboModify($foodInfo),
                    'is_multiple_combo'    => $foodInfo['is_multiple_combo'],
                ];
            }
        }

        //创建订单
        $orderData = [
            'order_no'              => $orderSn,
            'order_people'          => $orderInfo['order_people'],
            'order_man'             => $orderInfo['order_man'],
            'order_child'           => $orderInfo['order_child'],
            'store_code'            => $orderInfo['store_code'],
            'table_id'              => $orderInfo['table_id'],
            'order_source'          => $orderInfo['source'],
            'order_state'           => 0,
            'order_type'            => $orderInfo['order_type'],
            'order_create_time'     => time(),
            'user_id'               => empty($orderInfo['user_id']) ? 0 : $orderInfo['user_id'],
            'create_users'          => empty($orderInfo['openid']) ? '' : $orderInfo['openid'],
            'order_amount'          => $publicPrice,
            'order_amount_member'   => $memberPrice,
            'order_expire_time'     => $orderInfo['expire_time'],
            'order_remark'          => isset($orderInfo['order_remark']) ? $orderInfo['order_remark'] : '',
            'order_discount'        => $orderInfo['order_discount'],
            'order_discount_member' => $orderInfo['order_discount_member'],
            'is_package'            => $orderInfo['is_package'],
            'is_take_dish'          => $orderInfo['is_take_dish'],
            'brand_id'              => $brandId ? $brandId : session('users.brand_id'),
        ];

        // 重置订单
        if (!empty($orderInfo['order_no'])) {

            unset($orderData['order_no']);
            // 更新订单
            $orderUpState = model('common/order')->upOrderInfo([
                'order_no' => $orderSn,
            ], $orderData);

            if (!$orderUpState) {
                throw new \Exception('更新订单失败');
            }

            // 更新菜品
            $delFoodListState = model('common/orderFood')->delFoodList([
                'order_no' => $orderSn,
            ]);

            if (!$delFoodListState) {
                throw new \Exception('更新订单失败');
            }

            $foodState = model('common/orderFood')->addFoodList($foodList);
            if (!$foodState) {
                throw new \Exception('更新订单失败');
            }

            $orderSn = $orderInfo['order_no'];
        } else {
            $orderState = model('common/order')->addOrder($orderData);
            if (!$orderState) {
                throw new \Exception('创建订单失败');
            }

            $foodState = model('common/orderFood')->addFoodList($foodList);
            if (!$foodState) {
                throw new \Exception('创建订单失败');
            }
        }

        return $orderSn;
    }

    /**
     * 发送菜品
     * @param array $data
     * @throws \Exception
     */
    public function sendDish($data = [], $orderNo = '')
    {
        if (empty($data)) {
            throw new \Exception('下单失败');
        }
        $memberInfo = [
            'openid'          => '',
            'name'            => '',
            'phone'           => '',
            'grade_name'      => '',
            'cno'             => '',
            'is_vip'          => '',
            'user_grade'      => '',
            'vip_expire_time' => '',
        ];

        $userDb   = new Data(config('domain.user_db_url'));
        $userInfo = $userDb->getMemberInfo($data['openid']);
        if (!empty($userInfo)) {
            $memberInfo = [
                'openid'          => $userInfo['openid'],
                'name'            => $userInfo['name'],
                'phone'           => $userInfo['phone'],
                'grade_name'      => $userInfo['grade_name'],
                'cno'             => $userInfo['cno'],
                'is_vip'          => $userInfo['is_vip'],
                'user_grade'      => $userInfo['user_grade'],
                'vip_expire_time' => $userInfo['vip_expire_time'],
                'zx_coupon_info'  => empty($userInfo['zx_coupon_info']) ? null : $userInfo['zx_coupon_info'],
            ];
        }

        $dishList = [];

        foreach ($data['food_list'] as $key => $item) {
            $dishList[$key]['dish_code']  = $item['food_code'];
            $dishList[$key]['num']        = $item['food_number'];
            $dishList[$key]['unit']       = $item['food_unit'];
            $dishList[$key]['is_weigh']   = $item['food_weigh'];
            $dishList[$key]['is_combo']   = $item['is_combo'];
            $dishList[$key]['price']      = $item['food_price'];
            $dishList[$key]['additional'] = $this->filterRemark($item['food_remark']);
            if ($item['is_combo'] == 1) {
                $dishList[$key]['modifiers'] = json_decode($item['food_modifiers'], true);
            }
        }

        \think\Log::record('菜品数据：' . print_r($dishList, true), 'notice');

        $data['people'] = ['man' => $data['man'], 'child' => $data['child']];
        $choiceApi      = new Choice(config('domain.choice_url'));

        $result = $choiceApi->sendDishes(
            $data['store_code'],
            $data['table_id'],
            $data['people'],
            $dishList,
            json_encode($memberInfo),
            $this->filterRemark($data['remark']),
            $orderNo
        );

        if (false === $result) {
            throw new \Exception($choiceApi->errMsg, $choiceApi->errCode);
        }

        return $result['order_id'];
    }

    public function filterRemark($remark)
    {
        if (!empty($remark)) {
            // 下单到辰森时，判断是否有 没有忌口四个字 如果有，去掉
            $remarkList = explode(',', $remark);
            foreach ($remarkList as $remarkKey => $remarkValue) {
                if ($remarkValue == self::NODIET) {
                    unset($remarkList[$remarkKey]);
                }
            }

            if (!empty($remarkList)) {
                return implode(',', $remarkList);
            }
        }

        return '';
    }

    /**
     * 获取订单详情
     * @param $condition
     */
    public function getOrderInfo($condition)
    {
        $choiceApi = new Choice(config('domain.choice_url'));
        $orderInfo = $choiceApi->getOrder(
            $condition['store_code'],
            $condition['table_id']
        );

        return $orderInfo['order_id'];
    }

    public function updateOrderInfo($orderSn, $choiceId)
    {
        // 检测订单是否更新

        $orderInfo = model('common/order')->getOrderInfo([
            'order_no' => $orderSn,
        ], 'order_id,choice_id');

        if (!empty($orderInfo) and is_null($orderInfo['choice_id'])) {
            // 更新订单
            model('common/order')->upOrderInfo([
                'order_id' => $orderInfo['order_id'],
            ], ['choice_id' => $choiceId]);
        }
    }

    public function getOrderList($orderState = 10)
    {
        $orderList = model('common/order')->getOrderList([
            'order_state' => $orderState,
        ], 'order_id, store_code, table_id, order_state')->toArray();

        return $orderList;
    }

    /**
     * 统计订单金额
     * @param $storeCode
     * @param $tableId
     * @return array|bool
     */
    public function getOrderTotalMoney($storeCode, $tableId)
    {

        $orderInfo = model('common/order')->getOrderInfo([
            'store_code'  => $storeCode,
            'table_id'    => $tableId,
            'order_state' => [
                'neq', 1,
            ],
        ], 'order_no,create_users');

        if (empty($orderInfo)) {
            return false;
        }

        $total = model('common/orderFood')->foodTotalPrice([
            'order_no' => $orderInfo['order_no'],
        ], 'food_price');

        if (empty($total)) {
            return false;
        }

        return [
            'order_no' => $orderInfo['order_no'],
            'total'    => $total,
            'openid'   => $orderInfo['create_users'],
        ];
    }

    /**
     * 自选套餐重组套餐modify格式为二维
     * @param $foodInfo
     * @return array|string
     */
    private function getComboModify($foodInfo)
    {
        \think\Log::notice(print_r($foodInfo, true));
        $result = [];
        if (isset($foodInfo['is_multiple_combo']) && $foodInfo['is_multiple_combo'] == 1) {
            //多选套餐，从原有三维数组food_modifiers中筛选出所选food_code的信息
            if (!empty($foodInfo['food_modifiers']) && !empty($foodInfo['combo_detail'])) {
                $foodModifiers = json_decode($foodInfo['food_modifiers'], true);
                \think\Log::notice("--------多选套餐处理-------");
                \think\Log::notice(print_r($foodModifiers, true));

                $newComboArr = [];
                foreach ($foodModifiers as $tagK => $detail) {
                    foreach ($detail as &$detailItem) {
                        $tag = $tagK;
                        $newComboArr[$tag][$detailItem['dish_code']] = $detailItem;
                    }
                    unset($detailItem);
                }
                $tagArr = array_keys($newComboArr);
                foreach ($foodInfo['combo_detail'] as $food) {
                    if (in_array($food['tag'], $tagArr)) {
                        $comboFoodInfo = $newComboArr[$food['tag']][$food['dish_code']];
                        if ($foodInfo['diy'] == 1) {
                            $comboFoodInfo['num'] = $food['num'];
                        }
                        $result[] = $comboFoodInfo;
                    }
                }
            }

            $result = json_encode($result);
        } elseif ($foodInfo['is_combo'] == 1) {
            $foodModifiers = json_decode($foodInfo['food_modifiers'], true);
            foreach ($foodModifiers as $detail) {
                $result[] = is_array(current($detail)) ? current($detail) : $detail;
            }
            $result = json_encode($result);
        }

        return $result;
    }
}
