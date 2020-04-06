<?php

namespace app\api\controller;

/**
 * 小吃小喝推荐
 */
class Recommend extends Base
{

    /**
     * 获取推荐菜品code
     * @return array 推荐菜品字串
     */
    public function get()
    {
        $storeCode = input('post.store_code', 0, 'int');
        $tableNo   = input('post.table_no', 0, 'int');
        $orderNo   = input('post.order_no', '', 'string');

        if (!$storeCode || !$tableNo) {
            return $this->returnMsg(0, '参数传入错误');
        }

        // 这里需要加个门店开关 - 下单是否开启推荐
        $store_info = $this->getRedis()->get(config('cache_keys.store_info') . ":{$storeCode}");

        // 菜品推荐地方 1=>下单前, 2=>下单后
        $type = 1;
        if (empty($orderNo) && empty($store_info['enabled_recommend'])) {
            return $this->returnMsg(0, '门店没有开启菜品推荐功能');
        } elseif (!empty($orderNo)) {
            // 有订单号代表下单成功之后的推荐
            $type = 2;
            if ($store_info['enabled_link'] == 0 && $store_info['enabled_recommend_later'] == 0) {
                return $this->returnMsg(0, '没有可展示的数据');
            } elseif ($store_info['enabled_link'] == 1 && $store_info['enabled_recommend_later'] == 0) {
                return $this->returnMsg(200, 'success', [
                    'enabled_link'            => 1,
                    'enabled_recommend_later' => 0,
                ]);
            }
        }

        $res  = model('common/Recommend', 'service')->getRecommendCode($storeCode, $tableNo, ['type' => $type, 'order_no' => $orderNo]);
        $data = json_decode($res, true);
        if ($data['code'] != 200) {
            return $this->returnMsg(0, $data['msg']);
        }
        $recommendList = $data['data'] ? $data['data'] : [];
        // 检查该门店有没有这些菜品
        $storeFoodList = $this->getRedis()->get(config('cache_keys.store_menu_dish_code') . ":{$storeCode}");
        // 门店菜品和推荐菜品取交集
        $recommendList = array_intersect($recommendList, $storeFoodList ? $storeFoodList : []);
        // 估清检查
        $recommendList = model('common/food', 'service')->estimates($storeCode, $recommendList, false);
        if (empty($recommendList)) {
            switch ($type) {
                case 1:
                    return $this->returnMsg(0, '没有推荐的菜品');
                case 2:
                    return $this->returnMsg(200, 'success', [
                        'enabled_link' => $store_info['enabled_link'],
                        'food_list'    => [],
                    ]);
            }
        }

        if ($type == 1) {
            // 推荐菜品,显示1-2个
            $recommendList = array_slice($recommendList, 0, 2);
        }

        // 台位信息
        if (!empty($tableNo)) {
            $tableInfo = $this->getRedis()->get(config('cache_keys.table_info') . ":{$storeCode}:{$tableNo}");
        }

        // 获取菜品详情
        $recommendInfoList = [];
        foreach ($recommendList as $food) {
            $foodInfo = $this->getRedis()->get(config('cache_keys.store_menu_dish') . ":{$storeCode}:{$food}");
            if ($foodInfo) {
                unset(
                    $foodInfo['food_modifiers'],
                    $foodInfo['is_essential'],
                    $foodInfo['is_specialty'],
                    $foodInfo['is_newest'],
                    $foodInfo['is_combo'],
                    $foodInfo['is_packing'],
                    $foodInfo['food_attrs'],
                    $foodInfo['food_description']
                );

                if (!empty($tableInfo) and $tableInfo['type_id'] == config('room.type')) {
                    $foodInfo['food_price']        = $foodInfo['food_room_price'];
                    $foodInfo['food_member_price'] = $foodInfo['food_room_member_price'];
                    unset($foodInfo['food_room_price']);
                    unset($foodInfo['food_room_member_price']);
                }

                $recommendInfoList[$food] = $foodInfo;
            }
        }
        switch ($type) {
            case 1:
                $recommendInfoList = model('common/discount', 'service')->process($storeCode, $recommendInfoList, $this->openid, 1);

                return $this->returnMsg(200, 'success', $recommendInfoList);
            case 2:
                return $this->returnMsg(200, 'success', [
                    'enabled_link'            => $store_info['enabled_link'],
                    'enabled_recommend_later' => 1,
                    'member_rules'            => $store_info['member_rules'],
                    'food_list'               => $recommendInfoList,
                ]);
        }
    }
}