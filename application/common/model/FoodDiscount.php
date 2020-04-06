<?php

namespace app\common\model;

/**
* 菜品折扣模型
*/
class FoodDiscount extends Common
{
	protected $table = 'd_food_discount';
	protected $pk = 'store_code';
	protected $resultSetType = 'collection';

	public function getDiscountFoodList($condition, $field = '*')
	{
		$result = $this->field($field)->where($condition)->select();
		return (is_null($result))?[]:$result->toArray();
	}

    public function getDiscountFoodPage($condition, $field = '*', $limit = null, $order = 'discount_id desc')
    {
        $result = $this->field($field)->where($condition)->limit($limit)->order($order)->select();
        return (is_null($result))?[]:$result->toArray();
    }

    public function getCount($condition)
    {
        return $this->where($condition)->count();
    }

    public function upState($condition, $data)
    {
        return $this->where($condition)->update($data);
    }

    public function getDiscountFoodInfo($condition, $field = '*')
    {
        $result = $this->where($condition)->field($field)->find();
        return (is_null($result))?[]:$result->toArray();
    }

    public function addDiscount($data)
    {
        return $this->insertAll($data);
    }

    public function getDiscountColumn($condition, $field)
    {
        $result = $this->where($condition)->column($field);
        return $result;
    }
}