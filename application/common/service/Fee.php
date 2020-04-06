<?php
namespace app\common\service;
use sdk\Redis;

/**
* 茶位费处理
*/
class Fee
{
    protected static $instance = null;
    /**
     * 构造函数
     */
    public function __construct()
    {
        if (is_null(self::$instance)) {
            self::$instance = new Redis();
        }
        self::$instance->select(is_null(config('redis.db'))?0:config('redis.db'));
    }

    /**
     * 增加茶位费
     * @param array 用户购物车
     * @return array
     */
    public function addFee($storeCode, $tableNo, $userCartInfo, $fee_code, $people)
    {
        $openid = session('users.openid');
        $table_fee_key = $this->_feeKey($storeCode, $openid);
        if(self::$instance->get($table_fee_key) === 1){
            return $userCartInfo;
        }
        $table_info = self::$instance->get(config('cache_keys.table_info') . ':' . $storeCode . ':' . $tableNo);
        if(!empty($table_info['table_fee_state']) && $table_info['table_fee_state']==1){
            if($table_info['table_fee_type'] == 1 && !empty($table_info['fee_number'])){
                $num = $table_info['fee_number'];
            }else if($table_info['table_fee_type'] == 2){
                if($table_info['is_table_man'] == 1 && $table_info['is_table_child'] == 1){
                    $num = $people['people'];
                }else if($table_info['is_table_man'] == 1 && $people['man'] != 0){
                    $num = $people['man'];
                }else if($table_info['is_table_child'] == 1 && $people['child'] != 0){
                    $num = $people['child'];
                }
            }
            if(empty($num)){
                return $userCartInfo;
            }
            // 添加 $fee_code * $num
            $fee_info = model('common/food')->getFoodList(['store_code' => $storeCode, 'food_code' => $fee_code], 'food_code, food_name, food_unit, food_weigh, food_price, food_modifiers, is_combo, is_packing, food_member_price');
            if (!empty($fee_info[0]) && is_array($fee_info[0])) {
                $fee_info = $fee_info[0];
                $tmp["food_code"] = $fee_info['food_code'];
                $tmp["food_number"] = $num;
                $tmp["food_remark"] = "";
                $tmp["food_name"] = $fee_info['food_name'];
                $tmp["food_unit"] = $fee_info['food_unit'];
                $tmp["food_weigh"] = $fee_info['food_weigh'];
                $tmp["food_price"] = $fee_info['food_price'];
                $tmp["food_modifiers"] = $fee_info['food_modifiers'];
                $tmp["is_combo"] = $fee_info['is_combo'];
                $tmp["is_packing"] = $fee_info['is_packing'];
                $tmp['food_member_price'] = $fee_info['food_member_price'];
                $userCartInfo[$openid]['shopping'][$fee_info['food_code']] = $tmp;
            }
        }
        return $userCartInfo;
    }

    /**
     * 设置茶位费标识
     * @param type desc
     * @return void
     */
    public function setFlag($orderInfo)
    {
        if($orderInfo['order_type'] != 3){
            return false;
        }
        // 3 代表在线先结账
        $store_code = $orderInfo['store_code'];
        $openid = $orderInfo['create_users'];
        $table_fee_key = $this->_feeKey($store_code, $openid);
        // 已经征收茶位费,标识置为1
        self::$instance->set($table_fee_key, 1, config('cache_keys.table_fee_time'));
    }

    /**
     * 茶位费键获取
     * @param $store_code 门店ID
     * @param $openid openid
     * @return string 返回茶位费键
     */
    private function _feeKey($store_code, $openid)
    {
        $date = date('Ymd', time());
        $time = strtotime(config('cache_keys.table_fee_time_division'));
        if(time() < $time){
            $fee_key = 'lunch';
        }else{
            $fee_key = 'dinner';
        }
        return config('cache_keys.table_fee').":{$store_code}:{$openid}:{$date}:{$fee_key}";
    }
}