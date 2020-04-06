<?php
namespace app\api\controller;

/**
 * 下单通知 前台轮训
 * 日志已记录
 * Class Notify
 * @package app\api\controller
 */
class Notify extends Base
{
    protected $auth = true;

    public function index()
    {
        $storeCode = input('post.store_code', 0, 'int');
        $tableId   = input('post.table_id', '', 'string');

        if (! $storeCode or ! $tableId) {
            $this->failed(0, '参数错误');

            return $this->returnMsg(0, '参数错误');
        }
        $cacheName = config('cache_keys.order_notify') . ":{$storeCode}:{$tableId}:{$this->openid}";
        $result    = $this->getRedis()->get($cacheName);

        if (false !== $result) {
            $this->getRedis()->del($cacheName);
        }

        if (false !== $result) {
            $msg = $this->returnMsg(200, 'success', ['state' => $result]);
        } else {
            $msg = $this->returnMsg(200, 'success');
        }

        return $msg;
    }
}
