<?php

namespace app\online\controller;

class Index extends Base
{
    /**
     * @return \think\response\Redirect|\think\response\View
     */
    public function index()
    {
        $storeId = input('get.store_id', 0, 'int');
        $tableNo = input('get.table_no', '');
        $param   = input('param.');

        if (!$storeId) {
            return view('index:fail', $this->fail('请重新扫码', '请重新扫描桌上二维码进行点餐', 'scan'));
        }

        // check store
        if ($this->storeInfo['store_state'] != 1 || $this->storeInfo['enabled_online'] != 1) {
            return view('index:fail', $this->fail('在线点餐关闭'));
        }

        // fastfood
        if ($this->storeInfo['store_mode'] == 2) {
            $param['from'] = 'fastFood';
            $param['type'] = 'eatIn';

            return redirect('/fastfood/index', $param);
        }

        // eat in
        if ($tableNo and $storeId) {
            $param    = array_merge($param, ['from' => 'eatIn']);
            $eatInUrl = '/online/eat';

            return redirect($eatInUrl, $param);
        }

        // other mode
        return redirect('/online/choose/mode', $param);
    }
}
