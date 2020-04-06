<?php
namespace app\common\model;

class Packing extends Common
{
    protected $table = 'd_packing_category';
    
    protected $pk = 'category_id';
    
    protected $resultSetType = 'collection';
    
    public function getCategoryInfo($condition, $field = '*')
    {
        $result = $this->field($field)->where($condition)->find();
        if (is_null($result)) {
            return [];
        }
        return $result->toArray();
    }
    
    public function getCategoryList($condition, $field = '*')
    {
        $result = $this->field($field)->where($condition)->select();
        if (is_null($result)) {
            return [];
        }
        return $result->toArray();
    }

    public function updateCategory($condition, $data=[])
    {
        $result = $this->where($condition)->update($data);
        return $result;
    }
}