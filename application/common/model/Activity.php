<?php
namespace app\common\model;

/**
 * 活动模型
 * Class Activity
 * @package app\common\model
 */
class Activity extends Common
{
    protected $table = 'd_activity';

    protected $pk = 'id';

    protected $resultSetType = 'collection';

    /**
     * 活动列表
     * @param $condition
     * @param string $order
     * @param string $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getActivityList($condition, $field = '*', $order = 'id desc', $limit = '')
    {
        $result = $this->field($field)->where($condition)->order($order)->limit($limit)->select();
        return (is_null($result)) ? [] : $result->toArray();
    }

    /**
     * 获取活动信息
     * @param $condition
     * @param string $field
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getActivityInfo($condition, $field = '*', $order = 'id desc')
    {
        $result = $this->field($field)->where($condition)->order($order)->find();
        return (is_null($result)) ? [] : $result->toArray();
    }
}
