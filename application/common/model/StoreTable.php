<?php
namespace app\common\model;

/**
 * 门店台位模型
 * Class Task
 * @package app\common\model
 */
class StoreTable extends Common
{
    protected $table = 'd_store_table';
    
    protected $resultSetType = 'collection';
    
    public function upStoreList($condition, $data)
    {
        return $this->where($condition)->update($data);
    }
    
    public function addTableList($data)
    {
        return $this->insertAll($data);
    }
    
    public function getTableList($condition, $field = '*', $limit = '')
    {
        $result = $this->field($field)->where($condition)->limit($limit)->select();
        return (is_null($result))?[]:$result->toArray();
    }
    
    public function deleteTableList($condition)
    {
        return $this->where($condition)->delete();
    }
}