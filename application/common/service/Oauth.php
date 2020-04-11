<?php

namespace app\common\service;

use lib\Redis;

class Oauth
{
    public function check($token, $brand, $isset = false)
    {
        if (!$token) {
            return false;
        }

        // 验证token是否存在
        $oath = Redis::getInstance()->get(config('cache_keys.oauth_token') . ":{$token}");

        if (empty($oath) or $oath['brand_id'] != $brand) {
            return false;
        }

        if (true === $isset) {
            session('users.openid', $oath['openid']);
            session('users.unionid', $oath['unionid']);
            session('users.brand_id', $oath['brand_id']);
            session('users.nickname', $oath['nickname']);
        }

        return $oath;
    }
}
