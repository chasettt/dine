<?php
namespace app\api\controller;

class Ad extends Base
{
    protected $auth = false;

    /**
     * 新广告平台接口
     * @return array
     */
    public function getList()
    {
        return $this->returnMsg(200, 'success', []);
    }
    
    /**
     * 广告点击率
     * @return array
     */
    public function click()
    {
        echo json_encode(['code' => 200, 'msg' => 'success']);
        if (function_exists("fastcgi_finish_request")) {
            fastcgi_finish_request();
        }
    }
}
