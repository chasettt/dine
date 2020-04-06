<?php
namespace app\api\controller;

/**
 * 门店排行榜
 * @
 * Class Ranking
 * @package app\api\controller
 */
class Ranking extends Base
{

    protected $auth = true;

    public function list()
    {
        $store_id    = input('param.store_id', 0, 'int');
        $tableId     = input('post.table_id', 0, 'int');
        $environment = input('post.environment', '', 'string');

        if (! $store_id) {
            $this->failed('0', '参数错误');

            return $this->returnMsg(0, '参数错误');
        }

        $salesList = $this->getRedis()->get(config('cache_keys.ranking_store') . ":{$store_id}");

        if (false === $salesList) {
            $this->failed($this->getRedis()->errCode, $this->getRedis()->errMsg);

            return $this->returnMsg(200, 'success');
        }

        $storeInfo = $this->getRedis()->get(config('cache_keys.store_info') . ":{$store_id}");
        if (false === $storeInfo) {
            $this->failed($this->getRedis()->errCode, $this->getRedis()->errMsg);

            return $this->returnMsg(200, 'success');
        }
        $member_rules = $storeInfo['member_rules'];


        //估清
        $estimatesKey  = [];
        $estimatesList = $this->getRedis()->get(config('cache_keys.choice_estimates') . ":{$store_id}");
        if (false !== $estimatesList) {
            $estimatesKey = array_keys($estimatesList);
        }

        //餐单
        $storeMenuList = $this->getRedis()->get(config('cache_keys.store_menu') . ":{$store_id}");
        if (false === $storeMenuList) {
            $this->failed($this->getRedis()->errCode, $this->getRedis()->errMsg);

            return $this->returnMsg(200, 'success');
        }

        // 台位信息
        if (! empty($tableId)) {
            $tableInfo = $this->getRedis()->get(config('cache_keys.table_info') . ":{$store_id}:{$tableId}");
        }

        $foodList = [];//food_code=>info
        if (! empty($storeMenuList)) {
            foreach ($storeMenuList as $item) {
                if (! empty($item['food_list'])) {
                    foreach ($item['food_list'] as &$food) {

                        if (! empty($tableInfo) and $tableInfo['type_id'] == config('room.type')) {
                            $food['food_price']        = $food['food_room_price'];
                            $food['food_member_price'] = $food['food_room_member_price'];
                            unset($food['food_room_price']);
                            unset($food['food_room_member_price']);
                        }

                        $foodList[$food['food_code']] = $food;
                    }
                }
            }
        }
        unset($storeMenuList);
        $foodListKeys = array_keys($foodList);

        $res = [];
        $i   = 0;
        $num = config('ranking_num');
        foreach ($salesList as $key => $item) {
            if ($i >= $num) {
                break;
            }

            $foodCode = $item['food_code'];

            if (in_array($foodCode, $foodListKeys)) {

                $res[$i] = $foodList[$foodCode];

                //是否售罄
                if (in_array($foodCode, $estimatesKey) && floor($estimatesList[$foodCode]) < 1) {
                    $res[$i]['soldout'] = true;
                } else {
                    $res[$i]['soldout'] = false;
                }
                $res[$i]['store_code']   = $item['store_code'];
                $res[$i]['sales_volume'] = $item['sales_volume'];
                $res[$i]['ranking_time'] = $item['ranking_time'];
                $res[$i]['member_rules'] = $member_rules;
                $i++;

            }

        }

        return $this->returnMsg(200, 'success', $res);
    }
}


