<?php
/**
 * Created by PhpStorm.
 * User: teng
 * Date: 2020/4/5
 * Time: 1:39 AM
 */

return [
    'db'          => 1,
    'prefix'      => 'online',
    'host'        => think\Facade\Env::get('redis.host'),
    'port'        => 6379,
    'password'    => '',
    'rule'        => true,
    'remainder'   => 10,
    'sys_version' => 32,
    'serialize'   => true,
];