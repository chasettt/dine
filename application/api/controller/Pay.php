<?php
namespace app\api\controller;

use sdk\XiBei;

/**
 * 获取美味支付链接
 * Class Pay
 * @package app\api\controller
 */
class Pay extends Base
{
    protected $auth = true;

    public function indexOp()
    {
        $storeCode = input('post.store_code', 0, 'int');
        $tableId   = input('post.table_id', '', 'string');
        if (! $storeCode or ! $tableId) {
            $this->failed(0, '参数错误');
            return $this->returnMsg(0, '参数错误');
        }
        
        $xibeiApi = new XiBei(config('domain.xibei_url'));
        $payLink  = $xibeiApi->tableLink($storeCode, $tableId);

        if (false === $payLink) {
            $this->failed($xibeiApi->errCode, $xibeiApi->errMsg);
            return $this->returnMsg(0, '获取支付链接失败');
        }

        return $this->returnMsg(200, 'success', ['path' => $payLink['qr_url']]);
    }
}
