<?php
/**
 * Created by web360
 * DateTime: 2018-11-13 16:25:08
 */

namespace app\common\service;

use Exception;
use sdk\Redis;
use sdk\Wapi;

/**
 * 活动
 */
class Activity
{
    /**
     * 获取门店活动信息
     * @param int 门店ID
     * @param type info:获取信息|order:下单
     * @return array 活动信息
     */
    public function getInfo($storeId, $type = 'info')
    {
        $redis        = new Redis();
        $activityInfo = $redis->get(config('cache_keys.activity_draw') . ":{$storeId}");
        if (!$activityInfo || $activityInfo['start_time'] > time()) {
            // 没有可用的活动信息 || 门店活动尚未开始
            return [];
        }
        // 活动过期, 不用验证, 因为这种情况, 活动信息就获取不到
        // 用户身份
        $isMember = $this->userIdentity();
        if ($type == 'info' && $activityInfo['applicable_crowd'] != 1 && $activityInfo['applicable_crowd'] != $isMember) {
            // 活动受众人群和用户身份不匹配
            return [];
        }
        // 下单用户身份不用判断, 多人点餐, 无法判断谁最后一个下单

        $week = explode(',', $activityInfo['week']);
        // 今天是否可抽奖
        if (!in_array(date('w'), $week)) {
            return [];
        }
        return $activityInfo;
    }

    /**
     * 判断用户身份
     * @return int
     */
    private function userIdentity()
    {
        $openid         = session('users.openid');
        $wapiApi        = new Wapi(config('domain.welife_url'));
        $weLifeUserInfo = $wapiApi->getUserInfo($openid);
        if (!empty($weLifeUserInfo['grade']) && in_array($weLifeUserInfo['grade'], config('grade'))) {
            // VIP
            return 2;
        } else {
            // 普通用户
            return 3;
        }
    }

    /**
     * 下单处理
     * @param int 门店ID
     * @param int 台位ID
     * @param array 订单菜品列表
     * @param string 订单号
     * @return void
     */
    public function order($storeId, $tableId, $foodList, $orderNo)
    {
        $redis           = new Redis();
        $activityInfo    = $this->getInfo($storeId, 'order');
        $tableOrderNoKey = config('cache_keys.table_order_no') . ":{$storeId}:{$tableId}";
        if (!$activityInfo) {
            // 清掉台位历史订单
            $redis->del($tableOrderNoKey);
            return false;
        }

        $unionid = session('users.unionid');
        // 台位订单号存起来, 后面的覆盖前面的, 不用担心出错, 只有加菜的时候才会用到
        $redis->set($tableOrderNoKey, $orderNo, get_future_time());
        // 以订单号为key, 把订单金额存入Redis
        $orderNoKey = config('cache_keys.order_no') . ":{$orderNo}";
        $orderInfo  = $redis->get($orderNoKey);
        // 以用户unionid为key
        $orderNoUnionid = config('cache_keys.order_no_unionid') . ":{$unionid}";
        if (empty($orderInfo['order_amount'])) {
            // 首次下单, 订单金额为0
            $orderInfo['order_amount'] = 0;
            $orderNoUnionidInfo        = $redis->get($orderNoUnionid);
            if (!empty($orderNoUnionidInfo['order_no']) && $orderNoUnionidInfo['order_no'] != $orderNo) {
                // 清掉历史订单(该用户本次订单顶替上次订单,不管上次订单是否可以抽奖,是否抽过奖)
                $redis->del($orderNoUnionid);
            }
        }
        foreach ($foodList as $food_code => $food) {
            // 总价统一按原价计算
            $orderInfo['order_amount'] += $food['food_number'] * $food['food_price'];
        }

        // 活动存活时间
        $survivalTime = $activityInfo['end_time'] - time();

        // 用户身份
        $isMember = $this->userIdentity();

        try {
            // 存储订单抽奖用户的时候需要身份验证 受众人群: 1-所有人群,2-vip,3-普通用户
            if ($activityInfo['applicable_crowd'] != 1 && $activityInfo['applicable_crowd'] != $isMember) {
                throw new Exception("活动受众人群和用户身份不匹配");
            }

            if (empty($orderInfo['draw_user']) && $orderInfo['order_amount'] >= $activityInfo['order_amount']) {
                // 如果之前没有抽奖用户 && 订单金额满足抽奖条件
                // 第一次碰见100元(活动订单金额)的用户有抽奖资格
                // 该订单抽奖用户
                $orderInfo['draw_user'] = $unionid;
                $userInfo               = $redis->get(config('cache_keys.user_info') . ":" . session('users.openid'));
                if (empty($userInfo['wechat_nickname'])) {
                    $orderInfo['draw_user_nickname'] = '您的小伙伴';
                } else {
                    $orderInfo['draw_user_nickname'] = $userInfo['wechat_nickname'];
                }
                $redis->set(
                    $orderNoUnionid,
                    ['order_no' => $orderNo, 'activity_id' => $activityInfo['id']],
                    $survivalTime
                );
            }
        } catch (Exception $e) {
            \think\Log::record("门店code: {$storeId}, 用户: {$unionid}" . $e->getMessage());
        }
        // 存储到活动实效
        $redis->set($orderNoKey, $orderInfo, $survivalTime);
    }

    /**
     * 获取抽奖次数
     * @param $unionid 用户unionid
     * @return int 抽奖次数
     */
    public function getDrawNum($unionid)
    {
        // 不用再验证活动的可用性, 抽奖资格给了他, 只要没抽, 就一直存在
        // 除非后一个订单覆盖前一个订单 || 活动过期
        $redis          = new Redis();
        $orderNoUnionid = config('cache_keys.order_no_unionid') . ":{$unionid}";
        // 存在就给1次抽奖机会, 不存在就不给
        return $redis->get($orderNoUnionid);
    }
}
