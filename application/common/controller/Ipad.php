<?php

namespace app\common\controller;

use sdk\Redis;
use think\Controller;
use think\Db;

class Ipad extends Controller
{
    protected $auth      = true;
    protected $storeInfo = [];

    protected static $redis = null;

    public function _initialize()
    {
        // 授权认证
        if (true === $this->auth) {

            $storeId = input('store_id', 0, 'int');
            $brandId = 1;

            // 门店信息
            if (! empty($storeId)) {
                // 门店信息
                $this->storeInfo = $this->getRedis()->get(config('cache_keys.store_info') . ":{$storeId}");
                if (empty($this->storeInfo['brand_id'])) {
                    $this->redirect(url('/ipad/fail'));
                }

                $brandId = $this->storeInfo['brand_id'];
            }

            $selfUrl = get_current_url();
            $oauth   = config('domain.web_url') . config('address.oauth') . urlencode($selfUrl) . '&brand_id=' . $brandId;

            // token
            $token = input('?get.token') ? input('get.token') : cookie(config('cache_keys.oauth_token'));
            if (empty($token))
                $this->redirect($oauth);

            // user
            $userInfo = model('common/oauth', 'service')->check($token, $brandId);
            if (empty($userInfo))
                $this->redirect($oauth);

            session('iPad_user', $userInfo);
            cookie(config('cache_keys.oauth_token'), $token, get_future_time());
        }
    }

    public function getRedis()
    {
        if (empty(self::$redis)) {
            self::$redis = new Redis();
        }

        return self::$redis;
    }

    /**
     * 消息返回
     * @param int $code
     * @param string $message
     * @param array $data
     * @return array
     */
    public function returnMsg($code = 0, $message = 'error', $data = [])
    {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        header('Access-Control-Allow-Methods: GET, POST');

        return ['code' => $code, 'msg' => $message, 'data' => $data];
    }

    /**
     * 空方法
     */
    public function _empty()
    {
        abort(404, '页面不存在');
    }
}