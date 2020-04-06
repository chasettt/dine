<?php
namespace app\common\model;

class Order extends Common
{
    protected $table = 'd_order';

    protected $pk = 'order_id';

    protected $resultSetType = 'collection';

    /**
     * 添加订单
     * @param $data
     * @return mixed
     */
    public function addOrder($data)
    {
        return $this->data($data)->save();
    }

    /**
     * 获取订单数据
     * @param $condition
     * @param string $field
     * @return mixed
     */
    public function getOrderInfo($condition, $field = '*')
    {
        $result = $this->field($field)->where($condition)->find();
        if (is_null($result)) {
            return [];
        }
        return $result->toArray();
    }

    /**
     * 更新订单
     * @param $condition
     * @param $data
     * @return mixed
     */
    public function upOrderInfo($condition, $data)
    {
        return $this->where($condition)->update($data);
    }

    /**
     * 获取订单列表
     * @param $condition
     * @param string $field
     * @return array
     */
    public function getOrderList($condition, $field = '*')
    {
        $result = $this->field($field)->where($condition)->select();
        return (is_null($result)) ? [] : $result->toArray();
    }

    public function getPersonalOrder($condition, $page = 1, $limit = 10000, $order = 'order_id desc', $field = '*')
    {
        $result = $this->field($field)->where($condition)->page($page)->limit($limit)->order($order)->select();
        return (is_null($result)) ? [] : $result->toArray();
    }

    /**
     * 订单某字段值
     * @param $condition
     * @param string $field
     * @return mixed
     */
    public function getOrderFieldValue($condition, $field = 'order_id')
    {
        return $this->where($condition)->value($field);
    }

    /**
     * 订单列表
     * @param array $condition
     * @param string $field
     * @param string $limit
     * @param string $order
     * @return mixed
     */
    public function getOrderPage($condition = [], $field = 'order_id', $limit = '0,10', $order = 'order_create_time desc,order_id desc')
    {
        return $this->field($field)->where($condition)->limit($limit)->order($order)->select();
    }

    /**
     * 统计订单
     * @param array $condition
     * @return mixed
     */
    public function getOrderCount($condition = [])
    {
        return $this->where($condition)->count('order_id');
    }

    /**
     * 订单
     * @param array $condition
     * @param string $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderListColumn($condition = [], $field = 'store_id')
    {
        return $this->where($condition)->column($field);
    }

    /**
     *
     */
    public function editOrder($condition, $data)
    {
        return $this->where($condition)->save($data);
    }

}