<?php
namespace app\common\service;

/**
 * 上传附件
 */

use lib\Ftp;
use think\Exception;

class FileRemote
{
    public static function upload($local, $remote, $delLocal = false)
    {
        $ftp = new Ftp(config('ftp'));

        if (true === $ftp->connect()) {
            $response = $ftp->upload($local, $remote);

            if ($delLocal) {
                @unlink($local);
            }

            if (empty($response)) {
                throw new Exception('远程附件上传失败，请检查配置并重新上传');
            }

            return true;
        } else {
            throw new Exception('FTP服务器连接失败');
        }
    }
}

