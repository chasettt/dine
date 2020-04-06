<?php

namespace app\common\service;

use sdk\Mwee;
use sdk\Redis;
use sdk\Payment as payApi;
use sdk\Wapi;

class Payment
{

    public $errCode;
    public $errMsg;

    /**
     * @param $input
     * @param $orderNo
     * @param $openid
     * @return array
     */
    public function productBuy($input, $orderNo, $openid, $uid = 0, $unionid = '')
    {
        // 验证订单信息
        $orderModel = model('common/order');
        $condition  = [
            'order_no' => $orderNo,
        ];
        if ($uid) {
            $condition['user_id'] = $uid;
        } else if ($openid) {
            $condition['create_users'] = $openid;
        }
        $orderInfo = $orderModel->getOrderInfo($condition, 'order_id,store_code,order_type,order_source,table_id,create_users,order_no,order_amount,order_amount_member,pay_state,order_expire_time,takeaway_id,brand_id,user_id');

        if (empty($orderInfo)) {
            return ['error' => '订单不存在' . $orderNo];
        }
        if ((int)$orderInfo['pay_state'] == 1) {
            return ['error' => '订单已支付'];
        }
        if ($orderInfo['order_expire_time'] <= time()) {
            return ['error' => '订单已过期'];
        }

        // 检测菜品是否估清
        $orderFoodList = model('common/orderFood')->getFoodList(
            ['order_no' => $orderInfo['order_no']],
            'food_code,food_name,food_number,food_weigh,food_price,food_member_price'
        );

        if (empty($orderFoodList)) {
            return ['error' => '无可下单菜品'];
        }
        // 设置brand_id
        $weLifeService = model('common/welife', 'service')->setInit($orderInfo['brand_id']);
        // 营销活动
        $orderInfo['food_list'] = model('common/discount', 'service')->process($orderInfo['store_code'], $orderFoodList, $openid, 1, $unionid, $orderInfo['brand_id']);

        // 查询门店信息
        $storeModel = model('common/store');
        $storeInfo  = $storeModel->getStore([
            'store_code' => $orderInfo['store_code'],
        ], 'store_code,store_name,welife_shopid,welife_cashier_id,member_rules,mwee_shopid,payment_version,enabled_welife_pay,payment_platform');

        if (emptY($storeInfo)) {
            return ['error' => '门店不存在'];
        }

        $orderPayInfo = [
            'store_code'     => $orderInfo['store_code'],
            'user_id'        => $orderInfo['user_id'],
            'create_users'   => $orderInfo['create_users'],
            'order_id'       => $orderInfo['order_id'],
            'order_sn'       => $orderInfo['order_no'],
            'store_id'       => $orderInfo['store_code'],
            'table_id'       => $orderInfo['table_id'],
            'order_type'     => $orderInfo['order_type'],
            'order_source'   => $orderInfo['order_source'],
            'store_info'     => $storeInfo,
            'order_amount'   => $orderInfo['order_amount'],
            'takeaway_id'    => $orderInfo['takeaway_id'],
            'use_card'       => !isset($input['use_card']) ? 0 : 1,
            'brand_id'       => $orderInfo['brand_id'],
            'balance_amount' => 0,
            'credit_amount'  => 0,
            'coupon_amount'  => 0,
            'pay_member'     => 0,
        ];

        // 使用卡号
        if (isset($input['card_no'])) {
            $weLifeAccountInfo = $weLifeService->welifeUserAccounts($input['card_no']);
            if (empty($weLifeAccountInfo)) {
                return ['error' => '卡不存在'];
            }

            // 是否会员
            if (true === in_array($weLifeAccountInfo['grade'], config('grade'))) {
                $orderPayInfo['pay_member'] = 1;
            }

            $orderPayInfo['card_no'] = $input['card_no'];

            // 使用储值余额
            if (isset($input['balance_pay']) and $input['balance_pay'] > 0) {
                $balanceTotal = $weLifeAccountInfo['balance']; // 卡内余额
                $balancePay   = $input['balance_pay'] * 100; // 余额支付金额
                if ($balancePay > 0) {
                    if ($balancePay > $balanceTotal) {
                        return ['error' => '账户余额不足'];
                    } else {
                        $orderPayInfo['balance_amount'] = $balancePay / 100;
                    }
                }
            }

            // 使用积分
            if (isset($input['credit_pay']) && $input['credit_pay'] > 0) {
                $creditTotal = $weLifeAccountInfo['credit'];
                $creditPay   = $input['credit_pay'];
                if ($creditPay > 0) {
                    if (!$weLifeAccountInfo['use_credit']) {
                        return ['error' => '积分不可用'];
                    }
                    if ($creditPay > $creditTotal) {
                        return ['error' => '积分不足'];
                    } else {
                        $orderPayInfo['credit_amount'] = $creditPay;
                    }
                }
            }

            // 代金券
            if (isset($input['coupon_ids']) && !empty($input['coupon_ids'])) {
                $couponIds = $input['coupon_ids'];

                if (!empty($couponIds) && is_array($couponIds)) {
                    // 取得所有可用优惠券
                    $validCouponList = $weLifeService->getValidCoupons($storeInfo, $orderInfo, $weLifeAccountInfo['coupons'], $orderPayInfo['pay_member'] ? true : false);

                    if (empty($validCouponList['valid'])) {
                        return ['error' => '无可用的优惠券'];
                    }
                    $validCouponList = $validCouponList['valid'];

                    $denoCouponList    = $giftCouponList = [];
                    $templateCouponArr = []; // 存放同种类型的券
                    $mixCouponArr      = [];      // 存放不可混用的券模板
                    $couponAmount      = 0; // 券抵扣金额
                    foreach ($couponIds as $couponId) {

                        if (isset($validCouponList[$couponId])) {
                            $couponInfo = $validCouponList[$couponId];

                            // 分类存放不同类型的优惠券
                            if ($couponInfo['type'] == 1) {
                                $denoCouponList[$couponId] = $couponInfo;
                            } elseif ($couponInfo['type'] == 2) {
                                $giftCouponList[$couponId] = $couponInfo;
                            } else {
                                return ['error' => '系统暂不支持此类型的优惠券'];
                            }

                            // 按类型按券模板压入券
                            $templateCouponArr[$couponInfo['type']][$couponInfo['template_id']][$couponId] = $couponInfo;

                            // 按类型压入不可混用的券模板
                            if ($couponInfo['mix_use'] === true) {
                                // 可混用
                                $mixCouponArr[1][$couponInfo['template_id']] = 1;
                            } else {
                                $mixCouponArr[0][$couponInfo['template_id']] = 1;
                            }

                            // 累计抵扣金额
                            $couponAmount += $couponInfo['deno'];
                        } else {
                            return ['error' => '账户中的一个或多个代金券或礼品券不存在或不可用'];
                        }
                    }

                    foreach ($couponIds as $couponId) {
                        $couponInfo = $validCouponList[$couponId];
                        // 验证使用张数
                        if ($couponInfo['max_use'] > 0) {
                            $count = count($templateCouponArr[$couponInfo['type']][$couponInfo['template_id']]);
                            if ($count > $couponInfo['max_use']) {
                                return ['error' => '券张数超出可使用数量'];
                            }
                        }
                        // 验证是否混用
                        if (!$couponInfo['mix_use']) {
                            $count_nomix = isset($mixCouponArr[1]) ? count($mixCouponArr[0]) : 0;
                            $count_mix   = isset($mixCouponArr[1]) ? count($mixCouponArr[1]) : 0;

                            if ($count_nomix > 1 || ($count_nomix == 1 && $count_mix > 0)) {
                                return ['error' => '劵有混用,请检查'];
                            }
                        }
                    }

                    $orderPayInfo['coupon_amount']     = $couponAmount;
                    $orderPayInfo['deno_coupons_list'] = $denoCouponList;
                    $orderPayInfo['gift_coupons_list'] = $giftCouponList;
                }
            }

        }

        // 是否执行会员价
        if ($orderPayInfo['pay_member'] and $storeInfo['member_rules']) {
            $orderPayInfo['order_amount'] = $orderInfo['order_amount_member'];
        }

        // 计算本次需要微信支付的订单金额
        $payAmount = floatval($orderPayInfo['order_amount']) - (floatval($orderPayInfo['balance_amount']) + floatval($orderPayInfo['credit_amount']) + floatval($orderPayInfo['coupon_amount']));
        if ($payAmount < 0) {
            return ['error' => '所选支付金额超出订单金额'];
        }

        $orderPayInfo['pay_amount'] = price_format($payAmount);

        return [
            'order_pay_info' => $orderPayInfo,
        ];

    }

    /**
     * 第三方支付
     * @param $orderInfo
     * @param $from
     * @return array|bool
     */
    public function apiPay($orderInfo)
    {
        try {

            if ($orderInfo['store_info']['enabled_welife_pay']) {
                \think\Log::record('==================== 微生活支付 ====================', 'notice');
                // 微生活支付

                debug('welife_start');
                $payResult = $this->_weLifePay($orderInfo);
                debug('welife_end');

                $welifeTime = debug('welife_start', 'welife_end') . 's';
                \think\Log::record('调用微生活支付耗时：' . $welifeTime, 'notice');
                \think\Log::record('微生活支付：' . print_r($payResult, true), 'notice');

                // 更新订单
                $update                      = [];
                $update['welife_bizid']      = $payResult['biz_id'];
                $update['welife_card_no']    = $orderInfo['card_no'];
                $update['welife_tcid']       = $payResult['result']['tcid'];
                $update['balance_amount']    = $orderInfo['balance_amount'];
                $update['credit_amount']     = $orderInfo['credit_amount'];
                $update['coupon_amount']     = $orderInfo['coupon_amount'];
                $update['deno_coupons_info'] = isset($orderInfo['deno_coupons_list']) ? json_encode($orderInfo['deno_coupons_list']) : '';
                $update['gift_coupons_info'] = isset($orderInfo['gift_coupons_list']) ? json_encode($orderInfo['gift_coupons_list']) : '';
                $update['pay_member']        = $orderInfo['pay_member'];
                $update['member_rules']      = $orderInfo['store_info']['member_rules'];
                $update['create_users']      = $orderInfo['create_users'];

                model('common/order')->upOrderInfo(['order_id' => $orderInfo['order_id']], $update);
                \think\Log::record('订单更新：' . print_r($update, true), 'notice');

                if ($orderInfo['pay_amount'] == 0) {
                    // 更新订单
                    $orderInfo['welife_bizid'] = $payResult['biz_id'];
                    $orderInfo['welife_tcid']  = $payResult['result']['tcid'];
                    $this->updateProductBuy($orderInfo['order_sn'], 0, $orderInfo);
                    $url = '';
                    if ($orderInfo['order_type'] == 3) {
                        $url = config('domain.web_url') . sprintf(config('address.online_order_success'), $orderInfo['store_id'], $orderInfo['table_id'], $orderInfo['order_sn'], 'eatIn');
                    }

                    if ($orderInfo['order_type'] == 2) {
                        $url = config('domain.web_url') . sprintf(config('address.takeout_order_success'), $orderInfo['store_id'], $orderInfo['order_sn'], 'takeout');
                    }

                    if ($orderInfo['order_type'] == 4) {
                        $url = config('domain.web_url') . sprintf(config('address.takeaway_order_success'), $orderInfo['store_id'], $orderInfo['order_sn'], $orderInfo['takeaway_id'], 'takeaway');
                    }

                    if ($orderInfo['order_type'] == 5) {
                        $url = config('domain.web_url') . sprintf(config('address.fastfood_order_success'), $orderInfo['store_id'], $orderInfo['order_sn'], $orderInfo['table_id'], 'fastfood');
                    }

                    if ($orderInfo['order_type'] == 6) {
                        $url = config('domain.web_url') . sprintf(config('address.fastfood_order_success'), $orderInfo['store_id'], $orderInfo['order_sn'], $orderInfo['table_id'], 'ipad_fastfood');
                    }

                    if ($orderInfo['order_type'] == 7) {
                        $url = config('domain.web_url') . sprintf(config('address.fastfood_xcx_order_success'), $orderInfo['store_id'], $orderInfo['order_sn'], $orderInfo['table_id'], 'fastfood_xcx');
                    }

                    return [
                        'pay_type'    => 1,
                        'pay_success' => $url,
                    ];
                }
            }

            if ($orderInfo['pay_amount'] > 0) {
                \think\Log::record('==================== 微信支付 ====================', 'notice');
                // 还需微信支付,获取美味支付参数返回到前端跳转支付
                debug('webpay_start');
                // $payParams = $this->_getPayParams($orderInfo);

                $payParams = $this->_xibeiPayParams($orderInfo);

                debug('webpay_end');

                if (!$payParams) {
                    throw new \Exception('获取支付参数失败');
                }

                $webPayTime = debug('webpay_start', 'webpay_end') . 's';
                \think\Log::record('调用微信支付耗时：' . $webPayTime, 'notice');

                \think\Log::record('=============== 微信支付信息： ===============' . print_r($payParams, true), 'notice');
                $payType = $orderInfo['store_info']['payment_version'] == "v1" ? 1 : ($orderInfo['store_info']['payment_version'] == "v2" ? 2 : 1);
                $payData = ['pay_order_no' => $payParams['pay_order_no'], 'pay_type' => $payType];

                if ($orderInfo['use_card'] == 0) {
                    $payData['welife_card_no'] = isset($orderInfo['card_no']) ? $orderInfo['card_no'] : 0;
                    $payData['pay_member']     = $orderInfo['pay_member'];
                    $payData['member_rules']   = $orderInfo['store_info']['member_rules'];
                }

                $payData['create_users'] = $orderInfo['create_users'];
                model('common/order')->upOrderInfo([
                    'order_id' => $orderInfo['order_id'],
                ], $payData);

                \think\Log::record('微信支付订单更新：' . print_r($payData, true), 'notice');

                if ($orderInfo['order_type'] == 7) {
                    $payParams['pay_type'] = 2;

                    return $payParams;
                }

                return [
                    'pay_type'     => 2,
                    'pay_params'   => $payParams['pay_params'],
                    'pay_platform' => isset($payParams['payment_platform']) ? $payParams['payment_platform'] : '',
                    'pay_url'      => isset($payParams['pay_url']) ? $payParams['pay_url'] : '',
                    'pay_content'  => isset($payParams['pay_content']) ? $payParams['pay_content'] : '',
                ];

            } else {
                throw new \Exception('支付金额错误');
            }

        } catch (\Exception $e) {
            $this->errCode = $e->getCode();
            $this->errMsg  = $e->getMessage();

            return false;
        }
    }

    /**
     * 微生活支付
     * 提交交易预览
     * @param $orderPayInfo
     * @return array
     * @throws \Exception
     */
    private function _weLifePay($orderPayInfo)
    {
        if (empty($orderPayInfo['store_info']['welife_shopid'])) {
            throw new \Exception('未知微生活门店');
        }
        if (empty($orderPayInfo['store_info']['welife_cashier_id'])) {
            throw new \Exception('未知门店收银员');
        }

        $bizId = makeOrderSn();

        $params                   = [];
        $params['biz_id']         = $bizId;
        $params['cno']            = $orderPayInfo['card_no'];
        $params['shop_id']        = $orderPayInfo['store_info']['welife_shopid'];
        $params['cashier_id']     = $orderPayInfo['store_info']['welife_cashier_id'];
        $params['consume_amount'] = $orderPayInfo['order_amount'] * 100;
        $params['payment_amount'] = $orderPayInfo['pay_amount'] * 100;   // consume_amount - sub_balance - sub_credit - 优惠券金额 - 菜品券金额
        $params['payment_mode']   = 6; // 线上微信

        if ($orderPayInfo['balance_amount'] > 0) {
            $params['sub_balance'] = $orderPayInfo['balance_amount'] * 100;
        }
        if ($orderPayInfo['credit_amount']) {
            $params['sub_credit'] = $orderPayInfo['credit_amount'];
        }
        if (isset($orderPayInfo['deno_coupons_list'])) {
            $params['deno_coupon_ids'] = array_keys($orderPayInfo['deno_coupons_list']);
        }

        if (isset($orderPayInfo['gift_coupons_list'])) {
            $params['gift_coupons_ids'] = array_keys($orderPayInfo['gift_coupons_list']);
            $params['products']         = [];
            foreach ($orderPayInfo['gift_coupons_list'] as $couponInfo) {
                $caipinInfo = $couponInfo['__product_info__'];

                if (!empty($couponInfo['deno'])) {
                    $foodPrice = floatval($couponInfo['deno']);
                } else {
                    // 会员
                    if (isset($caipinInfo['discount_price']) and $caipinInfo['discount_price'] > 0) {
                        $foodPrice = $caipinInfo['discount_price'];
                    } else {
                        $foodPrice = $orderPayInfo['pay_member'] ? $caipinInfo['food_member_price'] : $caipinInfo['food_price'];
                    }
                }
                $params['products'][] = [
                    'name'        => str_replace(['+', '-'], ['_'], $caipinInfo['food_name']),
                    'no'          => $caipinInfo['food_code'],
                    'num'         => $caipinInfo['food_number'],
                    'price'       => $foodPrice * 100,
                    'is_activity' => 2,
                ];
            }
        }

        if ($orderPayInfo['order_type'] == 4) {
            $params['remark'] = '成品外带-' . $orderPayInfo['order_sn'];
        }

        if ($orderPayInfo['order_type'] == 2) {
            $params['remark'] = '堂食外带-' . $orderPayInfo['order_sn'];
        }

        if ($orderPayInfo['order_type'] == 3) {
            $params['remark'] = '堂食-' . $orderPayInfo['order_sn'];
        }

        if ($orderPayInfo['order_type'] == 5) {
            $params['remark'] = '快餐-' . $orderPayInfo['order_sn'];
        }

        if ($orderPayInfo['order_type'] == 6) {
            $params['remark'] = 'ipad快餐-' . $orderPayInfo['order_sn'];
        }

        if ($orderPayInfo['order_type'] == 7) {
            $params['remark'] = '小程序快餐-' . $orderPayInfo['order_sn'];
        }

        // 设置brand_id
        $weLifeService = model('common/welife', 'service')->setInit($orderPayInfo['brand_id']);

        $result = $weLifeService->dealPreview($params);
        if ($result) {
            return [
                'biz_id' => $bizId,
                'result' => $result,
            ];
        } else {
            throw new \Exception($weLifeService->errMsg);
        }
    }

    /**
     * 更新订单信息
     * @param $orderSn
     * @param $payAmount
     * @param $orderInfo
     * @param string $tradeNo
     * @throws \Exception
     */
    public function updateProductBuy($orderSn, $payAmount, $orderInfo, $tradeNo = '', $payOrderNo = '')
    {
        $orderModel = model('common/order');

        $condition['order_no'] = $orderSn;

        $update['pay_state']  = 1;
        $update['pay_time']   = time();
        $update['trade_no']   = $tradeNo;
        $update['pay_amount'] = $payAmount;

        $result = $orderModel->upOrderInfo($condition, $update);

        if (!$result) {
            throw new \Exception('更新订单失败');
        }

        $payData = [];

        if (!empty($orderInfo['welife_tcid'])) {
            // 如有微生活支付,提交交易, 设置brand_id
            $weLifeService = model('common/welife', 'service')->setInit($orderInfo['brand_id']);
            $commitResult  = $weLifeService->dealCommit($orderInfo['welife_bizid']);
            \think\Log::record('==================== 微生活支付返回信息 ====================', 'notice');
            \think\Log::record(print_r($commitResult, true), 'notice');
//          [deal_id] => 413702236757839872
//          [stored_pay] => 70100 使用储值
//          [stored_sale_pay] => 283 使用储值实收金额     两个相减是使用储值奖励金额
            if (false === $commitResult) {
                throw new \Exception($weLifeService->errMsg);
            }

            $payData['welife_deal_id']       = $commitResult['deal_id'];
            $payData['final_balance_amount'] = $commitResult['stored_sale_pay'] / 100;
        }

        if (!empty($payOrderNo)) {
            $payData['pay_order_no'] = $payOrderNo;
        }

        if (!empty($payData)) {
            $result = $orderModel->upOrderInfo(['order_no' => $orderSn], $payData);
            if (!$result) {
                throw new \Exception('更新订单失败');
            }
        }

        try {
            // 清空购物车
            $key = !empty($orderInfo['user_id']) ? $orderInfo['user_id'] : $orderInfo['create_users'];
            $this->clearCart($orderInfo['store_code'], $key);
            // 茶位费
            model('common/fee', 'service')->setFlag($orderInfo);
            // 添加到发送菜品队列
            model('common/queue', 'service')->push($orderSn);
        } catch (\Exception $e) {
            throw new \Exception('入栈失败');
        }
    }

    private function clearCart($storeId, $openid)
    {
        $redis    = new Redis();
        $cartName = config('cache_keys.shopping_cart') . ":{$storeId}:{$openid}";
        $cartInfo = $redis->get($cartName);
        if (false !== $cartInfo) {
            $redis->del($cartName);
        }
        $peopleCacheName = config('cache_keys.shopping_cart_people') . ":{$storeId}:{$openid}";
        $peopleCache     = $redis->get($peopleCacheName);
        if (!empty($peopleCache)) {
            $redis->del($peopleCacheName);
        }
    }

    /**
     * 西贝web支付
     * @param $orderInfo
     * @return array
     * @throws \Exception
     */
    private function _xibeiPayParams($orderInfo)
    {
        $params     = [];
        $payOrderNo = makeOrderSn();

        // 新支付接口
        $params['app_id']       = config('payment.app_id');
        $params['store_id']     = $orderInfo['store_info']['store_code'];
        $params['out_order_sn'] = $payOrderNo;
        $params['title']        = $orderInfo['store_info']['store_name'] . '-堂食';
        $params['amount']       = $orderInfo['pay_amount'] * 100;
        $params['notify_url']   = config('domain.web_url') . '/payment/notify/xibei?order_no=' . $orderInfo['order_sn'];
        $params['channel']      = ('daxing_airport' == $orderInfo['store_info']['payment_platform']) ? 'daxing_airport_pub_wap' : 'wx_pub';
        $params['extra']        = [
            'return_url' => config('domain.web_url') . '/payment/order/success?store_id=' . $params['store_id'] . '&order_sn=' . $orderInfo['order_sn'],
            'openid'     => $orderInfo['openid'],
        ];

//        if ($orderInfo['order_type'] == 7) {
//            $params['channel'] = 'wx_miniprogram';
//            $params['extra']   = [
//                'appid'  => empty(config('weapp')[$orderInfo['brand_id']]['appid']) ? '' : config('weapp')[$orderInfo['brand_id']]['appid'],
//                'openid' => $orderInfo['openid'],
//            ];
//        } else {
            // @todo 是否开启微生活
            //            $params['channel'] = $orderInfo['store_info']['enabled_welife_pay'] ? 'wx_pub' : 'wx_pub_wap';

//        }

        \think\Log::record('==================== 西贝支付参数 ====================', 'notice');
        \think\Log::record(print_r($params, true), 'notice');

        if ($orderInfo['order_type'] == 4) {
            $params['title'] = $orderInfo['store_info']['store_name'] . '-成品外带';
        }

        if ($orderInfo['order_type'] == 2) {
            $params['title'] = $orderInfo['store_info']['store_name'] . '-堂食外带';
        }

        if ($orderInfo['order_type'] == 3) {
            $params['title'] = $orderInfo['store_info']['store_name'] . '-堂食';
        }

        if ($orderInfo['order_type'] == 5) {
            //$params['title'] = $orderInfo['store_info']['store_name'] . '-快餐';
            $params['title'] = $orderInfo['store_info']['store_name'];
        }

        if ($orderInfo['order_type'] == 6) {
            $params['title'] = $orderInfo['store_info']['store_name'] . '-ipad快餐';
        }

        if ($orderInfo['order_type'] == 7) {
            $params['title'] = $orderInfo['store_info']['store_name'] . '-小程序快餐';
        }

        $paymentApi     = new payApi(config('domain.payment_url'));
        $params['sign'] = $paymentApi->getSign($params);
        $result         = $paymentApi->newWebPay($params);

        if ($result) {
            if ($orderInfo['order_type'] == 7) {
                $result['pay_order_no'] = $payOrderNo;

                return $result;
            }

            return [
                'pay_order_no'     => $payOrderNo,
                'pay_params'       => $result,
                'version'          => 'v2',
                'payment_platform' => $orderInfo['store_info']['payment_platform'],
                'pay_url'          => isset($result['pay_url']) ? $result['pay_url'] : '',
                'pay_content'      => isset($result['pay_content']) ? $result['pay_content'] : '',
            ];
        } else {
            throw new \Exception($paymentApi->errMsg, $paymentApi->errCode);
        }
    }

    public function refund($orderSn, $type, $price = 0)
    {
        \think\Log::notice('=============== 在线点餐退款 ===============');

        if (!$orderSn or !$type) {
            return ['code' => 1000101, 'msg' => '参数错误'];
        }

        \think\Log::notice('=============== 订单信息 ===============');

        $orderInfo = model('common/order')->getOrderInfo(['order_no' => $orderSn]);

        \think\Log::notice(print_r($orderInfo, true));
        if (empty($orderInfo)) {
            return ['code' => 1000121, 'msg' => '无此订单'];
        }

        if ($orderInfo['pay_state'] == 4) {//已退款订单返回成功
            return true;
        } else if ($orderInfo['pay_state'] != 1 && $orderInfo['pay_state'] != 5) {
            return ['code' => 1000121, 'msg' => '订单支付状态错误'];
        }

        $refundInfo = model('common/refund')->getRefundInfo(['order_no' => $orderInfo['order_no']]);

        switch ($type) {
            case 'welife' :
                //微生活撤销交易
                \think\Log::notice('=============== 微生活撤销消费 ===============');
                $wapi      = new Wapi(config('domain.welife_url'), session('users.brand_id'));
                $storeInfo = model('common/store')->getStore(['store_code' => $orderInfo['store_code']]);
                if (empty($storeInfo) || !$storeInfo['welife_cashier_id']) {
                    return ['code' => 1000101, 'msg' => '无收银员id'];
                }
                $welifeRefundInfo = $wapi->dealCancel($orderInfo['welife_bizid'], $storeInfo['welife_cashier_id']);
                \think\Log::notice(print_r($welifeRefundInfo, true));
                if (false === $welifeRefundInfo) {
                    return ['code' => 1000121, 'msg' => $wapi->errMsg];
                }

                if ($welifeRefundInfo['result'] !== "SUCCESS") {
                    return ['code' => 1000121, 'msg' => '微生活撤销交易失败'];
                }

                $this->updateRefund($orderSn, ['refund_welife' => 1, 'refund_welife_desc' => json_encode($welifeRefundInfo)]);
                $this->updateOrderRefund($orderSn, $orderInfo);
                break;
            case 'xibei':
                //西贝支付退款
                if (!empty($orderInfo['pay_order_no']) && floatval($orderInfo['pay_amount']) != 0) {
                    \think\Log::notice('=============== 西贝退款信息 ===============');
                    $refundKeys      = 1;
                    $refundXibeiDesc = [];
                    $refundAmount    = 0;

                    if ($price == 0) {
                        $price = $orderInfo['pay_amount'];
                    }

                    if (!empty($refundInfo) and $refundInfo['refund_xibei'] == 1) {
                        $refundXibeiDesc = json_decode($refundInfo['refund_xibei_desc'], true);
                        $refundKeys      = count($refundXibeiDesc) + 1;

                        foreach ($refundXibeiDesc as $descItem) {
                            $refundAmount += $descItem['refund_amount'] / 100;
                        }
                    }

                    if ($price > $orderInfo['pay_amount'] - $refundAmount) {
                        return ['code' => 1000122, 'msg' => '退款金额大于订单可退金额'];
                    }

                    $data = [
                        "app_id"        => config('payment.app_id'),
                        "out_order_sn"  => $orderInfo['pay_order_no'],
                        "out_refund_sn" => $orderInfo['order_no'] . "-{$refundKeys}",
                        //                        "order_amount"  => $price * 100,
                        "refund_amount" => $price * 100,
                        "notify_url"    => "",
                    ];

                    $paymentApi   = new payApi(config('domain.payment_url'));
                    $data['sign'] = $paymentApi->getSign($data);
                    \think\Log::notice(print_r($data, true));

                    $result = $paymentApi->refund($data);
                    \think\Log::notice(print_r($result, true));
                    if (false === $result) {
                        return ['code' => 1000121, 'msg' => $paymentApi->errMsg];
                    }
                    $refundXibeiDesc[] = $result;

                    $this->updateRefund($orderSn, ['refund_xibei' => 1, 'refund_xibei_desc' => json_encode($refundXibeiDesc)]);
                    $this->updateOrderRefund($orderSn, $orderInfo);
                }
                break;
            default:
                return ['code' => 1000121, 'msg' => '未知退款类型'];
        }

        return true;
    }

    private function updateRefund($orderSn, $data)
    {
        $refundModel = model('common/refund');
        $refundInfo  = $refundModel->getRefundInfo(['order_no' => $orderSn], 'refund_id');
        if (empty($refundInfo)) {
            $data = array_merge(['order_no' => $orderSn], $data);
            $refundModel->addRefund($data);
        } else {
            $refundModel->updateRefund(['order_no' => $orderSn], $data);
        }
    }

    private function updateOrderRefund($orderNo, $orderInfo)
    {
        // 更新退款状态
        $refundModel = model('common/refund');
        $refundInfo  = $refundModel->getRefundInfo(['order_no' => $orderNo]);
        $orderState  = 5;  //部分退款

        if (floatval($orderInfo['pay_amount']) == 0
            && floatval($orderInfo['balance_amount']) == 0
            && floatval($orderInfo['credit_amount']) == 0
            && floatval($orderInfo['coupon_amount']) == 0) {
            return;
        }

        $wxRefund     = false;
        $welifeRefund = false;

        //判断订单是否全部退款
        //微信
        if (floatval($orderInfo['pay_amount']) == 0) {
            //如果微信支付金额为0，默认微信退款完成
            $wxRefund = true;
        } else if ($refundInfo['refund_xibei'] == 1) {
            $refundXibeiDesc = json_decode($refundInfo['refund_xibei_desc'], true);

            //循环退款记录，如果退款金额累加等于微信支付金额，微信退款完成
            if (!empty($refundXibeiDesc)) {
                $orderAmount = $orderInfo['pay_amount'] * 100;
                $refundAmout = 0;

                foreach ($refundXibeiDesc as $descItem) {
                    $refundAmout += $descItem['refund_amount'];
                }

                if ($orderAmount == $refundAmout) {
                    $wxRefund = true;
                }
            }
        }

        //微生活
        if (floatval($orderInfo['balance_amount']) == 0 && floatval($orderInfo['credit_amount']) == 0 && floatval($orderInfo['coupon_amount']) == 0) {
            //未使用微生活支付，默认微生活退款完成
            $welifeRefund = true;
        } else if ($refundInfo['refund_welife'] == 1) {
            $welifeRefund = true;
        }

        if ($wxRefund == true && $welifeRefund == true) {
            $orderState = 4;//全部退款
        }
        model('common/order')->upOrderInfo(['order_no' => $orderNo], ['pay_state' => $orderState]);
    }
}
