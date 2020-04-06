<?php
namespace app\common\model;

class FoodSort extends Common
{
    protected $table = 'd_food_sort';

    protected $pk = 'sort_id';

    protected $resultSetType = 'collection';

    public function addAll($data)
    {
        return $this->insertAll($data);
    }

    public function getList($condition, $field = '*')
    {
        return $this->field($field)->where($condition)->select();
    }

   public function getOneList($condition, $field = '*')
   {
       return $this->field($field)->where($condition)->find();
   }
    
    public function getSortInfo($condition, $field = '*')
    {
        $result = $this->field($field)->where($condition)->find();
        return (is_null($result))?[]:$result->toArray();
    }

    public function delSortInfo($condition)
    {
        return $this->where($condition)->delete();
    }

    public function getListColumn($condition, $field = 'sort_id')
    {
        return $this->where($condition)->column($field);
    }
}
