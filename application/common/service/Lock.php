<?php
namespace app\common\service;

use \think\Cache;

/**
 * 锁
 * Class Lock
 * @package app\common\service
 */
class Lock
{
    public function get($lockName = 'lock')
    {
        return Cache::get($lockName);
    }

    public function set($lockName, $lockValue, $lockTime=0)
    {
        return Cache::set($lockName, $lockValue, $lockTime);
    }

    public function del($lockName)
    {
        return Cache::rm($lockName);
    }
}