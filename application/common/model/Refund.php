<?php

namespace app\common\model;

class Refund extends Common
{
    protected $table = 'd_order_refund';
    protected $pk = 'refund_id';
    protected $resultSetType = 'collection';

    public function updateRefund($condition = [], $data)
    {
        return $this->where($condition)->update($data);
    }

    public function getRefundInfo($condition, $field = '*')
    {
        $result = $this->field($field)->where($condition)->find();
        return is_null($result)?[]:$result->toArray();
    }

    public function addRefund($data)
    {
        return $this->insert($data);
    }
}