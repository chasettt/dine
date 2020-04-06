<?php
namespace app\api\controller;

/**
 * 门店
 * Class Store
 * @package app\api\controller
 */
class Store extends Base
{

    /**
     * 获取门店列表
     */
    public function list()
    {
        $storeCode = input('post.store_code', 0, 'int');
        $condition = [];
        if ($storeCode > 0) {
            $condition['store_code'] = $storeCode;
        }
        $storeList = model('common/store')->getStoreList($condition, 'store_code,store_name,store_state');

        return $this->returnMsg(200, 'success', $storeList);
    }

    /**
     * 门店详情
     * @return array
     */
    public function info()
    {
        $storeId = input('post.store_code', 0, 'int');
        if ($storeId) {
            $cacheName = config('cache_keys.store_info');
            $storeInfo = $this->getRedis()->get($cacheName . ":{$storeId}");

            return $this->returnMsg(200, 'success', $storeInfo);
        }

        return $this->returnMsg(200, 'success', []);
    }
}
