<?php
namespace app\common\controller;

use sdk\Redis;
use think\Controller;
use think\Log;
use think\Request;

class Program extends Controller
{
    protected $auth = true;
    protected $uid  = '';
    // 会员卡号
    protected $cno        = '';
    protected $unionid    = '';
    protected $brandid    = 0;
    protected $app_openid = '';

    protected static $redis = null;

    // 初始化
    public function _initialize()
    {
        if ($this->auth) {
            \think\Log::record('============== 小程序token验证 ==============', 'notice');
            $token = Request::instance()->header('token');
            \think\Log::record(print_r($_POST, true), 'notice');

            if (empty($token)) {
                exit(json_encode(['code' => 2010005, 'msg' => '无效token']));
            }
            \think\Log::record('参数验证成功', 'notice');

            \think\Log::record('============== token信息 ==============', 'notice');
            $memberInfo = $this->getRedis()->get(config('cache_keys.weapp') . ":{$token}");
            \think\Log::record(print_r($memberInfo, true), 'notice');

            if (!empty($memberInfo)) {
                $this->getRedis()->set(
                    config('cache_keys.weapp') . ":{$token}",
                    $memberInfo,
                    config('cache_keys.weapp_cache_time')
                );
                \think\Log::record('续签成功', 'notice');
                $this->uid        = $memberInfo['uid'];
                $this->cno        = $memberInfo['cno'];
                $this->unionid    = $memberInfo['unionid'];
                $this->app_openid = $memberInfo['app_openid'];
                $this->brandid    = $memberInfo['brandid'];
                $this->headimgurl = $memberInfo['headimgurl'];
            } else {
                \think\Log::record('token无效', 'notice');
                exit(json_encode(['code' => 2010005, 'msg' => '无效token']));
            }
        }
    }

    public function getRedis()
    {
        if (empty(self::$redis)) {
            self::$redis = new Redis();
            return self::$redis;
        }
        return self::$redis;
    }

    /**
     * 返回数据格式
     * @return array
     */
    public function returnMsg($code = 0, $message = 'success', $data = [])
    {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        header('Access-Control-Allow-Methods: GET, POST');
        return ['code' => $code, 'msg' => $message, 'data' => $data];
    }
}
