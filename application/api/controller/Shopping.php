<?php

namespace app\api\controller;

/**
 * 购物车
 * Class Reserve
 * @package app\api\controller
 */
class Shopping extends Base
{
    protected $auth = true;

    /**
     * 获取购物车
     */
    public function get()
    {
        $param   = input('post.param', 'users'); // users , table
        $storeId = input('post.store_code', 0, 'int');
        $tableNo = input('post.table_id', '', 'string');

        // 参数检测
        if ('' == $param) {
            return $this->returnMsg(0, '请求参数错误');
        }

        $result          = [];
        $shoppingService = model('common/shopping', 'service');
        switch ($param) {
            case 'users':
                if (!$param or !$storeId) {
                    $this->failed(0, '参数错误');

                    return $this->returnMsg(0, '参数错误');
                }
                // 购物车
                $result = $shoppingService->getUserCart($this->openid, $storeId);

                $result = isset($result['details']) ? $result['details'] : '';
                break;
            case 'table':
                if (!$param or !$storeId or !$tableNo) {
                    return $this->returnMsg(0, '请求参数错误');
                }
                $result = $shoppingService->getTableUserCart($storeId, $tableNo, $this->openid);
                if (false === $result) {
                    return $this->returnMsg(-1, '登录已过期，请重新扫码');
                }
                break;
        }

        return $this->returnMsg(200, 'success', $result);
    }

    /**
     * 添加商品
     * @return array
     */
    public function add()
    {
        $param        = input('post.param', 'users'); // users , table, takeaway
        $source       = input('post.source', 'online');
        $foodCode     = input('post.food_code', 0, 'int');
        $foodNumber   = input('post.food_number', 1, 'int');
        $foodRemark   = input('post.food_remark', '');
        $openid       = input('post.openid', '');
        $tableId      = input('post.table_id', '', 'string');
        $storeCode    = input('post.store_code', 0, 'int');
        $isMultiCombo = input('post.is_multi_combo', 0, 'int'); //是否是自选套餐
        $comboDetail  = input('post.combo_detail/a', []); //自选套餐详情
        $comboKey     = input('post.combo_key', '', 'string'); //前端生成套餐唯一KEY
        $diy          = input('post.diy', 0, 'int');


        // 参数检测
        if ('' == $param) {
            return $this->returnMsg(0, '请求参数错误');
        }

        $shoppingService = model('common/shopping', 'service');
        switch ($param) {
            case 'users':
                // 参数检测
                if (!$source or !$foodCode or !$foodNumber or !$storeCode) {
                    return $this->returnMsg(0, '请求参数错误');
                }
                // 检测是否单独设置openid
                if ('' != $openid) {
                    $weChatOpenid = $openid;
                } else {
                    $weChatOpenid = $this->openid;
                }
                if ($source == 'fastfood') {
                    $shoppingService->addFastFood($storeCode, $weChatOpenid, [
                        'source'       => $source,
                        'food_code'    => $foodCode,
                        'food_number'  => $foodNumber,
                        'food_remark'  => $foodRemark,
                        'order_remark' => '',
                        'combo_detail' => $comboDetail,
                        'combo_key'    => $comboKey,
                        'diy'          => $diy,
                    ], $isMultiCombo);
                } else {
                    $shoppingService->addFood($storeCode, $weChatOpenid, [
                        'source'       => $source,
                        'food_code'    => $foodCode,
                        'food_number'  => (int)$foodNumber,
                        'food_remark'  => $foodRemark,
                        'order_remark' => '',
                        'combo_detail' => $comboDetail,
                        'combo_key'    => $comboKey,
                        'diy'          => $diy,
                    ], $tableId, $isMultiCombo);
                }
                break;
            case 'table':
                // 参数检测
                if (!$tableId or !$storeCode) {
                    return $this->returnMsg(0, '请求参数错误');
                }
                $shoppingService->addUserToTable($storeCode, $tableId, $this->openid);
                break;
        }

        return $this->returnMsg(200, 'success');
    }

    /**
     * 添加多个菜品
     * @return array
     */
    public function addMulti()
    {
        $source          = input('post.source', 'online');
        $foodCode        = input('post.food_code/a');
        $foodNumber      = input('post.food_number', 1, 'int');
        $storeCode       = input('post.store_code', 0, 'int');
        $shoppingService = model('common/shopping', 'service');
        if ($shoppingService->addMulti($this->openid, $source, $storeCode, $foodCode)) {
            return $this->returnMsg(200, 'success');
        } else {
            return $this->returnMsg(0, 'error');
        }
    }

    /**
     * 删除商品
     * @return array
     */
    public function del()
    {
        $param        = input('post.param', 'users'); // users , table
        $foodCode     = input('post.food_code', 0, 'int');
        $foodNumber   = input('post.food_number', 1, 'int');
        $openid       = input('post.openid', '');
        $storeCode    = input('post.store_code', 0, 'int');
        $isMultiCombo = input('post.is_multi_combo', 0, 'int'); //是否是自选套餐
        $comboKey     = input('post.combo_key', '', 'string'); //前端生成套餐唯一KEY
        $source       = input('post.source', 'online', 'string');

        // 检测参数
        if ('' == $param) {
            return $this->returnMsg(0, '请求参数错误');
        }

        $shoppingService = model('common/shopping', 'service');
        switch ($param) {
            // 用户购物车
            case 'users':
                if (!$foodCode or !$foodNumber) {
                    return $this->returnMsg(0, '请求参数错误');
                }

                // 检测是否单独设置openid
                if ('' != $openid) {
                    $weChatOpenid = $openid;
                } else {
                    $weChatOpenid = $this->openid;
                }
                if ($source == 'fastfood') {
                    $shoppingService->delFastFood($storeCode, $weChatOpenid, [
                        'food_code'   => $foodCode,
                        'food_number' => $foodNumber,
                        'combo_key'   => $comboKey,
                    ], $isMultiCombo);
                } else {
                    $shoppingService->delFood($storeCode, $weChatOpenid, [
                        'food_code'   => $foodCode,
                        'food_number' => $foodNumber,
                        'combo_key'   => $comboKey,
                    ], $isMultiCombo);
                }

                break;
        }

        return $this->returnMsg(200, 'success');
    }

    /**
     * 清空购物车
     */
    public function clear()
    {
        $param     = input('post.param', 'users'); // users , table
        $tableId   = input('post.table_id', '', 'string');
        $storeCode = input('post.store_code', 0, 'int');

        // 参数
        if ('' == $param) {
            return $this->returnMsg(0, '请求参数错误');
        }

        $shoppingService = model('common/shopping', 'service');
        switch ($param) {
            // 用户购物车
            case 'users':
                if (!$storeCode) {
                    return $this->returnMsg(0, '请求参数错误');
                }
                $shoppingService->clearUserCart($storeCode, $this->openid);
                break;
            // 台位购物车
            case 'table':
                if (!$storeCode or !$tableId) {
                    return $this->returnMsg(0, '请求参数错误');
                }
                $shoppingService->clearTableCart($storeCode, $tableId);
                break;
        }

        return $this->returnMsg(200, 'success');
    }

    /**
     * 添加备注
     * @return array
     */
    public function remark()
    {
        $param        = input('post.param', 'users'); // users , table
        $storeCode    = input('post.store_code', 0, 'int');
        $foodCode     = input('post.food_code', 0, 'int');
        $foodRemark   = input('post.food_remark', '');
        $openid       = input('post.openid', '');
        $isMultiCombo = input('post.is_multi_combo', 0, 'int'); //是否是自选套餐
        $comboKey     = input('post.combo_key', '', 'string'); //前端生成套餐唯一KEY
        $source       = input('post.source', 'online', 'string');

        // 参数检测
        if (!$param or !$foodCode) {
            return $this->returnMsg(0, '请求参数错误');
        }

        // 检测是否单独设置openid
        if ('' != $openid) {
            $weChatOpenid = $openid;
        } else {
            $weChatOpenid = $this->openid;
        }

        $shoppingService = model('common/shopping', 'service');
        if ('fastfood' == $source) {
            $shoppingService->remarkFastfood($storeCode, $weChatOpenid, [
                'food_code'   => $foodCode,
                'food_remark' => str_replace(' ', '', $foodRemark),
                'combo_key'   => $comboKey,
            ], $isMultiCombo);
        } else {
            $shoppingService->remark($storeCode, $weChatOpenid, [
                'food_code'   => $foodCode,
                'food_remark' => str_replace(' ', '', $foodRemark),
                'combo_key'   => $comboKey,
            ], $isMultiCombo);
        }

        return $this->returnMsg(200, 'success');
    }

    public function orderRemark()
    {
        $param       = input('post.param', 'users'); // users , table
        $storeCode   = input('post.store_code', 0, 'int');
        $orderRemark = input('post.remark', '');
        $openid      = input('post.openid', '');

        // 参数检测
        if (!$param) {
            return $this->returnMsg(0, '请求参数错误');
        }

        // 检测是否单独设置openid
        if ('' != $openid) {
            $weChatOpenid = $openid;
        } else {
            $weChatOpenid = $this->openid;
        }

        $shoppingService = model('common/shopping', 'service');
        $shoppingService->orderRemark($storeCode, $weChatOpenid, [
            'order_remark' => str_replace(' ', '', $orderRemark),
        ]);

        return $this->returnMsg(200, 'success');
    }

    public function setPeople()
    {
        $man       = input('post.man', 1, 'int');
        $child     = input('post.child', 0, 'int');
        $storeCode = input('post.store_code', 0, 'int');

        if (empty($storeCode) || empty($man)) {
            return $this->returnMsg(0, '请求参数错误');
        }

        $shoppingService = model('common/shopping', 'service');
        $shoppingService->setPeopleNum($man, $child, $storeCode, $this->openid);

        return $this->returnMsg(200, 'success');
    }

    public function getPeople()
    {
        $storeCode = input('post.store_code', 0, 'int');

        if (empty($storeCode)) {
            return $this->returnMsg(0, '请求参数错误');
        }

        $storeInfo = $this->getRedis()->get(config('cache_keys.store_info') . ":{$storeCode}");
        if ($storeInfo['enabled_select_num'] == 0 && $storeInfo['default_num'] != 0) {
            return $this->returnMsg(200, 'success', ['people' => $storeInfo['default_num'], 'man' => $storeInfo['default_num'], 'child' => 0]);
        } else if ($storeInfo['store_mode'] == 2) {
            return $this->returnMsg(0, 'error');
        }

        $shoppingService = model('common/shopping', 'service');
        $res             = $shoppingService->getPeopleNum($storeCode, $this->openid);
        if ($res) {
            return $this->returnMsg(200, 'success', $res);
        }

        return $this->returnMsg(0, 'error');
    }

    public function getComboSelected()
    {
        $param        = input('post.param', 'users'); // users , table
        $storeId      = input('post.store_code', 0, 'int');
        $tableNo      = input('post.table_id', '', 'string');
        $comboKey     = input('post.combo_key', '', 'string');
        $isMultiCombo = input('post.is_multi_combo', 0, 'int'); //是否是自选套餐

        // 参数检测
        if ('' == $param || empty($tableNo) || empty($storeId) || 1 != $isMultiCombo) {
            return $this->returnMsg(0, '请求参数错误');
        }

        if (0 == $comboKey) {
            return $this->returnMsg(200, 'success');
        }

        $result          = [];
        $shoppingService = model('common/shopping', 'service');
        // 购物车
        $result = $shoppingService->getUserCart($this->openid, $storeId);

        $result = isset($result['details']) ? $result['details'] : '';
        $result = model('common/discount', 'service')->process($storeId, $result, $this->openid, 1);

        if (isset($result[$comboKey])) {
            $result = $result[$comboKey];
        } else {
            return $this->returnMsg(0, '自选套餐参数错误');
        }

        return $this->returnMsg(200, 'success', $result);
    }
}
