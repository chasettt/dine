<?php
/**
 * Created by PhpStorm.
 * User: teng
 * Date: 2020/4/5
 * Time: 1:41 AM
 */

namespace app\traits;

trait Singleton
{
    private static $instance = null;

    static function getInstance(...$args)
    {
        if (!isset(self::$instance)) {
            self::$instance = new static(...$args);
        }

        return self::$instance;
    }
}