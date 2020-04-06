<?php
namespace app\api\controller;

use lib\Redis;

/**
 * 分类列表
 * Class Classes
 * @package app\api\controller
 */
class Classes extends Base
{
    protected $oauth = true;

    /**
     * 获取菜品分类
     * @return mixed
     */
    public function getFoodList()
    {
        // 门店ID
        $storeCode   = input('post.store_code', 0, 'int');
        $tableId     = input('post.table_id', 0, 'int');
        $environment = input('post.environment', '', 'string');

        if (! $storeCode) {
            return $this->returnMsg(0, '参数错误');
        }

        // 菜品列表
        $menuInfo = $this->getRedis()->get(config('cache_keys.store_menu') . ":{$storeCode}");

        if (false == $menuInfo or empty($menuInfo)) {
            return $this->returnMsg(200, 'success', []);
        }

        // 用户卡号 2019.7.15注释，已经没有按卡号店长推荐了
//        $cno = $this->getRedis()->get(config('cache_keys.user_cno') . ":{$this->openid}");

        // 估清
        $estimatesArr = $this->getRedis()->get(config('cache_keys.choice_estimates') . ":{$storeCode}");

        // 台位信息
        if (! empty($tableId)) {
            $tableInfo = $this->getRedis()->get(config('cache_keys.table_info') . ":{$storeCode}:{$tableId}");
        }

        foreach ($menuInfo as $keys => &$info) {
            if (isset($info['food_list']) and ! empty($info['food_list'])) {

                $foodList = $info['food_list'];

                foreach ($foodList as &$food) {
                    $food['soldout'] = false;

                    if (false !== $estimatesArr and isset($estimatesArr[$food['food_code']]) and floor($estimatesArr[$food['food_code']]) <= 0) {
                        $food['soldout'] = true;
                    }

                    // 如果是自选套餐，循环自选套餐中的主菜，判断是否售罄，如果售罄，整个套餐售罄
                    if ($food['is_combo'] == 1 && $food['is_multiple_combo'] && isset($food['combo_menu_detail']) && !empty($food['combo_menu_detail'])) {
                        // 判断是否售罄
                        foreach ($food['combo_menu_detail'] as $key => &$combo) {
                            if (!empty($combo['detail']) && is_array($combo['detail'])) {
                                foreach ($combo['detail'] as &$detailItem) {
                                    $detailItem['soldout'] = false;
                                    if (false !== $estimatesArr and isset($estimatesArr[$detailItem['dish_code']]) and floor($estimatesArr[$detailItem['dish_code']]) <= 0) {
                                        if ($key === 0) {
                                            $food['soldout'] = true;
                                        }
                                        $detailItem['soldout'] = true;
                                    }
                                }
                            }
                        }
                    }

                    // 如果是多规格菜 判断多规格中的每个菜是否售罄
                    if ($food['food_type'] == self::SPECS_TYPE) {
                        foreach ($food['food_specs'] as &$specItem) {
                            $specItem['soldout'] = false;
                            if (false !== $estimatesArr and isset($estimatesArr[$specItem['food_code']]) and floor($estimatesArr[$specItem['food_code']]) <= 0) {
                                $specItem['soldout'] = true;
                            }
                        }
                    }

                    if (! empty($tableInfo) and $tableInfo['type_id'] == config('room.type')) {
                        $food['food_price']        = $food['food_room_price'];
                        $food['food_member_price'] = $food['food_room_member_price'];
                        unset($food['food_room_price']);
                        unset($food['food_room_member_price']);
                    }
                }

                $info['food_list'] = $foodList;
            } else {
                unset($menuInfo[$keys]);
            }
        }

        return $this->returnMsg(200, 'success', $menuInfo);
    }

    public function getlist()
    {
        $storeCode   = input('post.store_code', 0, 'int');

        if (! $storeCode) {
            return $this->returnMsg(0, '参数错误');
        }

        // 菜品列表
        $classInfo = $this->getRedis()->get(config('cache_keys.store_menu_classes') . ":{$storeCode}");

        return $this->returnMsg(200, 'success', $classInfo);
    }
}
