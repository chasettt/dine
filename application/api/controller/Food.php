<?php
namespace app\api\controller;

/**
 * 菜品控制器
 * 日志已记录
 * Class Food
 * @package app\api\controller
 */
class Food extends Base
{
    protected $auth = true;

    /**
     * 来源
     */
    public function source()
    {
        return $this->returnMsg(200, 'success');
    }
}
