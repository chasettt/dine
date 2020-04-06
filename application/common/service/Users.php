<?php
namespace app\common\service;

use lib\Redis;

/**
 * 获取用户信息
 * Class Users
 * @package app\common\service
 */
class Users
{
    public function getUserInfo($openid = '')
    {
        $field    = 'wechat_id,wechat_nickname,wechat_openid,wechat_unionid,wechat_headimgurl,create_time';
        $userInfo = model('common/users')->getUserInfo(['wechat_openid' => $openid], $field);

        Redis::getInstance()->set(
            config('cache_keys.user_info') . ":{$openid}",
            $userInfo,
            config('cache_keys.user_cache_time')
        );

        return $userInfo;
    }
}