<?php

namespace app\common\model;

/**
 * 菜品模型
 * Class Food
 * @package app\common\model
 */
class Food extends Common
{
    protected $table = 'd_food';

    protected $pk = 'food_id';

    protected $resultSetType = 'collection';

    /**
     * 菜品列表
     * @param $condition
     * @param string $order
     * @param string $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getFoodList($condition, $field = '*', $order = 'food_id desc', $limit = '')
    {
        $result = $this->field($field)->where($condition)->order($order)->limit($limit)->select();
        return (is_null($result)) ? [] : $result->toArray();
    }

    /**
     * 菜品列表
     * @param $condition
     * @param string $column
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getFoodListColumn($condition, $column = 'food_code')
    {
        return $this->where($condition)->column($column);
    }

    /**
     * 获取菜品信息
     * @param $condition
     * @param string $field
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getFoodInfo($condition, $field = '*')
    {
        return $this->field($field)->where($condition)->find();
    }

    /**
     * 更新菜品
     * @param $condition
     * @param $data
     * @return $this
     */
    public function upFoodInfo($condition, $data)
    {
        return $this->where($condition)->update($data);
    }

    public function upFoodList($data)
    {
        return $this->saveAll($data);
    }

    /**
     * 添加菜品
     * @param $data
     * @return int|string
     */
    public function addFoodInfo($data)
    {
        return $this->insert($data);
    }

    public function addFoodList($data)
    {
        return $this->insertAll($data);
    }

    public function deleteFood($condition)
    {
        return $this->where($condition)->delete();
    }

    // 数据统计
    public function getCount($condition = [])
    {
        return $this->where($condition)->count();
    }
}
