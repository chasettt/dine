<?php
namespace app\api\controller;

use lib\Redis;
use think\Facade\Cookie;

/**
 * 登录
 * 日志已记录
 * Class Login
 * @package app\api\controller
 */
class Login extends \think\Controller
{
    public function indexOp()
    {
        $url     = input('get.returnUrl', '');
        $brandId = input('get.brand_id', 1);

        if ('' == $url) {
            $this->redirect(url('/online/fail'));
        }

        Cookie::set('_return_url', $url);
        Cookie::set('_brand_id', $brandId);

        $oauth = config('domain.passport_url') . '/wechat/login?appid=' . config('address.oauth_id') . '&brand_id=' . $brandId;

        return redirect($oauth);
    }

    /**
     * 回调
     */
    public function callbackOp()
    {
        $code      = input('get.code', '');
        $scope     = input('get.scope', 'base');
        $brandId   = Cookie::get('_brand_id');
        $returnUrl = Cookie::get('_return_url');

        // 授权认证Uri
        $oauthUrl = config('domain.passport_url') . '/wechat/login?appid=' . config('address.oauth_id') . "&brand_id=" . $brandId;
        if ('' == $code) {
            $this->deleteCookie();
            $this->redirect(url('/online/fail'));
        }

        // 授权认证
        $redis = Redis::getInstance();

            $memberInfo = model('common/users')->getUserInfo(['wechat_openid' => $oauth['openid']], 'wechat_id,wechat_openid,wechat_unionid,wechat_nickname,wechat_headimgurl');
            if (! empty($memberInfo)) {
                $token = strtoupper(md5($memberInfo['wechat_openid'] . time()));
                $redis->set(
                    config('cache_keys.oauth_token') . ":{$token}",
                    [
                        'openid'   => $memberInfo['wechat_openid'],
                        'unionid'  => $memberInfo['wechat_unionid'],
                        'brand_id' => $brandId,
                        'nickname' => $memberInfo['wechat_nickname'],
                    ],
                    get_future_time()
                );

                // 登录时就把信息存入缓存，后面就不用再查表了
                $redis->set(
                    config('cache_keys.user_info') . ":{$memberInfo['wechat_openid']}",
                    $memberInfo,
                    config('cache_keys.user_cache_time')
                );

                // 跳转
                $jumpUrl = $this->_getReturnUrl(
                    $returnUrl,
                    ['token' => $token]
                );

                // 清空cookie
                $this->deleteCookie();

                return redirect($jumpUrl);
            }

            return redirect($oauthUrl . '&scope=userinfo');
    }

    /**
     * 清空cookie
     */
    private function deleteCookie()
    {
        Cookie::delete('_brand_id');
        Cookie::delete('_return_url');
    }

    /**
     * 拼接回调地址
     * @param $url
     * @param $param
     * @return string
     */
    private function _getReturnUrl($url, $param)
    {
        $query      = parse_url($url, PHP_URL_QUERY);
        $buildQuery = http_build_query($param);

        if (! is_null($query)) {
            $url .= '&' . $buildQuery;
        } else {
            $url .= '?' . $buildQuery;
        }

        return $url;

    }

    /**
     * 登录失败
     * @return \think\response\Json
     */
    public function errorOp()
    {
        $code = input('get.code', -1, 'int');
        $msg  = input('get.msg', '授权失败', 'urldecode');

        return json(['code' => $code, 'msg' => $msg, 'data' => []]);
    }
}
