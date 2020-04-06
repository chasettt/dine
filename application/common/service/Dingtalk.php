<?php
/**
 * Created by PhpStorm.
 * User: teng
 * Date: 2019/6/14
 * Time: 下午4:58
 */
namespace app\common\service;

use think\Request;

class Dingtalk
{
    public function sendWarningMsg($errCode, $errMsg)
    {
        $request = Request::instance();

        $data = [
            'msgtype' => 'text',
            'text' => [
                'content' => json_encode([
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
                ], JSON_UNESCAPED_UNICODE),
            ]
        ];

        $dataString = json_encode($data);

        $result = $this->request_by_curl(config('domain.dingtalk_url'), $dataString);
        return $result;
    }

    public function request_by_curl($remote_server, $post_string)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $remote_server);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array ('Content-Type: application/json;charset=utf-8'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
        // curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        // curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

}