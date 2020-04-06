<?php
namespace app\common\model;

use think\Db;

/**
 * 菜品排序规则
 * Class FoodSortRule
 * @package app\common\model
 */
class FoodSortRule extends Common
{
    protected $table = 'd_food_sort_rule';
    protected $pk = 'rule_id';
    protected $resultSetType = 'collection';
    
    public function getRuleList($condition, $field = '*')
    {
        $result = $this->field($field)->where($condition)->select();
        return (is_null($result))?[]:$result->toArray();
    }
}