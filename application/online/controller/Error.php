<?php
namespace app\online\controller;

/**
 * 空控制器
 * Class Error
 * @package app\api\controller
 */
class Error extends \think\Controller
{
    public function index()
    {
        return view('public:404');
    }
}