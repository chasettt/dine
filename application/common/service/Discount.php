<?php

namespace app\common\service;

use sdk\Redis;
use sdk\Wapi;
use Think\Log;

/**
 * 菜品折扣
 */
class Discount
{
    /**
     * 折扣菜品价格过滤
     * @param string 门店code
     * @param array (购物车)菜品列表
     * @param string 用户openid
     * @param int 1=>价格显示, 2=>下单, 3=>下单确认页
     * @return array 处理后的信息
     */
    public function process($storeCode, $foodList, $openid, $type, $unionid = '', $brandid = '')
    {
        // 门店是否开启优免活动、会员规则
        $redis     = new Redis();
        $storeInfo = $redis->get(config('cache_keys.store_info') . ":{$storeCode}");

        if ($storeInfo['eat_payment_rules'] == 'later' || empty($storeInfo['enabled_discount']) || empty($storeInfo['discount_code'])) {
            // 门店后结账 || 门店没有开启优免活动 || 菜品优免code为0
            if ($type == 1 || $type == 3) {
                return $foodList;
            } else if ($type == 2) {
                return ['food_list' => $foodList, 'order_discount' => 0, 'order_discount_member' => 0];
            }
        }
        // 1.拿出门店下的折扣菜品
        $discountFoodList = $redis->get(config('cache_keys.store_food_activity') . ":{$storeCode}");
        if (empty($discountFoodList)) {
            // 没有可用菜品优免活动
            if ($type == 1 || $type == 3) {
                return $foodList;
            } else if ($type == 2) {
                return ['food_list' => $foodList, 'order_discount' => 0, 'order_discount_member' => 0];
            }
        }
        foreach ($discountFoodList as $k => $v) {
            if (time() > $v['end_time'] || time() < $v['start_time'] || !in_array(date('w', time()), $v['week'])) {
                // 活动已经结束 || 活动尚未开始
                // state为0的不考虑, 因为Cache里根本不存state为0的
                unset($discountFoodList[$k]);
            }
        }
        if (empty($discountFoodList)) {
            // 没有可用菜品优免活动
            if ($type == 1 || $type == 3) {
                return $foodList;
            } else if ($type == 2) {
                return ['food_list' => $foodList, 'order_discount' => 0, 'order_discount_member' => 0];
            }
        }
        // 取出所有折扣菜品code集合
        $discountFoodKeyList = array_keys($discountFoodList);
        // 是否VIP
        $brandid = $brandid ? $brandid : session('users.brand_id');
        $wapiApi = new Wapi(config('domain.welife_url'), $brandid);
        if ($unionid) {
            // 小程序是用 unionid 开的卡, 所以要用 unionid 查询
            $weLifeUserInfo = $wapiApi->uGetInfo($unionid);
        } else {
            if (empty($openid)) {
                $openid = session('users.openid');
            }
            $weLifeUserInfo = $wapiApi->getUserInfo($openid);
        }
        if (!empty($weLifeUserInfo['grade']) && in_array($weLifeUserInfo['grade'], config('grade'))) {
            $is_member = true;
        } else {
            $is_member = false;
        }
        if ($type == 1) {
            // 价格显示
            foreach ($foodList as $key => $food) {
                if (in_array($food['food_code'], $discountFoodKeyList)) {
                    if ($discountFoodList[$food['food_code']]['discount_type'] == 1) {
                        // 折扣
                        if ($storeInfo['member_rules'] == 1 && $is_member) {
                            // VIP
                            $foodList[$key]['discount_price'] = round($food['food_member_price'] * $discountFoodList[$food['food_code']]['discount_value']);
                        } else {
                            // 非vip处理
                            if ($discountFoodList[$food['food_code']]['is_vip'] == 1) {
                                // vip专享 不处理
                                $foodList[$key]['discount_price'] = 0;
                            } else {
                                $foodList[$key]['discount_price'] = round($food['food_price'] * $discountFoodList[$food['food_code']]['discount_value']);
                            }
                        }
                        $foodList[$key]['discount_value'] = $discountFoodList[$food['food_code']]['discount_value'];
                    } else if ($discountFoodList[$food['food_code']]['discount_type'] == 2) {
                        if ($discountFoodList[$food['food_code']]['is_vip'] == 1) {
                            // vip专享
                            if ($storeInfo['member_rules'] == 1 && $is_member) {
                                // 满足vip专享条件
                                if ($discountFoodList[$food['food_code']]['discount_value'] > $food['food_member_price']) {
                                    // 是VIP && 折扣价>vip价格, 不处理
                                    $foodList[$key]['discount_price'] = 0;
                                } else {
                                    $foodList[$key]['discount_price'] = round($discountFoodList[$food['food_code']]['discount_value']);
                                }
                            } else {
                                // 不满足vip专享条件
                                $foodList[$key]['discount_price'] = 0;
                            }
                        } else {
                            // 非vip专享
                            $foodList[$key]['discount_price'] = round($discountFoodList[$food['food_code']]['discount_value']);
                        }
                        $foodList[$key]['discount_value'] = round($discountFoodList[$food['food_code']]['discount_value']);
                    }
                    $foodList[$key]['discount_type'] = $discountFoodList[$food['food_code']]['discount_type'];
                } else {
                    $foodList[$key]['discount_price'] = 0;
                }
            }

            return $foodList;
        } else if ($type == 2) {
            // 下单
            // 优免总金额
            $orderDiscount = $orderDiscountMember = 0;
            // 处理购物车菜品
            foreach ($foodList as $user => &$userCart) {
                if (empty($userCart['shopping'])) {
                    continue;
                }
                foreach ($userCart['shopping'] as $foodCode => &$food) {
                    if (!in_array($food['food_code'], $discountFoodKeyList)) {
                        // 没有折扣,处理下一个
                        $food['food_discount']        = 0;
                        $food['food_discount_member'] = 0;
                        $food['discount_id']          = 0;
                        continue;
                    }
                    $food['discount_id'] = $discountFoodList[$food['food_code']]['discount_id'];
                    if ($discountFoodList[$food['food_code']]['discount_type'] == 1) {
                        $food['food_discount_member'] = $food['food_member_price'] - round($food['food_member_price'] * $discountFoodList[$food['food_code']]['discount_value']);
                        if ($discountFoodList[$food['food_code']]['is_vip'] == 1) {
                            // vip专享
                            $food['food_discount'] = 0;
                        } else {
                            // 非vip专享、折扣
                            $food['food_discount'] = $food['food_price'] - round($food['food_price'] * $discountFoodList[$food['food_code']]['discount_value']);
                        }
                    } else if ($discountFoodList[$food['food_code']]['discount_type'] == 2) {
                        // 优惠价格(特价)
                        if ($discountFoodList[$food['food_code']]['discount_value'] < $food['food_price']) {
                            if ($discountFoodList[$food['food_code']]['is_vip'] == 1) {
                                // vip专享
                                $food['food_discount'] = 0;
                            } else {
                                $food['food_discount'] = $food['food_price'] - round($discountFoodList[$food['food_code']]['discount_value']);
                            }
                        } else {
                            $food['food_discount'] = 0;
                        }
                        if ($discountFoodList[$food['food_code']]['discount_value'] < $food['food_member_price']) {
                            $food['food_discount_member'] = $food['food_member_price'] - round($discountFoodList[$food['food_code']]['discount_value']);
                        } else {
                            $food['food_discount_member'] = 0;
                        }
                    }
                    $orderDiscount       += $food['food_discount'] * $food['food_number'];
                    $orderDiscountMember += $food['food_discount_member'] * $food['food_number'];
                }
            }

            // 返回优免金额
            return ['food_list' => $foodList, 'order_discount' => $orderDiscount, 'order_discount_member' => $orderDiscountMember];
        } else if ($type == 3) {
            // 下单确认页
            foreach ($foodList as $user => &$userCart) {
                if (empty($userCart['shopping'])) {
                    continue;
                }
                foreach ($userCart['shopping'] as $foodCode => &$food) {
                    if (in_array($food['food_code'], $discountFoodKeyList)) {
                        if ($discountFoodList[$food['food_code']]['discount_type'] == 1) {
                            // 折扣
                            if ($storeInfo['member_rules'] == 1 && $is_member) {
                                // VIP
                                $food['discount_price'] = round($food['food_member_price'] * $discountFoodList[$food['food_code']]['discount_value']);
                            } else {
                                if ($discountFoodList[$food['food_code']]['is_vip'] == 1) {
                                    // vip专享, 不处理
                                    $food['discount_price'] = 0;
                                } else {
                                    $food['discount_price'] = round($food['food_price'] * $discountFoodList[$food['food_code']]['discount_value']);
                                }
                            }
                            $food['discount_value'] = $discountFoodList[$food['food_code']]['discount_value'];
                        } else if ($discountFoodList[$food['food_code']]['discount_type'] == 2) {
                            if ($discountFoodList[$food['food_code']]['is_vip'] == 1) {
                                // vip专享
                                if ($storeInfo['member_rules'] == 1 && $is_member) {
                                    // 满足vip专享条件
                                    if ($discountFoodList[$food['food_code']]['discount_value'] > $food['food_member_price']) {
                                        // 是VIP && 折扣价>vip价格, 不处理
                                        $food['discount_price'] = 0;
                                    } else {
                                        // 优惠价格(特价)
                                        $food['discount_price'] = round($discountFoodList[$food['food_code']]['discount_value']);
                                    }
                                } else {
                                    // 不满足vip专享条件
                                    $food['discount_price'] = 0;
                                }
                            } else {
                                // 非vip专享, 优惠价格(特价)
                                $food['discount_price'] = round($discountFoodList[$food['food_code']]['discount_value']);
                            }
                            $food['discount_value'] = round($discountFoodList[$food['food_code']]['discount_value']);
                        }
                        $food['discount_type'] = $discountFoodList[$food['food_code']]['discount_type'];
                    } else {
                        // 没有折扣
                        $food['discount_price'] = 0;
                    }
                }
            }

            return $foodList;
        }
    }
}