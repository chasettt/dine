<?php
namespace app\common\model;

/**
 * 定时任务模型
 * Class Task
 * @package app\common\model
 */
class Task extends Common
{
    protected $table = 'online_task';

    protected $pk = 'task_id';

    protected $resultSetType = 'collection';

    public function getTaskList($condition = [])
    {
        $result = $this->where($condition)->select();
        
        return is_null($result)?[]:$result->toArray();
    }
}