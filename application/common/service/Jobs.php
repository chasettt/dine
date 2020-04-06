<?php
namespace app\common\service;

use Jobby\Jobby;

class Jobs
{
    protected static $jobs;

    protected static $logFiles;

    public function __construct()
    {
        self::$jobs = new Jobby();
        self::$logFiles = RUNTIME_PATH . 'crontab/' . date('Ym') .'/'. date('d') . '/';
        return $this;
    }

    /**
     * 添加任务
     * @param array $task
     * @return $this|bool
     */
    public function addTask($task = [])
    {
        if (empty($task)) {
            return $this;
        }

        $destination = self::$logFiles . "{$task['task_name']}.log";;

        // 检测文件大小
        if(is_file($destination) && 2097152 <= filesize($destination) ){
            rename($destination, dirname($destination) . '/' . time() . '-' . basename($destination));
        }

        // 添加任务
        self::$jobs->add($task['task_name'], [
            'runAs'     => $task['runAs'],
            'command'   => $task['command'],
            'maxRuntime' => $task['maxRuntime'],
            'schedule'  => $task['schedule'],
            'output'    => $destination,
            'enabled'   => true
        ]);

        return $this;
    }

    /**
     * 运行任务
     */
    public function runTask()
    {
        self::$jobs->run();
    }
}