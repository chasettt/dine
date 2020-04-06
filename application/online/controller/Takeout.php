<?php
namespace app\online\controller;

class Takeout extends Base
{
    public function index()
    {
        if (empty($this->storeInfo)) {
            return view('index/fail', $this->fail('请重新扫码', '请重新扫描桌上二维码进行点餐', 'scan'));
        }

        if(!empty($this->storeInfo['enabled_opening']) && $this->storeInfo['enabled_opening'] == 1){
            $opentime = $this->_checkOpentime($this->storeInfo);
            if (! empty($opentime)) {
                $this->assign('openTime', $opentime['open_time']);
                $this->assign('store_name', $opentime['store_name']);
                return view('index:rest');
            }
        }

        $this->assign('vip_url', config('domain.vip_url').sprintf(config('address.vip_address'), $this->storeInfo['store_code']).'&redirect_url='.urlencode(request()->url(true)));
        $this->assign('shop_url', config('domain.shop_url').sprintf(config('address.shop_address'), $this->storeInfo['welife_shopid']));
        $this->assign('logo', $this->storeInfo['store_logo']);
        return view('index:index');
    }

    /**
     * 确认页
     */
    public function confirm()
    {
        // 门店信息
        if (empty($this->storeInfo)) {
            return view('index/fail', $this->fail('请重新扫码', '请重新扫描桌上二维码进行点餐', 'scan'));
        }

        $this->assign('store_name', empty($this->storeInfo['store_name']) ? '' : $this->storeInfo['store_name']);
        $this->assign('store_address', empty($this->storeInfo['store_address']) ? '' : $this->storeInfo['store_address']);
        return view('confirm');
    }
    
}