<?php
namespace app\api\controller;

class Multicombo extends Base
{
    protected $auth = false;

    public function getComboInfoOp()
    {
        $foodCode = input('post.dish_code', 0, 'int');
        $storeId  = input('post.store_code', 0, 'int');

        if ( !$foodCode or !$storeId) {
            return $this->returnMsg(0, '参数错误');
        }

        $comboInfo = $this->getRedis()->get(config('cache_keys.store_menu_dish').":{$storeId}:{$foodCode}");
        if (false == $comboInfo) {
            return $this->returnMsg(0, '套餐不存在');
        }

        //过滤套餐中的单菜，是否售罄
        $comboInfo = $this->filterCombo($comboInfo, $storeId);
        return $this->returnMsg(200, 'success', $comboInfo);
    }

    protected function filterCombo($comboInfo, $storeId)
    {
        // 估清
        $estimatesArr = $this->getRedis()->get(config('cache_keys.choice_estimates').":{$storeId}");
        //判断是否下架
        $storeFoodList = $this->getRedis()->get(config('cache_keys.store_menu_dish_code').":{$storeId}");

        foreach ($comboInfo['combo_menu_detail'] as &$combo) {
            foreach ($combo['detail'] as &$item) {
                $item['soldout'] = false;
                //估清
                if (false !== $estimatesArr and true === array_key_exists($item['dish_code'], $estimatesArr)) {
                    if (true === isset($estimatesArr[$item['dish_code']]) &&
                        floor($estimatesArr[$item['dish_code']]) <= 0) {
                        $item['soldout'] = true;
                    }
                }
                //下架
                if (false !== $storeFoodList and true !== in_array($item['dish_code'], $storeFoodList)) {
                    $item['soldout'] = true;
                }
            }
        }

        return $comboInfo;
    }
}