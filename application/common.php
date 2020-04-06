<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

function get_future_time()
{
    $now   = time();
    $today = mktime(23, 59, 59, date('m'), date('d'), date('Y'));

    return $today - $now;
}

/**
 * 取得当前页面url
 * @return string
 */
function get_current_url()
{
    if ($_SERVER['SERVER_PORT'] == 443 || (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off')) {
        $scheme = 'https://';
    } else {
        $scheme = 'http://';
    }
    $port = ($_SERVER['SERVER_PORT'] != '80') ? ':' . $_SERVER['SERVER_PORT'] : '';

    return think\Facade\Request::domain() . '?' . think\Facade\Request::url();
}