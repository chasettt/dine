<?php
namespace app\api\controller;

use sdk\Wechat as WechatApi;

/**
 * jssdk
 * Class Wechat
 * @package app\api\controller
 */
class Wechat extends Base
{
    protected $auth = true;
    
    /**
     * 公众平台JSAPI签名
     * @return mixed
     */
    public function getJsSignOp()
    {
        return $this->returnMsg(200, 'success', []);
    }
}