<?php
namespace app\common\service;

use sdk\Redis;

class Food
{
    /**
     * @todo 废弃
     * 更新菜品缓存列表
     * @param string $storeId
     * @return bool
     */
    public function updateFoodCache($storeId = '')
    {
        return false;
        if (! $storeId) {
            return false;
        }
    
        $foodModel    = model('common/food');
        $classesModel = model('common/classes');
    
        $classList = $classesModel->getClassesList([
            'store_code'=>$storeId,
            'classes_state' => 1
        ], 'classes_code,classes_name');
    
        $foodList = $foodModel->getFoodList([
            'store_code'=>$storeId,
            'food_state' => 1
        ], 'classes_code,food_code,food_name,food_unit,food_weigh,food_weigh_description,food_price,food_member_price,food_modifiers,is_essential,is_specialty,is_newest,is_combo');
    
    
        if (! empty($classList) and ! empty($foodList)) {
            $redis = new Redis();
        
            // 增加额外分类
            $extraClasses = config('classes.extra');
        
            foreach ($classList as $keys => &$class) {
                foreach ($foodList as &$food) {
                    if ($class['classes_code'] == $food['classes_code']) {
                        if (1 == $food['is_combo']) {
                            $foodDetail = $redis->get(config('cache_keys.combo_info').":{$food['food_code']}");
                            $food['food_image'] = (is_null($foodDetail['combo_image_1']))?'':$foodDetail['combo_image_1'];
                            $food['food_image_hd'] = (is_null($foodDetail['combo_image_1_hd']))?'':$foodDetail['combo_image_1_hd'];
                            $food['food_attrs'] = [];
                            $food['food_description'] = (is_null($foodDetail['combo_description']))?'':$foodDetail['combo_description'];
                        } else {
                            $foodDetail = $redis->get(config('cache_keys.dish_info').":{$food['food_code']}");
                            $food['food_image'] = (is_null($foodDetail['dish_image_1']))?'':$foodDetail['dish_image_1'];
                            $food['food_image_hd'] = (is_null($foodDetail['dish_image_1_hd']))?'':$foodDetail['dish_image_1_hd'];
                            $food['food_attrs'] = (is_null($foodDetail['dish_attrs']))?[]:$foodDetail['dish_attrs'];
                            $food['food_description'] = (is_null($foodDetail['dish_description']))?'':$foodDetail['dish_description'];
                        }
                    
                        if (! empty($food['food_attrs'])) {
                            $attr = config('classes.attr');
                        
                            foreach ($food['food_attrs'] as $attrInfo) {
                                if (isset($attr[$attrInfo['id']])) {
                                    $extraClasses[$attr[$attrInfo['id']]]['food_list'][] = $food;
                                }
                            }
                        }
                    
                        // 餐单菜品详情
                        $redis->set(
                            config('cache_keys.store_menu_dish').":{$storeId}:{$food['food_code']}",
                            $food,
                            config('cache_keys.store_cache_time')
                        );
                    
                        $class['food_list'][] = $food;
                    }
                }
            }
        
            $menuInfo = array_merge($classList, $extraClasses);
        
            if (! empty($menuInfo)) {
                $redis->set(
                    config('cache_keys.store_menu').":{$storeId}",
                    $menuInfo,
                    config('cache_keys.store_cache_time')
                );
            }
            return true;
        }
        return false;
    }

    /**
     * 基础数据 菜品缓存
     * @todo 废弃
     * @return bool
     */
    public function updateBaseFoodCache()
    {
        exit;
        $baseStore = model('common/base', 'service');
        $cacheName = config('redis_cache.base_food');
        $foodList = $baseStore->getFoodList();

        foreach ($foodList as $item) {
            cache($cacheName.$item['dish_code'], $item);
        }

        return true;
    }

    /**
     * 同步菜品图片
     * @return bool
     */
    public function syncFoodCache()
    {
        $collectList = model('collect')->field('store_code ,store_state , dish_value')->select();
        $foodList = model('common/base', 'service')->getFoodList();
        $foodList = array_column($foodList,NULL,'dish_code');
        $cacheName = config('redis_cache.base_food');
        foreach (toArray($collectList) as $item){
             if($item['store_state'] and $item['dish_value'] and $item['dish_value'] = explode('|',$item['dish_value'])){
                  $begin_num = count($item['dish_value']);
                  foreach ($item['dish_value'] as $k=>$v){
                       if(isset($foodList[$v]) && $foodList[$v]['dish_image_1'] && $foodList[$v]['dish_image_1_hd'] && $foodList[$v]['dish_image_2']){
                           cache($cacheName.$v, $foodList[$v]);
                           unset($item['dish_value'][$k]);
                       }
                  }

                 if($begin_num != count($item['dish_value'])) {
                     model('common/food', 'service')->updateFoodCache($item['store_code']);
                     $list[] = ['store_code' => $item['store_code'], 'dish_value' => implode('|', $item['dish_value'])];
                 }
             }
        }
        if(isset($list)) {
            model('collect')->saveAll($list);
        }
        return true;
    }
    /**
     * 获取菜品列表
     * @param $storeCode
     * @param $classesCode
     * @return mixed
     */
    public function getFoodList($storeCode, $classesCode)
    {
        return model('common/food')->getFoodList([
            'store_code' => $storeCode,
            'classes_code' => $classesCode,
            'food_state' => 1
        ],
            'food_code,food_name,food_unit,food_price,food_member_price,is_essential,is_specialty,is_newest,food_modifiers,food_image,food_image_hd,food_weigh,food_weigh_description,is_combo'
        );
    }

    /**
     * 菜品排序
     * @param $storeCode
     * @param array $foodList
     * @return array
     */
    public function FoodListSort($storeCode, $foodList = [])
    {
        $list = model('common/foodSort')->getSortInfo([
            'store_code' => $storeCode,
            'sort_type' => 'food'
        ],'sort_list');

        if (is_null($list)) {
            return $foodList;
        }

        //$list = $list->toArray();
        $sortArr = unserialize($list['sort_list']);

        $not = [];
        $yes = [];

        foreach ($foodList as $item) {
            $keys = array_search($item['food_code'], $sortArr);

            if (false === $keys) {
                $not[] = $item;
            } else {
                $yes[$keys] = $item;
            }
        }
        ksort($yes);
        $result = array_merge($yes, $not);

        return $result;
    }

    /**
     * 单菜品缓存详情
     * @param null $storeCode
     * @param null $foodCode
     * @return bool
     */
    public function getCacheFoodInfo($storeCode = null, $foodCode = null)
    {
        if (null == $storeCode or null == $foodCode) {
            return false;
        }

        return cache(config('redis_cache.store_food_details') . $storeCode . ':' . $foodCode);
    }

    /**
     * 菜品估清检测
     * @param $storeId 门店code
     * @param $foodList 需要检测的菜品列表
     * @param $type 传入菜品列表的格式
     * true => 普通的菜品列表, 加入估清标识, 如：$food['soldout'] = true|false;
     * false => 估清的菜品要删除, 传入的只有菜品code, 如：[10511, 10470]
     * @return array 检测完的菜品
     */
    public function estimates($storeId, $foodList, $type = true)
    {
        // 估清
        $redis = new Redis();
        $estimatesArr = $redis->get(config('cache_keys.choice_estimates').":{$storeId}");
        if(!$estimatesArr){
            // 没有估清的菜品,直接返回原菜品列表
            return $foodList;
        }
        foreach($foodList as $key=>$food){
            if($type){
                $foodCode = $food['food_code'];
                $foodList[$foodCode]['soldout'] = false;
            }else{
                $foodCode = $food;
            }
            if(array_key_exists($foodCode, $estimatesArr) && floor($estimatesArr[$foodCode]) <= 0){
                // 牛大骨之类的小于1斤,就算估清,所以要向下取整
                // foodCode在估清列表 && 库存 <= 0, 则代表估清
                if($type){
                    $foodList[$key]['soldout'] = true;
                }else{
                    unset($foodList[$key]);
                }
            }
        }
        return $foodList;
    }

    /**
     * 获取额外分类菜品数据结构
     * 店长推荐 | 十大招牌 | 新品尝鲜
     * @param $storeId
     * @param $foodList
     * @param $type
     * @param null $number
     * @return array|bool
     */
    public function getExtraClassFood($storeId, $foodList, $type, $number = null)
    {
        $foodList = array_column($foodList, null, 'food_code');
        $foodRecommendList = model('common/foodSort')->getSortInfo([
            'store_code' => $storeId,
            'sort_type'  => $type,
        ], 'sort_list, sort');

        if (!empty($foodRecommendList['sort_list'])) {
            // 推荐排序菜品
            $foodRecommendLists = unserialize($foodRecommendList['sort_list']);
            // foodRecommendList(推荐菜品)只取10条, 即便有下架的, 最终不够10条也可以
            if (!is_null($number)) {
                $foodRecommendLists = array_slice($foodRecommendLists, 0, $number);
            }
            // 键值对交换
            $foodRecommendLists = array_flip($foodRecommendLists);
            // 和$foodList取交集, 排除下架菜品
            $tmpList       = array_intersect_key($foodRecommendLists, $foodList);

            $recommendList = [];
            foreach ($tmpList as $food_code => $food) {
                $recommendList[] = $foodList[$food_code];
            }
            return [
                'list' => $recommendList,
                'sort' => $foodRecommendList['sort'],
            ];
        }

        return false;
    }
}
