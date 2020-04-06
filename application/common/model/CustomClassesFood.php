<?php

namespace app\common\model;

/**
 * 菜品自定义分类模型
 * Class Classes
 * @package app\common\model
 */
class CustomClassesFood extends Common
{
    protected $table = 'd_custom_classes_food';

    protected $pk = 'id';

    protected $resultSetType = 'collection';

    public function getFoodList($condition, $field = '*', $order = '')
    {
        $result = $this->field($field)->where($condition)->order($order)->select();
        return (is_null($result)) ? [] : $result->toArray();
    }
}