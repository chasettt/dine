<?php

namespace app\common\model;

/**
 * 菜品自定义分类模型
 * Class Classes
 * @package app\common\model
 */
class CustomClasses extends Common
{
    protected $table = 'd_custom_classes';

    protected $pk = 'id';

    protected $resultSetType = 'collection';

    /**
     * 获取菜品列表
     * @param $condition
     * @param string $field
     * @param string $order
     */
    public function getClassesList($condition, $field = '*', $order = 'sort asc')
    {
        $result = $this->field($field)->where($condition)->order($order)->select();
        return (is_null($result)) ? [] : $result->toArray();
    }
}