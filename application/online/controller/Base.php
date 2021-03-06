<?php
/**
 * Created by PhpStorm.
 * User: teng
 * Date: 2020/4/4
 * Time: 8:49 PM
 */

namespace app\online\controller;

use think\Controller;
use think\Facade\Request;
use lib\Redis;
use app\common\service\Oauth;
use \Firebase\JWT\JWT;

/**
 * Class Base
 * @package app\online\controller
 */
class Base extends Controller
{
    protected $auth      = true;
    protected $storeInfo = [];

    public function initialize()
    {
        $storeId = $this->request->param('store_id');

        // 参数验证
        if (!$storeId) {
            $this->redirect(url('/online/fail'));
        }

        // 门店信息
        $this->storeInfo = $this->getRedis()->get(config('cache_keys.store_info') . ":{$storeId}");

        // 校验门店
        if (empty($this->storeInfo)) {
            $this->redirect(url('/online/fail'));
        }

        // 授权认证
        if (true === $this->auth) {
            $brandId = $this->storeInfo['brand_id'];

            if (!input('?get.token')) {
                $token  = cookie(config('cache_keys.oauth_token'));
                $userId = input('user_id');

                if (!$token) {
                    $token = $this->getToken($userId);
                } else {
                    $oauth = new Oauth();
                    $check = $oauth->check($token, $brandId, true);

                    if (!$check) {
                        $token = $this->getToken($userId);
                    }
                }

                header("token:{$token}");
//                $redirectUrl = Request::server('REQUEST_URI') . '?'
//                    . Request::server('QUERY_STRING') . '&token=' . $token;
//                $this->redirect($redirectUrl);
            } else {
                $token = input('get.token');
            }
            cookie(config('cache_keys.oauth_token'), $token, config('cache_keys.token_cache_time'));

        }
    }


    public function fail($title = '', $description = '', $btn = '')
    {
        $buttons = [
            'scan'   => [
                'class' => 'J-btn-scan',
                'text'  => '扫一扫',
            ],
            'reload' => [
                'class' => 'J-btn-refresh',
                'text'  => '重新获取',
            ],
        ];

        $button = [];
        if ($btn && isset($buttons[$btn])) {
            $button = $buttons[$btn];
        }

        return [
            'title'       => $title,
            'description' => $description,
            'button'      => $button,
        ];
    }

    /**
     * @return Redis|null
     */
    public function getRedis()
    {
        return Redis::getInstance();
    }

    private function getToken($userId)
    {
        $field = 'wechat_id,wechat_openid,wechat_unionid,wechat_nickname,wechat_headimgurl';
        $memberInfo = model('common/users')->getUserInfo(['wechat_id' => $userId], $field);

        if (!empty($memberInfo)) {
//            $token = strtoupper(md5($memberInfo['wechat_openid'] . time()));
            $token = $this->getJwtToken($memberInfo['wechat_openid']);
            $this->getRedis()->set(
                config('cache_keys.oauth_token') . ":{$token}",
                [
                    'openid'   => $memberInfo['wechat_openid'],
                    'unionid'  => $memberInfo['wechat_unionid'],
                    'brand_id' => $this->storeInfo['brand_id'],
                    'nickname' => $memberInfo['wechat_nickname'],
                ],
                get_future_time()
            );

            // 登录时就把信息存入缓存，后面就不用再查表了
            $this->getRedis()->set(
                config('cache_keys.user_info') . ":{$memberInfo['wechat_openid']}",
                $memberInfo,
                config('cache_keys.user_cache_time')
            );
        }
        return $token;
    }

    public function getJwtToken($openid)
    {
        $key     = config('jwt.key');
        $jwtData = [
            'lat'    => config('jwt.lat'),
            'nbf'    => config('jwt.nbf'),
            'exp'    => config('jwt.exp'),
            'openid' => $openid, //可以加入自己想要获得的用户信息参数
        ];

        $jwtToken = JWT::encode($jwtData, $key);

        return $jwtToken;
    }

    /**
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
}