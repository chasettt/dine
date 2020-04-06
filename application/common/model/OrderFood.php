<?php
namespace app\common\model;

use Symfony\Component\Translation\Tests\IdentityTranslatorTest;

class OrderFood extends Common
{
    protected $table = 'd_order_food';

    protected $resultSetType = 'collection';

    /**
     * 添加订单
     * @param $data
     * @return mixed
     */
    public function addFoodList($data)
    {
        return $this->saveAll($data);
    }

    /**
     * 菜品列表
     * @param $condition
     * @param string $field
     * @return mixed
     */
    public function getFoodList($condition, $field = '*')
    {
        $result = $this->field($field)->where($condition)->select();
        return (is_null($result))?[]:$result->toArray();
    }

    public function getOrderFoodCount($condition){
        $result = $this ->where($condition)->count();
        return (is_null($result))?[]:$result;
    }

    /**
     * 菜品详情分页
     * @param $condition
     * @param string $field
     * @param string $order
     * @return mixed
     */
    public function getFoodPage($condition, $field = '*', $order = 'order_food_id desc')
    {
        return $this->field($field)->where($condition)->order($order)->select();
    }

    public function foodTotalPrice($condition = [])
    {
        $foodList = $this->getFoodList($condition, 'food_price,food_number');

        if (! empty($foodList)) {
            $total = 0;

            foreach ($foodList as $item) {
                $total += $item['food_price'] * $item['food_number'];
            }

            return $total;
        }

        return false;

        //return $this->where($condition)->sum($field);
    }
    
    public function updateFoodInfo($condition, $data)
    {
        return $this->where($condition)->update($data);
    }
    
    public function delFoodList($condition)
    {
        return $this->where($condition)->delete();
    }
}