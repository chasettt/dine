<?php

namespace app\api\controller;

class Activity extends Base
{
    protected $auth = false;

    /**
     * 获取门店活动信息
     * return 活动信息
     */
    public function getInfo()
    {
        return $this->returnMsg(200, 'success', []);
    }

    /**
     * 加菜时获取之前订单信息
     * return 历史订单信息
     */
    public function getOrderIn()
    {
        $storeId = input('post.store_id', 0, 'int');
        $tableId = input('post.table_id', 0, 'int');
        if (!$storeId || !$tableId) {
            return $this->returnMsg(0, '参数错误');
        }
        // 获取 门店:台位 的订单号
        $orderNo = $this->getRedis()->get(config('cache_keys.table_order_no') . ":{$storeId}:{$tableId}");
        if (!$orderNo) {
            return $this->returnMsg(0, '该台位无订单号');
        }
        $orderInfo = $this->getRedis()->get(config('cache_keys.order_no') . ":{$orderNo}");
        // 不够抽奖资格 - 初始状态
        $orderInfo['draw'] = 0;
        if (!empty($orderInfo['draw_user']) && !empty($orderInfo['draw_user_nickname'])) {
            $orderNoUnionid = config('cache_keys.order_no_unionid') . ":{$orderInfo['draw_user']}";
            // 存在draw_user && order_no_unionid为空 => 已抽奖
            // 存在draw_user && order_no_unionid不为空 => 未抽奖
            if ($this->getRedis()->get($orderNoUnionid)) {
                // 未抽奖
                $orderInfo['draw'] = 1;
            } else {
                // 已抽奖
                $orderInfo['draw'] = 2;
            }
            $orderInfo['draw_user_nickname'] = mb_substr($orderInfo['draw_user_nickname'], 0, 4, 'utf8');
        }
        return $this->returnMsg(200, 'success', $orderInfo);
    }

    /**
     * 检查抽奖资格
     *
     */
    public function check()
    {
        $post = file_get_contents('php://input');
        if (!$post) {
            return $this->returnMsg(0, '参数错误');
        }
        $post = json_decode($post, true);
        if (empty($post['unionid'])) {
            return $this->returnMsg(0, '参数错误');
        }
        $uid = $post['unionid'];

        \think\Log::record('======= 请求参数 =======');
        \think\Log::record($this->request->param());

        $drawInfo = model('common/activity', 'service')->getDrawNum($uid);

        if (empty($drawInfo['activity_id']) || empty($drawInfo['order_no'])) {
            return $this->returnMsg(0, '暂无抽奖机会');
        }

        return $this->returnMsg(200, 'success', [
            'numbers'  => 1,
            'order_id' => $drawInfo['order_no'],
            'params'   => ['activity_id' => $drawInfo['activity_id']],
        ]);
    }

    /**
     * 用户抽奖记录
     *
     */
    public function record()
    {
        $post = file_get_contents('php://input');
        if (!$post) {
            return $this->returnMsg(0, '参数错误');
        }
        $post = json_decode($post, true);
        if (empty($post['unionid']) || empty($post['order_id']) || empty($post['prize']) || empty($post['params'])) {
            return $this->returnMsg(0, '参数错误');
        }

        $uid     = $post['unionid'];
        $orderId = $post['order_id'];
        $prize   = $post['prize'];
        $params  = $post['params'];
        \think\Log::record('======= 请求参数 =======');
        \think\Log::record($this->request->param());

        $winTime    = empty($prize['win_time']) ? 0 : $prize['win_time'];
        $prizeName  = empty($prize['prize_name']) ? '' : $prize['prize_name'];
        $prizeId    = empty($prize['prize_id']) ? 0 : $prize['prize_id'];
        $type       = empty($prize['type']) ? 0 : $prize['type'];
        $activityId = empty($params['activity_id']) ? 0 : $params['activity_id'];

        $orderNoUnionid = config('cache_keys.order_no_unionid') . ":{$uid}";

        if ($this->getRedis()->get($orderNoUnionid)) {
            $data = [
                'activity_id' => $activityId,
                'unionid'     => $uid,
                'win_time'    => $winTime,
                'prize_name'  => $prizeName,
                'prize_id'    => $prizeId,
                'type'        => $type,
                'order_id'    => $orderId,
                'inputtime'   => time(),
            ];

            $record = \think\Db::table('online_activity_draw_record');
            $bool   = $record->insertGetId($data);

            if (!$bool) {
                \think\Log::record('======= 数据写入失败 =======');
                return $this->returnMsg(0, '写入数据失败');
            }

            // 剔除抽奖资格
            $this->getRedis()->del($orderNoUnionid);
            return $this->returnMsg(200, 'success');
        }
        return $this->returnMsg(0, '暂无抽奖机会');
    }
}
