<?php
namespace app\common\service;

use sdk\Redis;

/**
 * 对列
 * Class Queue
 * @package app\common\service
 */
class Queue
{
    protected static $instance = null;
    protected $prefix = 'takeout';
    protected $queue = 'payment';
    protected $db = 3;
    
    /**
     * 初始化
     * Queue constructor.
     */
    public function __construct()
    {
        if (is_null(self::$instance)) {
            self::$instance = new Redis();
        }
        
        self::$instance->setPrefix($this->prefix.":");
        self::$instance->select($this->db);
    }
    
    /**
     * 入栈
     * @param $value
     * @return int
     */
    public function push($value)
    {
        return self::$instance->lPush($this->queue, $value);
    }
    
    /**
     * 出栈
     * @return string
     */
    public function pop()
    {
        return self::$instance->rPop($this->queue);
    }
}