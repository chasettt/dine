<?php
/**
 * Created by PhpStorm.
 * User: teng
 * Date: 2020/4/6
 * Time: 3:57 PM
 */

namespace app\http\controller;

use lib\Redis;
use think\swoole\Server;

class Websocket extends Server
{
    protected $host = '127.0.0.1';
    protected $port = 9508;
    protected $serverType = 'socket';

    protected $option = [
        'worker_num'=> 4,
        'daemonize'	=> false,
        'backlog'	=> 128,
    ];

    public function onOpen($server, $request)
    {
        echo "ws server: handshake success with fd{$request->fd}\n";
    }

    public function onRequest($request, $response)
    {
        $response->end("<h1>Hello Swoole. #" . rand(1000, 9999) . "</h1>");
    }

    public function onMessage($server, $frame)
    {
//        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
        $data = json_decode($frame->data, true);
        dump($data);
        $fd = $frame->fd;

        switch ($data['action']) {
            case 'food_add':
                $res = model('common/cart', 'service')->addFood($data);
                model('common/cart', 'service')->notifyCart($server, $data['store_code'], $data['table_id']);
                break;
            case 'food_del':
                $res = model('common/cart', 'service')->delFood($data);
                model('common/cart', 'service')->notifyCart($server, $data['store_code'], $data['table_id']);
                break;
            case 'init_table':
                $res = model('common/cart', 'service')->addUserToTable($data, $fd);
                break;
        }

        $server->push($frame->fd, json_encode($res));
    }

    public function onClose($ser, $fd)
    {
        echo "ws client {$fd} closed\n";
    }
}