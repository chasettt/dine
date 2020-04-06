<?php
namespace app\common\model;

/**
 * 门店模型
 * Class Store
 * @package app\common\model
 */
class Store extends Common
{
    protected $table = 'd_store';

    protected $pk = 'store_id';

    protected $resultSetType = 'collection';

    /**
     * 添加门店
     * @param array $data
     * @return bool|false|int
     */
    public function addStore($data = [])
    {
        if (empty($data))
            return false;

        return $this->insert($data);
    }
    
    public function addStoreList($data)
    {
        return $this->insertAll($data);
    }

    /**
     * 获取门店
     * @param array $condition
     * @param string $field
     * @return mixed
     */
    public function getStore($condition = [], $field = '*')
    {
        $result = $this->field($field)->where($condition)->find();
        if (is_null($result)) {
            return [];
        }
        return $result->toArray();
    }
    
    /**
     * 更新门店
     * @param array $condition
     * @param array $data
     * @return $this
     */
    public function upStore($condition = [], $data = [])
    {
        return $this->where($condition)->update($data);
    }

    /**
     * 门店列表
     * @param array $condition
     * @param string $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getStoreList($condition = [], $field = '*')
    {
        $result = $this->field($field)->where($condition)->select();
        return (is_null($result))?[]:$result->toArray();
        
    }

    /**
     * 门店列表
     * @param array $condition
     * @param string $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getStoreListColumn($condition = [], $field = 'store_id')
    {
        return $this->where($condition)->column($field);
    }
    
    /**
     * 删除门店
     * @param $condition
     * @return int
     */
    public function deleteStore($condition)
    {
        return $this->where($condition)->delete();
    }
    // 门店总数
    public function storeCount(){
        return $this->count();
    }
}