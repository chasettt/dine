<?php
namespace app\common\model;

/**
 * 菜品分类模型
 * Class Classes
 * @package app\common\model
 */
class Classes extends Common
{
    protected $table = 'd_classes';

    protected $pk = 'classes_id';

    protected $resultSetType = 'collection';

    /**
     * 获取分类
     * @param $condition
     * @param string $field
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getClassesInfo($condition, $field = '*')
    {
        return $this->field($field)->where($condition)->find();
    }

    /**
     * 获取菜品列表
     * @param $condition
     * @param string $field
     * @param string $order
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getClassesList($condition, $field = '*', $order='classes_id desc')
    {
        $result = $this->field($field)->where($condition)->order($order)->select();
        return (is_null($result))?[]:$result->toArray();
    }

    /**
     * 获取菜品列表
     * @param $condition
     * @param string $column
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getClassesListColumn($condition, $column = 'classes_code')
    {
        return $this->where($condition)->column($column);
    }

    /**
     * 更新分类
     * @param $condition
     * @param $data
     * @return $this
     */
    public function upClassesInfo($condition, $data)
    {
        return $this->where($condition)->update($data);
    }

    /**
     * 添加分类
     * @param $data
     * @return int|string
     */
    public function addClassesInfo($data)
    {
        return $this->insert($data);
    }
    
    public function addClassesList($data)
    {
        return $this->insertAll($data);
    }
    
    public function deleteClasses($condition)
    {
        return $this->where($condition)->delete();
    }
}