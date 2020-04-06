<?php
namespace app\common\behavior;

use \think\Debug;
use \think\Request as req;
use \think\Log;

class Request
{
    public function appInit(&$params)
    {
        Debug::remark('app_init');
    }

    public function appEnd(&$params)
    {
        Debug::remark('app_end');
        $times = Debug::getRangeTime('app_init', 'app_end');
        $url   = req::instance()->url();
        $param = req::instance()->param();
        $module = req::instance()->module();

        if ($times > 3 and $module != 'crontab') {
            $debug = \think\Env::get('app_debug');

            if (! $debug and $module != 'crontab') {

                $logs = "应用模块：{$module}\n\n";
                $logs .= "推送时间：" . date('m-d H:i:s') . "\n\n";
                $logs .= "请求地址：\n{$url}\n\n";
                $logs .= "请求参数：\n" . json_encode($param, JSON_UNESCAPED_UNICODE) . "\n\n";
                $logs .= "响应时间：{$times}\n";

                $data = [
                    'user_name' => 'zenghp',
                    'content'   => $logs,
                ];

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, 'http://self.db.xibeidev.com/sendwechatbase');
                curl_setopt($curl, CURLOPT_HEADER, 1);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                curl_exec($curl);
                curl_close($curl);
            }

            Log::record("请求地址：{$url}", Log::DEBUG);
            Log::record("请求参数：\r\n" . print_r($param, true), Log::DEBUG);
            Log::record("响应时间：{$times}s", Log::DEBUG);
        }
    }

}
