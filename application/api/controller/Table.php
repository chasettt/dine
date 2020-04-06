<?php
namespace app\api\controller;

/**
 * 台位
 * Class Table
 * @package app\api\controller
 */
class Table extends Base
{
    protected $auth = false;

    /**
     * 台位状态
     * @return mixed
     */
    public function state()
    {
        $storeCode = input('post.store_code', 0, 'int');
        $tableId   = input('post.table_id', '', 'string');
        if (! $storeCode or ! $tableId) {
            $this->failed(0, '参数错误');

            return $this->returnMsg(0, '参数错误');
        }

        $tableInfo = $this->getRedis()->get(config('cache_keys.choice_table_state') . ":{$storeCode}:{$tableId}");
        if (false === $tableInfo) {
            $this->failed(0, '台位不存在');

            return $this->returnMsg(0, '台位不存在');
        }

        return $this->returnMsg(200, 'success', [
            'state' => $tableInfo['table_state'],
        ]);
    }

    /**
     * 获取台位信息
     * @return mixed
     */
    public function info()
    {
        $storeCode = input('post.store_code');
        $tableId   = input('post.table_id', '', 'string');
        if (! $storeCode or ! $tableId) {
            $this->failed(0, '参数错误');

            return $this->returnMsg(0, '参数错误');
        }

        $tableInfo = $this->getRedis()->get(config('cache_keys.table_info') . ":{$storeCode}:{$tableId}");

        if (false === $tableInfo) {
            $this->failed($this->getRedis()->errCode, $this->getRedis()->errMsg);

            return $this->returnMsg(0, '台位不存在');
        }

        return $this->returnMsg(200, 'success', $tableInfo);
    }
}
