<?php
namespace app\api\controller;

/**
 * 点击重试按钮
 * 日志已记录
 * Class Reload
 * @package app\api\controller
 */
class Reload extends Base
{
    protected $auth = true;

    public function index()
    {
        return $this->returnMsg(200, 'success');
    }
}