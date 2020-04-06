<?php
namespace app\api\controller;

/**
 * 收藏
 * 已优化 日志记录
 * Class Favorite
 * @package app\api\controller
 */
class Favorite extends Base
{
    protected $auth = false;

    /**
     * 大家都喜欢 显示菜品评分最高的10个菜
     * @return array
     */
    public function dishScoreList()
    {
        $storeId = input('post.store_code', 0, 'int');

        if (empty($storeId)) {
            return $this->returnMsg(0, '参数错误');
        }

        $dishList = $this->getRedis()->get(config('cache_keys.ranking_dish_score') . ":{$storeId}");

        if (empty($dishList)) {
            return $this->returnMsg(200, 'success', []);
        }

        arsort($dishList);
        $dishIds = array_keys($dishList);

        return $this->returnMsg(200, 'success', $dishIds);
    }

    /**
     * 摇一摇 展示菜品销量前20中的6个 这里找20个返回 前端取6个
     * @return array
     */
    public function dishSaleTop()
    {
        $storeId = input('post.store_code', 0, 'int');

        if (empty($storeId)) {
            return $this->returnMsg(0, '参数错误');
        }

        $dishList = $this->getRedis()->get(config('cache_keys.ranking_dish_sales') . ":{$storeId}");
        $dishFilterList = $this->getRedis()->get(config('cache_keys.score_dish_filter'));

        $dishFilterList = empty($dishFilterList) ? [] : $dishFilterList;

        if (empty($dishList)) {
            return $this->returnMsg(200, 'success', []);
        }

        arsort($dishList);
        $dishList = array_diff(array_keys($dishList), $dishFilterList);
        $dishListTopTwenty = array_slice($dishList, 0, 20);
        shuffle($dishListTopTwenty);

        return $this->returnMsg(200, 'success', $dishListTopTwenty);
    }
}