<?php

namespace app\api\controller;

/**
 * 用户信息
 * Class Users
 * @package app\api\controller
 */
class Users extends Base
{
    protected $auth = true;

    /**
     * todo 加参数，区分是否查询甄选优惠券信息和会员等级
     * 用户信息
     * @return array
     */
    public function info()
    {
        $result = [
            'wechat_openid'     => $this->openid,
            'wechat_unionid'    => $this->unionid,
            'wechat_headimgurl' => '',
            'nick_name'         => '',
            'is_member'         => false,
            'grade_number'      => 1,
            'name'              => '',
            'phone'             => '',
            'coupon_details'    => [
                'welife' => [],
                'zhenxuan' => []
            ],
        ];

        $userInfo = $this->getRedis()->get(config('cache_keys.user_info') . ":{$this->openid}");

        if (false === $userInfo) {
            $userInfo = model('common/users', 'service')->getUserInfo($this->openid);
        }

        if (!empty($userInfo)) {
            $result['nick_name'] = $userInfo['wechat_nickname'];
            $result['wechat_headimgurl'] = $userInfo['wechat_headimgurl'];
        }

        return $this->returnMsg(200, 'success', $result);
    }

    public function infoSimple()
    {
        $result = [
            'wechat_openid'     => $this->openid,
            'wechat_unionid'    => $this->unionid,
            'wechat_headimgurl' => '',
            'nick_name'         => '',
            'is_member'         => false,
            'grade_number'      => 1,
            'name'              => '',
            'phone'             => '',
            'coupon_details'    => [
                'welife' => [],
                'zhenxuan' => []
            ],
        ];

        $userInfo = model('common/users', 'service')->getUserInfo($this->openid);

        if (!empty($userInfo)) {
            $result['nick_name'] = $userInfo['wechat_nickname'];
            $result['wechat_headimgurl'] = $userInfo['wechat_headimgurl'];
        }

        return $this->returnMsg(200, 'success', $result);
    }
}