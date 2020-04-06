<?php

namespace app\api\controller;

use sdk\Choice;
use think\Db;
use think\Queue;
use think\Facade\Log;

/**
 * 订单处理
 * Class Order
 * @package app\api\controller
 */
class Order extends Base
{
    protected $auth   = true;
    protected $people = [];

    /**
     * 获取订单
     * @return array
     */
    public function get()
    {
        $storeCode = input('post.store_code', 0, 'int');
        $tableId   = input('post.table_id', '', 'string');

        if (!$storeCode or !$tableId) {
            return $this->returnMsg(0, '参数错误');
        }

        // 查询门店订单
        $choiceApi = new Choice(config('domain.choice_url'));
        $orderInfo = $choiceApi->getOrder($storeCode, $tableId);

        if (false === $orderInfo) {
            $this->failed($choiceApi->errCode, $choiceApi->errMsg);

            return $this->returnMsg(0, '获取订单失败');
        }

        $result = [];
        if (!empty($orderInfo)) {

            // 查询台位订单时间
            $orderTime = model('common/order')->getOrderFieldValue([
                'store_code'  => $storeCode,
                'table_id'    => $tableId,
                'order_state' => [
                    'eq', 10,
                ],
            ], 'order_create_time');

            // 订单状态
            $result['store_code']        = $storeCode;
            $result['order_people']      = $orderInfo['people'];
            $result['order_man']         = $orderInfo['man'];
            $result['order_child']       = $orderInfo['child'];
            $result['order_state']       = 10;
            $result['order_create_time'] = (!is_null($orderTime) ? $orderTime : time() - 600);
            $result['food_list']         = [];

            if (!empty($orderInfo['dishes'])) {
                foreach ($orderInfo['dishes'] as $item) {
                    $foodDetails = $this->getRedis()->get(config('cache_keys.store_menu_dish') . ":{$storeCode}:{$item['dish_code']}");
                    $foodWeigh   = (!$foodDetails or !isset($foodDetails['food_weigh'])) ? 0 : $foodDetails['food_weigh'];

                    // 如果是套餐，拆解套餐内容
                    $foodInfo = [
                        'food_code'         => $item['dish_code'],
                        'food_name'         => $item['dish_name'],
                        'food_price'        => $item['price'],
                        'food_member_price' => $item['member_price'],
                        'food_unit'         => $item['unit'],
                        'food_weigh'        => $foodWeigh,
                        'food_number'       => floatval($item['num']),
                        'food_state'        => 1,
                        'food_remark'       => $item['remark'],
                        'is_combo'          => $foodDetails['is_combo'] == 1 ? 1 : 0,
                        'details'           => ($foodDetails['is_combo'] == 1 && !empty($item['details'])) ? $item['details'] : [],
                    ];

                    $result['food_list'][] = $foodInfo;
                }
            }

        } else {
            $this->failed(0, '获取订单为空');
        }

        return $this->returnMsg(200, 'success', $result);
    }

    /**
     * 提交订单
     * @return mixed
     */
    public function commit()
    {
        Log::record('======================== 在线点餐后结账 ========================', 'notice');

        $storeCode   = input('post.store_code', 0, 'int');
        $tableId     = input('post.table_id', '', 'string');
        $people      = input('post.people', 0, 'int');
        $man         = input('post.man', 0, 'int');
        $child       = input('post.child', 0, 'int');
        $orderRemark = input('post.remark', '', 'string');

        Log::record('提交参数：' . print_r(input('post.'), true), 'notice');

        if (!$storeCode or !$tableId) {
            $this->failed(0, '参数错误');

            return $this->returnMsg(0, '参数错误');
        }

        if ($people != ($man + $child)) {
            return $this->returnMsg(0, '人数不符');
        }

        // 检测台位锁
        $lockName = config('cache_keys.order_lock') . ":{$storeCode}:{$tableId}";
        $lock     = $this->getRedis()->get($lockName);

        if (false !== $lock) {
            return $this->returnMsg(-2, '订单已锁定');
        }

        Db::startTrans();
        try {

            // 加台位锁
            $this->getRedis()->set($lockName, 1, config('cache_keys.order_lock_time'));
            // 查询购物车
            $result = $this->_getCartList($storeCode, $tableId);

            if (false == $result or empty($result['food_list'])) {
                throw new \Exception('购物车无商品');
            }

            Log::record('购物车商品：' . print_r($result, true), 'notice');

            $orderService = model('common/order', 'service');
            $orderData    = [
                'store_code' => $storeCode,
                'table_id'   => $tableId,
                'people'     => $people,
                'man'        => $man,
                'child'      => $child,
                'openid'     => $this->openid,
                'source'     => $result['source'],
                'food_list'  => $result['food_list'],
                'remark'     => $orderRemark,
                'pay_type'   => 0,
            ];

            // 菜品估清
            $this->_estimates($storeCode, $orderData);

            $this->checkSaleNum($result['food_list']);

            // 创建订单
            Log::record('订单数据：' . print_r($orderData, true), 'notice');
            $createResult = $orderService->createOrder($storeCode, $orderData);

            // 发送菜品
            $orderNo = $orderService->sendDish($createResult['food_data'], $createResult['order_sn']);
            Log::record('订单号：' . print_r($orderNo, true), 'notice');

            // 下单菜品存入Redis, 成功后推荐要使用
            $orderKey = config('cache_keys.order_food_list') . ":{$createResult['order_sn']}";
            if ($orderFoodList = $this->getRedis()->get($orderKey)) {
                $this->getRedis()->set(
                    $orderKey,
                    array_unique(array_merge($orderFoodList, array_column($result['food_list'], 'food_code'))),
                    get_future_time()
                );
            } else {
                $this->getRedis()->set(
                    $orderKey,
                    array_column($result['food_list'], 'food_code'),
                    get_future_time()
                );
            }
            if (empty($orderNo) or false === is_numeric($orderNo)) {
                Log::error("getChoiceOrderInfo|{$storeCode}|{$tableId}|{$orderNo}");
                // 关联订单
                $choiceOrderId = $orderService->getOrderInfo([
                    'store_code' => $storeCode,
                    'table_id'   => $tableId,
                ]);

                $orderNo = $choiceOrderId;
            }

            $orderService->updateOrderInfo($createResult['order_sn'], $orderNo);
            // 清空购物车
            $this->_clearCart($storeCode, $tableId);
            $this->_setNotify($storeCode, $tableId);
            $this->_setTableState($storeCode, $tableId, $orderNo);
            Db::commit();

            // 解台位锁
            $this->getRedis()->del($lockName);

            $returnMsg = $this->returnMsg(200, 'success',
                $createResult['order_sn'] ? ['order_no' => $createResult['order_sn']] : []);

            // 订单金额处理 => 为下单后抽奖使用
            // 不管够不够资格都要存, 如果下单成功页统一查, 就需要查表了
            if (!empty($createResult['food_data']['food_list']) && !empty($createResult['order_sn'])) {
                model('common/activity', 'service')->order(
                    $storeCode, $tableId, $createResult['food_data']['food_list'], $createResult['order_sn']
                );
            }
        } catch (\Exception $e) {
            Db::rollback();
            // 解台位锁
            $this->getRedis()->del($lockName);
            $this->failed($e->getCode(), $e->getMessage());

            return $this->returnMsg($e->getCode(), $e->getMessage());
        }

        // 放入队列, 调用微信数据上报接口
        $jobData                = $createResult;
        $jobData['order_type']  = 1;
        $jobData['create_time'] = time();
        $jobData['openid']      = $this->openid;
        $jobData['state']       = 'order';

        $isPushed = Queue::push('app\common\job\WxOrderSync', $jobData, 'wx_order_sync');
        if (false === $isPushed) {
            Log::error('添加消息队列失败: queue:wx_order_sync jobdata:' . json_encode($jobData));
        }

        return $returnMsg;
    }

    /**
     * 台位购物车
     * @param $storeCode
     * @param $tableId
     * @return bool
     */
    private function _getCartList($storeCode, $tableId)
    {
        $cartList = $this->getRedis()->get(config('cache_keys.table_shopping_cart') . ":{$storeCode}:{$tableId}");

        if (false === $cartList) {
            return false;
        }

        $this->people = $cartList; // 设置notify的信息

        $result = false;
        foreach ($cartList as $item) {
            $cacheData = $this->getRedis()->get(config('cache_keys.shopping_cart') . ":{$storeCode}:{$item}");

            if (!isset($result['source']) and $this->openid == $item) {
                //                if (! isset($cacheData['source'])) {
                //                    $result['source'] = 'online';
                //                } else {
                //                    $result['source'] = $cacheData['source'];
                //                }
                $result['source'] = isset($cacheData['source']) ? $cacheData['source'] : 'online';
            }

            if (!empty($cacheData) and !empty($cacheData['details'])) {
                foreach ($cacheData['details'] as &$value) {
                    $foodList = $this->getRedis()->get(config('cache_keys.store_menu_dish') . ":{$storeCode}:{$value['food_code']}");
                    unset($foodList['style']);
                    if (false != $foodList) {
                        $value['users_openid'] = $item;
                        $result['food_list'][] = array_merge($value, $foodList);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * 清空购物车
     * @param $storeCode
     * @param $tableId
     */
    private function _clearCart($storeCode, $tableId)
    {
        $cartList = $this->getRedis()->get(config('cache_keys.table_shopping_cart') . ":{$storeCode}:{$tableId}");

        if (false !== $cartList) {
            foreach ($cartList as $item) {
                $cacheName = config('cache_keys.shopping_cart') . ":{$storeCode}:{$item}";
                $cacheData = $this->getRedis()->get($cacheName);

                if (!empty($cacheData) and !empty($cacheData['details'])) {
                    $this->getRedis()->del($cacheName);
                }
                $peopleCacheName = config('cache_keys.shopping_cart_people') . ":{$storeCode}:{$item}";
                $peopleCache     = $this->getRedis()->get($peopleCacheName);
                if (!empty($peopleCache)) {
                    $this->getRedis()->del($peopleCacheName);
                }
            }
        }

    }

    /**
     * 菜品估清
     * @param $storeId
     * @param $orderData
     * @throws \Exception
     */
    private function _estimates($storeId, $orderData)
    {
        $estimatesList = $this->getRedis()->get(config('cache_keys.choice_estimates') . ":{$storeId}");

        if (!empty($estimatesList) and !empty($orderData)) {
            $soldOut = [];
            foreach ($orderData['food_list'] as $item) {
                if (array_key_exists($item['food_code'], $estimatesList)) {
                    $dishNumber = $estimatesList[$item['food_code']];

                    if ($dishNumber < $item['food_number']) {
                        $soldOut[] = $item['food_name'];
                    }
                }

                //判断自选套餐中的菜品是否估清
                if ($item['is_multiple_combo'] == 1 &&
                    !empty($item['combo_detail']) &&
                    !array_key_exists($item['food_code'], $estimatesList)) {
                    foreach ($item['combo_detail'] as $comboDishCode) {
                        if (array_key_exists($comboDishCode['dish_code'], $estimatesList)) {
                            $comboDishNumber = $estimatesList[$comboDishCode['dish_code']];

                            if ($comboDishNumber < $comboDishCode['num']) {
                                $soldOut[] = $comboDishCode['dish_name'];
                            }
                        }
                    }
                }
            }

            if (!empty($soldOut)) {
                throw new \Exception(implode(',', $soldOut) . '抢光了', "-8");
            }
        }

        return $orderData;
    }

    /*
     * 判断起售数量
     * @throws \Exception
     */
    private function checkSaleNum($foodList)
    {
        $dishSet = [];
        foreach ($foodList as $item) {
            if (!isset($dishSet[$item['food_code']])) {
                $dishSet[$item['food_code']] = [
                    'num'           => $item['food_number'],
                    'food_name'     => $item['food_name'],
                    'food_sale_num' => $item['food_sale_num'],
                ];
            } else {
                $dishSet[$item['food_code']]['num'] += $item['food_number'];
            }
        }

        foreach ($dishSet as $set) {
            if ($set['num'] < $set['food_sale_num']) {
                throw new \Exception($set['food_name'] . '起售数量为' . $set['food_sale_num']);
            }
        }
    }

    /**
     * 设置通知用户
     * @param $storeCode
     * @param $tableId
     */
    private function _setNotify($storeCode, $tableId)
    {
        if (!empty($this->people)) {

            $keys = array_keys($this->people, $this->openid);
            if (!empty($keys)) {
                unset($this->people[current($keys)]);
            }

            foreach ($this->people as $item) {
                $this->getRedis()->set(
                    config('cache_keys.order_notify') . ":{$storeCode}:{$tableId}:{$item}",
                    1,
                    config('cache_keys.notify_cache_time')
                );
            }
        }
    }

    /**
     * 设置台位状态
     * @param $storeId
     * @param $tableId
     * @param $orderNo
     */
    private function _setTableState($storeId, $tableId, $orderNo)
    {
        $tableInfo = $this->getRedis()->get(config('cache_keys.choice_table_state') . ":{$storeId}:{$tableId}");
        if (false !== $tableInfo) {
            $tableInfo['table_state'] = "3";

            $this->getRedis()->set(
                config('cache_keys.choice_table_state') . ":{$storeId}:{$tableId}",
                $tableInfo,
                config('cache_keys.choice_cache_time')
            );

            // 设置台位用户
            $this->getRedis()->set(
                config('cache_keys.table_user') . ":{$storeId}:{$tableId}:{$orderNo}",
                $this->openid,
                get_future_time()
            );
        }
    }
}
