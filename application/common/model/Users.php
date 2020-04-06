<?php
namespace app\common\model;

class Users extends Common
{
    protected $table = 'd_user';

    protected $pk = 'wechat_id';
    
    protected $resultSetType = 'collection';
    
    public function getUserInfo($condition, $field = '*')
    {
        $result = $this->field($field)->where($condition)->find();
        return is_null($result)?[]:$result->toArray();
    }

    public function addUser($data)
    {
        return $this->insert($data);
    }

    public function updateUser($condition, $data)
    {
        return $this->where($condition)->update($data);
    }

    public function addDataGetId($data)
    {
        $this->insert($data);
        return $this->getLastInsID();
    }
}