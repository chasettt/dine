<?php
namespace app\index\controller;

use think\Db;
use think\Facade\Cache;
use think\facade\Hook;
use think\swoole\WebSocketFrame;

class Index extends \think\Controller
{
    // 自定义 onMessage
    public function websocket()
    {
        $server = WebSocketFrame::getInstance()->getServer();
        $frame = WebSocketFrame::getInstance()->getFrame();
        $data = WebSocketFrame::getInstance()->getData();
        $server->push($frame->fd, json_encode($data));
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
        //        Hook::add('swoole_websocket_on_close', 'app\\http\\behavior\\SwooleWebsocketOnclose');
    }

    public function index()
    {
        $server = WebSocketFrame::getInstance()->getServer();
        $frame = WebSocketFrame::getInstance()->getFrame();
        $data = WebSocketFrame::getInstance()->getData();
        $server->push($frame->fd, json_encode($data));
        echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
        //        Hook::add('swoole_websocket_on_close', 'app\\http\\behavior\\SwooleWebsocketOnclose');
    }

    public function hello($name = 'ThinkPHP5')
    {
        return 'hello,' . $name;
    }

    public function test()
    {
        echo 2;
    }
}
