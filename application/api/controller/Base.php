<?php

namespace app\api\controller;

use lib\Redis;
use think\Facade\Request;
use think\Facade\Log;

/**
 * Class Common
 * @package app\api\controller
 */
class Base extends \think\Controller
{
    protected $auth = false;

    protected $openid  = null;
    protected $unionid = null;
    protected $brandid = null;

    protected static $redis = null;

    const DISH_TYPE  = 1;
    const COMBO_TYPE = 2;
    const SPECS_TYPE = 3;

    /**
     * 初始化
     */
    public function initialize()
    {
        /**
         * 授权认证
         */
        if (true === $this->auth) {
            $token = input('get.token', '');

            if (!$token) {
                $this->result([], -1, '授权失败');
            }

            $login = $this->getRedis()->get(config('cache_keys.oauth_token') . ":{$token}");

            if (false == $login) {
                $this->result([], -1, '授权失败');
            }

            $this->openid  = $login['openid'];
            $this->unionid = $login['unionid'];
            $this->brandid = $login['brand_id'];
        }
    }

    /**
     * 延迟台位
     */
    public function delay()
    {
        // 延长台位时间
        if (input('?post.table_id') && input('?post.store_code')) {
            $storeCode = input('post.store_code', 0, 'int');
            $tableId   = input('post.table_id', '', 'string');
            $cacheName = config('cache_keys.table_shopping_cart') . ":{$storeCode}:{$tableId}";

            $cartList = $this->getRedis()->get($cacheName);

            if (false !== $cartList) {
                // 20190529 注释掉 疑似写错了
                //                $cartList[] = $this->openid;
                if (true != in_array($this->openid, $cartList) && $this->openid != null) {
                    $cartList[] = $this->openid;
                    $this->getRedis()->set($cacheName, $cartList, config('cache_keys.table_shopping_cache_time'));
                }
            }
        }
    }

    /**
     * redis
     * @return Redis|null
     */
    public function getRedis()
    {
        return Redis::getInstance();
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
//        header('Access-Control-Allow-Origin: *');
//        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
//        header('Access-Control-Allow-Methods: GET, POST');

        return json(['code' => $code, 'msg' => $message, 'data' => $data]);
    }

    /**
     * 请求失败
     * @param $errCode
     * @param $errMsg
     */
    public function failed($errCode, $errMsg)
    {
        $request = Request::instance();

        Log::error(json_encode([
            'code' => 0,
            'msg'  => $request->module() . ' request error',
            'data' => [
                'error' => [
                    'code' => $errCode,
                    'msg'  => $errMsg,
                ],

                'request' => [
                    'request_method' => $request->method(),
                    'request_url'    => $request->url(),
                    'request_param'  => $request->param(),
                    'request_time'   => date('Y-m-d H:i:s'),
                    'request_ip'     => $request->ip(),
                    'is_ajax'        => $request->isAjax(),
                ],
            ],
        ]));
    }
}
