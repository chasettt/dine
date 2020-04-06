<?php
namespace app\common\service;

use lib\Redis;

/**
 * 购物车服务类
 * Class Shopping
 * @package app\common\service
 */
class Shopping
{
    protected static $instance = [];

    /**
     * 用户购物车
     * @param $openid
     * @param $storeId
     * @return array
     */
    public function getUserCart($openid, $storeId)
    {
        // 购物车信息
        $cartList = $this->_getCache(config('cache_keys.shopping_cart') . ":{$storeId}:{$openid}");
        $foodCode = $this->_getFoodCode($storeId);
        $result   = [];
        if (!empty($foodCode) and false !== $cartList) {
            $userFilter = $this->_filter($foodCode, $cartList['details']);
            if (!empty($userFilter)) {
                foreach ($userFilter as $key => &$item) {
                    $foodInfo = $this->_getCache(config('cache_keys.store_menu_dish') . ":{$storeId}:{$item['food_code']}");
                    if (false !== $foodInfo) {
                        if (isset($item['diy']) and $item['diy'] == 1) {
                            $foodInfo['food_member_price'] = $foodInfo['food_price'] = 0;
                            foreach ($item['combo_detail'] as $value) {
                                $foodInfo['food_member_price'] = $foodInfo['food_price'] += $value['num'] * $value['price'];
                            }
                        }
                        $item = array_merge($item, $foodInfo);
                    }
                }
                //add
                $result['details'] = $userFilter;
            }
        }

        return $result;
    }

    public function getTakeawayCart($openid, $storeId, $takeaway)
    {
        // 购物车信息
        $cartList = $this->_getCache(config('cache_keys.shopping_cart') . ":{$storeId}:{$openid}");
        $foodCode = $this->_getTakeawayFoodCode($takeaway);
        $result   = [];

        if (!empty($foodCode) and false !== $cartList) {
            $userFilter = $this->_filter($foodCode, $cartList['details']);
            if (!empty($userFilter)) {
                // 这里直接查库，缓存只是用于堂食，避免外带的菜品也要加到堂食的餐单中
                $condition['store_code'] = ['in', $storeId];
                $condition['food_code']  = ['in', array_column($userFilter, 'food_code')];
                $foodList = model('common/food')->where($condition)->select()->toArray();
//                foreach ($userFilter as $key => &$item) {
//                    $foodInfo = $this->_getCache(config('cache_keys.store_menu_dish') . ":{$storeId}:{$item['food_code']}");
//                    if (false !== $foodInfo) {
//                        $item = array_merge($item, $foodInfo);
//                    }
//                }
                foreach ($foodList as &$food) {
                    $food['food_attrs'] = json_decode($food['food_attrs'], true);
                    $userFilter[$food['food_code']] = array_merge($userFilter[$food['food_code']], $food);
                }
                $result = $userFilter;
            }
        }

        return $result;
    }

    /**
     * 获取台位购物车
     * @param $storeId
     * @param $tableNo
     * @return array|boolean
     */
    public function getTableUserCart($storeId, $tableNo, $openid = '')
    {
        $shoppingTableInfo = $this->_getCache(config('cache_keys.table_shopping_cart') . ":{$storeId}:{$tableNo}");
        $tableInfo         = $this->_getCache(config('cache_keys.table_info') . ":{$storeId}:{$tableNo}");

        if (false == $shoppingTableInfo && $openid != null && !empty($openid)) {
//            \think\Log::error('查询table_shopping_cart缓存为空');
            return false;
        }

        if (!empty($openid) && !in_array($openid, $shoppingTableInfo)) {
//            \think\Log::error('报警:' . print_r($log, true));
            return false;
        }

        $tableUserCart = [];
        foreach ($shoppingTableInfo as $item) {
            // 购物车
            $userCart = $this->getUserCart($item, $storeId);

            if (!empty($userCart)) {
                foreach ($userCart['details'] as &$detail) {

                    if (!empty($tableInfo) and $tableInfo['type_id'] == config('room.type')) {
                        $detail['food_price']        = $detail['food_room_price'];
                        $detail['food_member_price'] = $detail['food_room_member_price'];
                    }
                    unset($detail['food_room_price']);
                    unset($detail['food_room_member_price']);
                }
            }

            $tableUserCart[$item]['shopping'] = isset($userCart['details']) ? $userCart['details'] : [];
            $tableUserCart[$item]['user']     = $this->getUserInfo($item);// 用户信息
//            $tableUserCart[$item]['classes']  = empty($menuInfo)?[]:$menuInfo;

            //多人点餐提示文字
            if (!empty($openid) && $item == $openid) {
                $shoppingCartText             = $this->_getCache(config('cache_keys.people_order_text') . ":{$storeId}:{$tableNo}:{$openid}");
                $tableUserCart[$item]['text'] = is_array($shoppingCartText) ? end($shoppingCartText) : '';
                //读取后，清空本人多人点餐提示文字
                if (!empty($shoppingCartText)) {
                    $data[] = '';
                    $this->_saveCache(config('cache_keys.people_order_text') . ":{$storeId}:{$tableNo}:{$openid}", $data, config('cache_keys.people_order_cache_time'));
                }
            }
        }

        return $tableUserCart;

//        return [];
    }

    /**
     * 添加菜品
     * @param $storeId
     * @param $openid
     * @param $food
     * @return bool
     */
    public function addFood($storeId, $openid, $food, $tableId = '', $isMultiCombo = 0)
    {
        $cartName = config('cache_keys.shopping_cart') . ":{$storeId}:{$openid}";
        $cartInfo = $this->_getCache($cartName);

        if (false == $cartInfo) {
            $cartInfo['source'] = $food['source'];

            if (0 == $isMultiCombo) {
                $cartInfo['details'][$food['food_code']] = [
                    'food_code'         => $food['food_code'],
                    'food_number'       => $food['food_number'],
                    'food_remark'       => $food['food_remark'],
                    'is_multiple_combo' => $isMultiCombo,
                ];
            } elseif (1 == $isMultiCombo) {
                $cartInfo['details'][$food['combo_key']] = [
                    'food_code'         => $food['food_code'],
                    'food_number'       => $food['food_number'],
                    'food_remark'       => $food['food_remark'],
                    'combo_detail'      => $food['combo_detail'],
                    'combo_key'         => $food['combo_key'],
                    'is_multiple_combo' => $isMultiCombo,
                    'diy'               => 0,
                ];
            }
//            $cartInfo['details'][$food['food_code']] = [
//                'food_code'   => $food['food_code'],
//                'food_number' => $food['food_number'],
//                'food_remark' => $food['food_remark'],
//            ];
            // $cartInfo['remark'] = $food['order_remark'];
        } else {
            if (0 == $isMultiCombo) {
                if (!empty($cartInfo['details']) && true == array_key_exists($food['food_code'], $cartInfo['details'])) {
                    $cartInfo['details'][$food['food_code']]['food_number'] += $food['food_number'];
                } else {
                    $cartInfo['details'][$food['food_code']] = [
                        'food_code'         => $food['food_code'],
                        'food_number'       => $food['food_number'],
                        'food_remark'       => $food['food_remark'],
                        'is_multiple_combo' => $isMultiCombo,
                    ];
                }
            } elseif (1 == $isMultiCombo) {
                if (!empty($cartInfo['details'])) {
                    //购物车自选套餐非空
                    if (true == array_key_exists($food['combo_key'], $cartInfo['details'])) {
                        //购物车存在此自选套餐，并且唯一键值也存在，更新套餐自选菜品 --防止因combo_key重复出现套餐信息错误
                        $cartInfo['details'][$food['combo_key']]['combo_detail']      = $food['combo_detail'];
                        $cartInfo['details'][$food['combo_key']]['food_code']         = $food['food_code'];
                        $cartInfo['details'][$food['combo_key']]['food_number']       = $food['food_number'];
                        $cartInfo['details'][$food['combo_key']]['food_remark']       = $food['food_remark'];
                        $cartInfo['details'][$food['combo_key']]['combo_key']         = $food['combo_key'];
                        $cartInfo['details'][$food['combo_key']]['is_multiple_combo'] = $isMultiCombo;
                    } else {
                        //购物车存在此自选套餐，键值不存在，新增自选套餐
                        //购物车不存在此自选套餐，新增套餐
                        $cartInfo['details'][$food['combo_key']] = [
                            'food_code'         => $food['food_code'],
                            'food_number'       => $food['food_number'],
                            'food_remark'       => $food['food_remark'],
                            'combo_detail'      => $food['combo_detail'],
                            'combo_key'         => $food['combo_key'],
                            'is_multiple_combo' => $isMultiCombo,
                            'diy'               => 0,
                        ];
                    }
                } else {
                    //购物车自选套餐为空，直接新增
                    $cartInfo['details'][$food['combo_key']] = [
                        'food_code'         => $food['food_code'],
                        'food_number'       => $food['food_number'],
                        'food_remark'       => $food['food_remark'],
                        'combo_detail'      => $food['combo_detail'],
                        'combo_key'         => $food['combo_key'],
                        'is_multiple_combo' => $isMultiCombo,
                        'diy'               => 0,
                    ];
                }
            }
        }
        $this->_saveCache($cartName, $cartInfo, get_future_time());

        //多人点餐提示文字
        if ($food['source'] == 'online' && !empty($tableId)) { //在线点餐堂食

            $userInfo  = $this->getUserInfo($openid);
            $data[]    = $userInfo['nickname'] . "加了1份菜，您想加点什么";
            $tableInfo = $this->_getCache(config('cache_keys.table_shopping_cart') . ":{$storeId}:{$tableId}");
            if (false != $tableInfo and false !== $keys = array_search($openid, $tableInfo)) {
                unset($tableInfo[$keys]);
                if (!empty($tableInfo)) {
                    foreach ($tableInfo as $item) {
                        $this->_saveCache(config('cache_keys.people_order_text') . ":{$storeId}:{$tableId}:{$item}", $data, config('cache_keys.people_order_cache_time'));
                    }
                }
            }

        }

        //多人点餐提示文字 end

        return true;
    }

    /**
     * todo 添加自选套餐修改
     * 添加多道菜品
     * @param type desc
     * @return  desc
     */
    public function addMulti($openid, $source, $storeCode, $foodCode, $foodNumber = 1)
    {
        $cartName = config('cache_keys.shopping_cart') . ":{$storeCode}:{$openid}";
        $cartInfo = $this->_getCache($cartName);

        if (empty($cartInfo['details'])) {
            // 全新添加
            $cartInfo['source'] = $source;
            foreach ($foodCode as $code) {
                $food                = [];
                $food['food_code']   = $code;
                $food['food_remark'] = '';
                if (empty($cartInfo['details'][$code]['food_number'])) {
                    $food['food_number'] = $foodNumber;
                } else {
                    $food['food_number'] = $cartInfo['details'][$code]['food_number'] + $foodNumber;
                }
                $cartInfo['details'][$code] = $food;
            }

            return $this->_saveCache($cartName, $cartInfo, get_future_time());
        } else {
            $foodKeys = array_keys($cartInfo['details']);
            foreach ($foodCode as $code) {
                if (in_array($code, $foodKeys)) {
                    // 购物车已有该菜品
                    $food['food_code'] = $code;
                    $cartInfo['details'][$code]['food_number'] += $foodNumber;
                } else {
                    $food = [];
                    // 购物车没有该菜品
                    $food['food_code'] = $code;
                    if (empty($cartInfo['details'][$code]['food_number'])) {
                        $food['food_number'] = $foodNumber;
                    } else {
                        $food['food_number'] = $cartInfo['details'][$code]['food_number'] + $foodNumber;
                    }
                    $food['food_remark']        = '';
                    $cartInfo['details'][$code] = $food;
                }
            }

            return $this->_saveCache($cartName, $cartInfo, get_future_time());
        }
    }

    /**
     * 快餐模式
     * 添加菜品
     * @param $storeId
     * @param $openid
     * @param $food
     * @return bool
     */
    public function addFastFood($storeId, $openid, $food, $isMultiCombo = 0)
    {
        $cartName = config('cache_keys.shopping_cart') . ":{$storeId}:{$openid}";
        $cartInfo = $this->_getCache($cartName);

        if (false == $cartInfo) {
            $cartInfo['source'] = $food['source'];
            if (0 == $isMultiCombo) {
                $cartInfo['details'][$food['food_code']] = [
                    'food_code'         => $food['food_code'],
                    'food_number'       => $food['food_number'],
                    'food_remark'       => $food['food_remark'],
                    'is_multiple_combo' => $isMultiCombo,
                ];
            } elseif (1 == $isMultiCombo) {
                $cartInfo['details'][$food['combo_key']] = [
                    'food_code'         => $food['food_code'],
                    'food_number'       => $food['food_number'],
                    'food_remark'       => $food['food_remark'],
                    'combo_detail'      => $food['combo_detail'],
                    'combo_key'         => $food['combo_key'],
                    'diy'               => $food['diy'],
                    'is_multiple_combo' => $isMultiCombo,
                ];
            }
        } else {
            if (0 == $isMultiCombo) {
                if (!empty($cartInfo['details'])) {
                    //购物车单品菜非空
                    if (true == array_key_exists($food['food_code'], $cartInfo['details'])) {
                        $cartInfo['details'][$food['food_code']]['food_number'] += $food['food_number'];
                    } else {
                        $cartInfo['details'][$food['food_code']] = [
                            'food_code'         => $food['food_code'],
                            'food_number'       => $food['food_number'],
                            'food_remark'       => $food['food_remark'],
                            'is_multiple_combo' => $isMultiCombo,
                        ];
                    }
                } else {
                    //购物车单品菜空，直接新增
                    $cartInfo['details'][$food['food_code']] = [
                        'food_code'         => $food['food_code'],
                        'food_number'       => $food['food_number'],
                        'food_remark'       => $food['food_remark'],
                        'is_multiple_combo' => $isMultiCombo,
                    ];
                }
            } elseif (1 == $isMultiCombo) {
                if (!empty($cartInfo['details'])) {
                    //购物车自选套餐非空
                    if (true == array_key_exists($food['combo_key'], $cartInfo['details'])) {
                        //购物车存在此自选套餐，并且唯一键值也存在，更新套餐自选菜品
                        $cartInfo['details'][$food['combo_key']]['combo_detail'] = $food['combo_detail'];
                    } else {
                        //购物车存在此自选套餐，键值不存在，新增自选套餐
                        //购物车不存在此自选套餐，新增套餐
                        $cartInfo['details'][$food['combo_key']] = [
                            'food_code'         => $food['food_code'],
                            'food_number'       => $food['food_number'],
                            'food_remark'       => $food['food_remark'],
                            'combo_detail'      => $food['combo_detail'],
                            'combo_key'         => $food['combo_key'],
                            'diy'               => $food['diy'],
                            'is_multiple_combo' => $isMultiCombo,
                        ];
                    }
                } else {
                    //购物车自选套餐为空，直接新增
                    $cartInfo['details'][$food['combo_key']] = [
                        'food_code'    => $food['food_code'],
                        'food_number'  => $food['food_number'],
                        'food_remark'  => $food['food_remark'],
                        'combo_detail' => $food['combo_detail'],
                        'combo_key'    => $food['combo_key'],
                        'diy'          => $food['diy'],
                    ];
                }
            }
        }
        $this->_saveCache($cartName, $cartInfo, get_future_time());

        return true;
    }

    /**
     * 添加用户至台位
     * @param $storeId
     * @param $tableNo
     * @param $openid
     * @return bool
     */
    public function addUserToTable($storeId, $tableNo, $openid)
    {
        // 检测用户是否在该台位
        $tableName = config('cache_keys.table_shopping_cart') . ":{$storeId}:{$tableNo}";
//        $tableTime = config('cache_keys.table_shopping_cache_time');

        $tableList = $this->_getCache($tableName);

        //多人点餐提示信息
        if (empty($tableList)) {
            //第一个用户进入台位
            $text[] = '快邀请同桌一起扫码点餐';
            $this->_saveCache(config('cache_keys.people_order_text') . ":{$storeId}:{$tableNo}:{$openid}", $text, config('cache_keys.people_order_cache_time'));
        } elseif (!in_array($openid, $tableList)) {
            //非第一个用户进入台位
            $userInfo = $this->getUserInfo($openid);
            $text[]   = $userInfo['nickname'] . '进入扫码点餐模式';
            $this->_saveCache(config('cache_keys.people_order_text') . ":{$storeId}:{$tableNo}:{$openid}", $text, config('cache_keys.people_order_cache_time'));

            //增加文字至该台位关联的所有人
            foreach ($tableList as $tableUser) {
                $this->_saveCache(config('cache_keys.people_order_text') . ":{$storeId}:{$tableNo}:{$tableUser}", $text, config('cache_keys.people_order_cache_time'));
            }
        }
        //多人点餐提示信息 end

        if (false == $tableList) {
            $this->_checkUserTable($storeId, $tableNo, $openid);
            $this->_saveCache($tableName, [$openid], config('cache_keys.table_shopping_cache_time'));

            return true;
        }

        if (true != in_array($openid, $tableList)) {
            $tableList[] = $openid;
            $this->_checkUserTable($storeId, $tableNo, $openid);
            $this->_saveCache($tableName, $tableList, config('cache_keys.table_shopping_cache_time'));

            return true;
        }


        // 如果openid在table_shopping_cart中，更新user_store_table_label
        // 这里是必须的，否则进入其他台位时，不会删除在这个台位上的table_shopping_cart中的openid
        $this->_saveCache(config('cache_keys.user_store_table_label') . ":{$openid}", $storeId . ':' . $tableNo, get_future_time());

        return true;
    }

    /**
     * 删除菜品
     * @param $storeId
     * @param $openid
     * @param $food
     * @return bool
     */
    public function delFood($storeId, $openid, $food, $isMultiCombo = 0)
    {
        $cartName = config('cache_keys.shopping_cart') . ":{$storeId}:{$openid}";
        $cartInfo = $this->_getCache($cartName);

        if (false == $cartInfo) {
            return false;
        } else {
            if (0 == $isMultiCombo) {
                if (true == array_key_exists($food['food_code'], $cartInfo['details'])) {
                    if ($cartInfo['details'][$food['food_code']]['food_number'] - $food['food_number'] <= 0) {
                        unset($cartInfo['details'][$food['food_code']]);
                    } else {
                        $cartInfo['details'][$food['food_code']]['food_number'] -= $food['food_number'];
                    }
                }
            } elseif (1 == $isMultiCombo) {
                //自选套餐
                if (true == array_key_exists($food['combo_key'], $cartInfo['details'])) {
                    unset($cartInfo['details'][$food['combo_key']]);
                }
            }
        }

        if (empty($cartInfo['details'])) {
            $this->_delCache($cartName);
        } else {
            $this->_saveCache($cartName, $cartInfo, get_future_time());
        }

        return true;
    }

    /**
     * 快餐模式
     * 删除菜品
     * @param $storeId
     * @param $openid
     * @param $food
     * @return bool
     */
    public function delFastFood($storeId, $openid, $food, $isMultiCombo = 0)
    {
        $cartName = config('cache_keys.shopping_cart') . ":{$storeId}:{$openid}";
        $cartInfo = $this->_getCache($cartName);

        if (false == $cartInfo) {
            return false;
        } else {
            if (0 == $isMultiCombo) {
                if (true == array_key_exists($food['food_code'], $cartInfo['details'])) {
                    if ($cartInfo['details'][$food['food_code']]['food_number'] - $food['food_number'] <= 0) {
                        unset($cartInfo['details'][$food['food_code']]);
                    } else {
                        $cartInfo['details'][$food['food_code']]['food_number'] -= $food['food_number'];
                    }
                }
            } elseif (1 == $isMultiCombo) {
                //自选套餐
                if (true == array_key_exists($food['combo_key'], $cartInfo['details'])) {
                    unset($cartInfo['details'][$food['combo_key']]);
                }
            }
        }

        if (empty($cartInfo['details'])) {
            $this->_delCache($cartName);
        } else {
            $this->_saveCache($cartName, $cartInfo, get_future_time());
        }

        return true;
    }

    /**
     * 清空用户购物车
     * @param $storeId
     * @param $openid
     * @return bool
     */
    public function clearUserCart($storeId, $openid)
    {
        $cartName = config('cache_keys.shopping_cart') . ":{$storeId}:{$openid}";
//        $cartInfo = $this->_getCache($cartName);
//        if (false !== $cartInfo) {
        $this->_delCache($cartName);
//        }

        return true;
    }

    /**
     * 清空台位购物车
     * @param $storeId
     * @param $tableNo
     * @return bool
     */
    public function clearTableCart($storeId, $tableNo)
    {
        $tableName = config('cache_keys.table_shopping_cart') . ":{$storeId}:{$tableNo}";
        $tableList = $this->_getCache($tableName);

        if (false !== $tableList) {
            foreach ($tableList as $openid) {
                $this->clearUserCart($storeId, $openid);
            }
        }

        return true;
    }

    /**
     * 清空台位用户信息缓存
     * @param $storeId
     * @param $tableNo
     * @return bool
     */
    public function clearTableShoppingCart($storeId, $tableNo)
    {
        $tableName = config('cache_keys.table_shopping_cart') . ":{$storeId}:{$tableNo}";
        $this->_delCache($tableName);

        return true;
    }

    /**
     * 添加备注
     * @param $storeId
     * @param $openid
     * @param $food
     * @return bool
     */
    public function remark($storeId, $openid, $food, $isMultiCombo = 0)
    {
        $cartName = config('cache_keys.shopping_cart') . ":{$storeId}:{$openid}";
        $cartInfo = $this->_getCache($cartName);
        if (false !== $cartInfo) {
            if (0 == $isMultiCombo) {
                if (true == array_key_exists($food['food_code'], $cartInfo['details'])) {
                    $cartInfo['details'][$food['food_code']]['food_remark'] = $food['food_remark'];
                    $this->_saveCache($cartName, $cartInfo, get_future_time());

                    return true;
                }
            } elseif (1 == $isMultiCombo && isset($cartInfo['details'][$food['combo_key']])) {
                $cartInfo['details'][$food['combo_key']]['food_remark'] = $food['food_remark'];
                $this->_saveCache($cartName, $cartInfo, get_future_time());

                return true;
            }
        }

        return false;
    }

    /**
     * 快餐模式
     * 添加备注
     * @param $storeId
     * @param $openid
     * @param $food
     * @return bool
     */
    public function remarkFastfood($storeId, $openid, $food, $isMultiCombo = 0)
    {
        $cartName = config('cache_keys.shopping_cart') . ":{$storeId}:{$openid}";
        $cartInfo = $this->_getCache($cartName);
        if (false !== $cartInfo) {
            if (1 == $isMultiCombo) {
                if (true == array_key_exists($food['combo_key'], $cartInfo['details'])) {
                    $cartInfo['details'][$food['combo_key']]['food_remark'] = $food['food_remark'];
                    $this->_saveCache($cartName, $cartInfo, get_future_time());

                    return true;
                }
            } elseif (0 == $isMultiCombo) {
                if (true == array_key_exists($food['food_code'], $cartInfo['details'])) {
                    $cartInfo['details'][$food['food_code']]['food_remark'] = $food['food_remark'];
                    $this->_saveCache($cartName, $cartInfo, get_future_time());

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 添加整单备注
     * @param $storeId
     * @param $openid
     * @param $food
     * @return bool
     */
    public function orderRemark($storeId, $openid, $remark)
    {
        $cartName = config('cache_keys.shopping_cart') . ":{$storeId}:{$openid}";
        $cartInfo = $this->_getCache($cartName);
        if (false !== $cartInfo) {
            $cartInfo['remark'] = $remark['order_remark'];
            $this->_saveCache($cartName, $cartInfo, get_future_time());

            return true;
        }

        return false;
    }

    public function setPeopleNum($man, $child, $storeId, $openid)
    {
        $cartName = config('cache_keys.shopping_cart_people') . ":{$storeId}:{$openid}";
        $cartInfo = [
            'man'   => $man,
            'child' => $child,
        ];
        $this->_saveCache($cartName, $cartInfo, get_future_time());

        return true;
    }

    public function getPeopleNum($storeId, $openid)
    {
        $cartName = config('cache_keys.shopping_cart_people') . ":{$storeId}:{$openid}";
        $cartInfo = $this->_getCache($cartName);
        if (false !== $cartInfo) {
            return $cartInfo;
        }

        return false;
    }

    /**
     * 菜品CODE
     * @param $storeId
     * @return array
     */
    private function _getFoodCode($storeId)
    {
        // 优化，直接取门店菜品列表缓存，不用处理餐单
        $storeFoods = $this->_getCache(config('cache_keys.store_menu_dish_code') . ":{$storeId}");
        if (empty($storeFoods)) {
            return [];
        }

        return array_unique($storeFoods);
    //        $menuList = $this->_getCache(config('cache_keys.store_menu') . ":{$storeId}");
    //        if (false !== $menuList) {
    //            $foodCodeList = [];
    //            foreach ($menuList as $classesInfo) {
    //                if (!empty($classesInfo['food_list'])) {
    //                    $foodCodeList = array_merge($foodCodeList, array_column($classesInfo['food_list'], 'food_code'));
    //                }
    //            }
    //
    //            return array_unique($foodCodeList);
    //        }
    //
    //        return [];
    }

    private function _getTakeawayFoodCode($takeaway)
    {
        $menuList = model('common/takeawayMenu')->getFoodList([
            'takeaway_id' => $takeaway,
        ], 'sort asc', 'food_code, sort');

        if (!empty($menuList)) {
            $foodCodeList = array_column($menuList, 'food_code');

            return array_unique($foodCodeList);
        }

        return [];
    }

    public function getUserInfo($openid)
    {
        $userInfo = $this->_getCache(config('cache_keys.user_info') . ":{$openid}");
        if (false === $userInfo) {
            $userInfo = model('common/users', 'service')->getUserInfo($openid);
        }
        if (!empty($userInfo)) {
            $result['openid']     = $userInfo['wechat_openid'];
            $result['nickname']   = $userInfo['wechat_nickname'];
            $result['headimgurl'] = $userInfo['wechat_headimgurl'];

            return $result;
        }

        return [];
    }

    /**
     * 检查台位是否唯一
     * @param $storeId
     * @param $tableNo
     * @param $openid
     * @return bool
     */
    private function _checkUserTable($storeId, $tableNo, $openid)
    {
        $labelName = config('cache_keys.user_store_table_label') . ":{$openid}";
        $labelTime = config('cache_keys.table_shopping_cache_time');
        $tableName = config('cache_keys.table_shopping_cart');
        $tableTime = config('cache_keys.table_shopping_cache_time');

        $userLabel = $this->_getCache($labelName);
        $tableStr  = $storeId . ':' . $tableNo;

        if (false == $userLabel) {
            $this->_saveCache($labelName, $tableStr, $labelTime);

            return true;
        }

        if ($tableStr != $userLabel) {
            // 获取台位信息
            $tableInfo = $this->_getCache($tableName . ":{$userLabel}");
            if (false != $tableInfo and false !== $keys = array_search($openid, $tableInfo)) {
//                unset($tableInfo[$keys]);
                // 防止有多个相同的openid存在
                foreach ($tableInfo as $key => $table) {
                    if ($table == $openid) {
                        unset($tableInfo[$key]);
                    }
                }

                if (empty($tableInfo)) {
                    $this->_delCache($tableName . ":{$userLabel}");
                } else {
                    $this->_saveCache($tableName . ":{$userLabel}", $tableInfo, $tableTime);
                }
            }
            $this->_saveCache($labelName, $tableStr, $labelTime);
        }

        return true;
    }

    /**
     * 过滤购物车
     * @param $foodCodeList
     * @param $orderFoodList
     * @return array
     */
    private function _filter($foodCodeList, $orderFoodList)
    {
        if (!empty($foodCodeList) and !empty($orderFoodList)) {
            foreach ($orderFoodList as $item) {
                if (!in_array($item['food_code'], $foodCodeList)) {
                    unset($orderFoodList[$item['food_code']]);
                }
            }

            return !empty($orderFoodList) ? $orderFoodList : [];
        }

        return [];
    }

    /**
     * 对象单例
     * @param $name
     * @return Redis
     */
    private function _checkCache($name = 'redis')
    {
        if (!isset(self::$instance[$name]) or !self::$instance[$name]) {
            self::$instance[$name] = new Redis();

            return self::$instance[$name];
        }

        return self::$instance[$name];
    }

    /**
     * get redis
     * @param $cacheName
     * @return mixed
     */
    private function _getCache($cacheName)
    {
        return Redis::getInstance()->get($cacheName);
    }


    /**
     * set redis
     * @param string $cacheName
     * @param array $data
     * @param null $cacheTime
     * @return mixed
     */
    private function _saveCache($cacheName = '', $data = [], $cacheTime = null)
    {
        return Redis::getInstance()->set($cacheName, $data, $cacheTime);
    }

    /**
     * del redis
     * @param $cacheName
     * @return int
     */
    private function _delCache($cacheName)
    {
        return Redis::getInstance()->del($cacheName);
    }

}