<?php
namespace app\online\controller;

class Fail extends \think\Controller
{
    public function index()
    {
        return view('index:fail', [
            'title'       => '点餐地址失效',
            'description' => '请重新扫描桌上二维码进行点餐',
            'button'      => [
                'class' => 'J-btn-scan',
                'text'  => '扫一扫',
            ],
        ]);
    }
}