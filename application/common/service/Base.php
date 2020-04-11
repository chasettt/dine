<?php
/**
 * Created by PhpStorm.
 * User: teng
 * Date: 2020/4/6
 * Time: 6:36 PM
 */

namespace app\common\service;

use lib\Redis;
use \Firebase\JWT\JWT;

class Base
{
    protected $openid = '';
    protected $unionid = '';
    protected $brandid = '';

    public function oauth($token)
    {
        $login = $this->getRedis()->get(config('cache_keys.oauth_token') . ":{$token}");

        if (false == $login) {
            return false;
        }

        $this->checkJwtToken($token);
        $this->openid  = $login['openid'];
        $this->unionid = $login['unionid'];
        $this->brandid = $login['brand_id'];
    }

    public function checkJwtToken($token)
    {
        $key  = config('jwt.key');
        $info = JWT::decode($token, $key,['HS256']);
        dump($info);
        return $info;
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
    public function returnMsg($code = 0, $message = 'error', $data = [], $type = '')
    {
        return ['code' => $code, 'msg' => $message, 'data' => $data, 'type' => $type];
    }
}