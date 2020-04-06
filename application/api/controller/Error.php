<?php
namespace app\api\controller;

/**
 * 空控制器
 * Class Error
 * @package app\api\controller
 */
class Error extends \think\Controller
{
    public function index()
    {
        var_dump(123);
        $this->result([], -1, '授权失败');
    }
}