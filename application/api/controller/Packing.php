<?php
namespace app\api\controller;

/**
 * 包装费
 * Class Order
 * @package app\api\controller
 */
class Packing extends Base
{
    protected $auth = true;

    public function info()
    {
        $storeId    = input('post.store_id');
        $packingFee = $this->getRedis()->get(config('cache_keys.packing_fee') . ":{$storeId}");

        return $this->returnMsg(200, 'success', is_null($packingFee) ? [] : $packingFee);
    }
}