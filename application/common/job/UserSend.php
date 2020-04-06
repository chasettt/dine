<?php

namespace app\common\job;

use think\queue\Job;
use think\Db;

/**
 * 微信数据上报消息队列
 *
 * @package app\common\job
 */
class UserSend
{
    /**
     * 消息队列默认调用的方法
     *
     * @param Job $job 当前的任务对象
     * @param array|mixed $data 发布任务时自定义的数据
     */
    public function fire(Job $job, $data)
    {
        try {
            echo date('Y-m-d H:i:s')."\t收到任务: [ openid: ".$data['openid'] .', attempts:'.$job->attempts()." ]\n";
            // 代码逻辑
            model('common/member', 'service')->sendUserInfo($data);

            // 任务执行成功删除任务
            $job->delete();
        } catch (\Exception $e) {
            echo date('Y-m-d H:i:s') . "\t任务异常: " . $e->getMessage() . ', attempts:' . $job->attempts() . " ]\n";

            if ($job->attempts() >= 3) {

                // 错误记录数据库
                Db::name('online_queue_fail')->insert([
                    'job'         => $job->getName(),
                    'queue'       => $job->getQueue(),
                    'data'        => json_encode($data),
                    'attempts'    => $job->attempts(),
                    'create_time' => time(),
                ]);

                $job->delete();
            } else {
                // 重新发布任务 延迟3秒
                $job->release(3);
            }
        }
    }
}