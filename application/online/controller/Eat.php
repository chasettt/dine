<?php

namespace app\online\controller;

use think\Facade\Log;
use sdk\Choice;

/**
 * Class EatIn
 * @package app\online\controller
 */
class Eat extends Base
{
    public function index()
    {
        $storeId = input('get.store_id', 0, 'int');
        $tableNo = input('get.table_no', '', 'string');
        $from    = input('get.from');

        $this->assign('logo', $this->storeInfo['store_logo']);

        // add food
        if (input('?get.type') and 'add' == input('get.type')) {
            return view('index:index');
        }

        $choiceApi = new Choice(config('domain.choice_url'));
        $tableInfo = $choiceApi->getTableInfo($storeId, $tableNo);

//        if (empty($tableInfo)) {
//            return view('index:fail', $this->fail('台位不存在'));
//        }
        $tableInfo['state'] = 1;

        switch ($tableInfo['state']) {
            case 1:
            case 2:
                // finish last order
                $upState = model('common/order')->upOrderInfo([
                    'store_code'  => $storeId,
                    'table_id'    => $tableNo,
                    'order_state' => 10,
                ], ['order_state' => 1]);

                //clear table user cache
                if ($upState) {
                    model('common/shopping', 'service')->clearTableShoppingCart($storeId, $tableNo);
                }

                return view('index:index');
                break;
            case 3:
                return redirect('/online/eat/order', [
                    'store_id' => $storeId,
                    'table_no' => $tableNo,
                    'from'     => $from,
                    'type'     => 'add',
                ]);
                break;
            case 4:
                return view('index:fail', $this->fail('台位已被预订'));
                break;

            case 5:
                return view('index:fail', $this->fail('预结台'));
                break;

            case 6:
                return view('index:fail', $this->fail('还未收台'));
                break;
        }
    }

    /**
     * @return \think\response\Redirect|\think\response\View
     */
    public function order()
    {
        $storeId = input('get.store_id', 0, 'int');
        $tableNo = input('get.table_no', '', 'string');
        $from    = input('get.from');

        if (!$storeId or !$tableNo) {
            return view('index:fail', $this->fail('请重新扫码', '请重新扫描桌上二维码进行点餐', 'scan'));
        }

        $tableInfo = $this->getRedis()->get(config('cache_keys.table_info') . ":{$storeId}:{$tableNo}");

        if (false == $tableInfo) {
            return view('index:fail', $this->fail('厨师哪去了', '亲，我和厨房失去联系了', 'reload'));
        }

        if ($tableInfo['state'] == 1 or $tableInfo['state'] == 2) {
            // 跳转首页
            return redirect('/online/eat', [
                'store_id' => $storeId,
                'table_no' => $tableNo,
                'from'     => $from,
            ]);
        }

        return view('index:order');
    }

    /**
     * 确认页
     */
    public function confirm()
    {
        return view('index:confirm');
    }

    /**
     * 成功页
     * @return \think\response\View
     */
//    public function success()
//    {
//        // todo is_member 放到会员表里
//        $this->assign('isMember', 0);
//        $this->assign('vipBuy', '');
//
//        return view('index:success');
//    }

    /**
     * @return \think\response\View
     */
//    public function fail()
//    {
//        return view('index:fail', $this->fail('请求失败'));
//    }

    public function orderDetail()
    {
        return view('index:orderDetail');
    }

    public function orderFail()
    {
        return view('index:orderFail');
    }
}
