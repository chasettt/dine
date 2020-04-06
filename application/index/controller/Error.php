<?php
/**
 * Created by PhpStorm.
 * User: teng
 * Date: 2020/4/4
 * Time: 11:08 PM
 */
namespace app\index\controller;

class Error
{
    public function index()
    {
        http_response_code(404);
        exit();
    }
}