<?php
namespace app\common\controller;

use sdk\Redis;
use think\Controller;
use think\Request;
use think\Log;

class Website extends Controller
{
    protected $auth = true;
    protected $storeInfo = [];
    protected static $redis = null;

    public function _initialize()
    {
        $request = Request::instance();
        $storeId = $request->param('store_id');

        // 参数验证
        if (! $storeId) {
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

            $selfUrl = get_current_url();
            $oauth   = config('domain.web_url') . config('address.oauth') . urlencode($selfUrl) . '&brand_id=' . $brandId;

            if (! input('?get.token')) {
                $token = cookie(config('cache_keys.oauth_token'));
                if (! $token) {
                    $this->redirect($oauth);
                } else {
                    $check = model('common/oauth', 'service')->check($token, $brandId, true);
                    if (! $check) {
                        $this->redirect($oauth);
                    }
                }
            } else {
                $token = input('get.token');
                $check = model('common/oauth', 'service')->check($token, $brandId, true);
                if (! $check) {
                    $this->redirect($oauth);
                }
                cookie(config('cache_keys.oauth_token'), $token, config('cache_keys.token_cache_time'));
            }
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
     * 错误
     * @param string $title
     * @param string $description
     * @param string $btn
     * @return array
     */
    public function fail($title = '', $description = '', $btn = '')
    {
        $buttons = array(
            'scan' => [
                'class' => 'J-btn-scan',
                'text'  => '扫一扫',
            ],
            'reload' => [
                'class' => 'J-btn-refresh',
                'text'  => '重新获取'
            ]
        );

        $button = array();
        if ($btn && isset($buttons[$btn])) {
            $button = $buttons[$btn];
        }
        return [
            'title' => $title,
            'description'   => $description,
            'button' => $button,
        ];
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