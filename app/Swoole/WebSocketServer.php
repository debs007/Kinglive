<?php

namespace App\Swoole;

use Swoole\WebSocket\Server;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class WebSocketServer
{
    public static function start(): void
    {
        $server = new Server("0.0.0.0", 9502);

        $server->set([
            'worker_num' => swoole_cpu_num(),
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'daemonize' => false,
        ]);

        $server->on('start', function () {
            echo "KingLive WebSocket server started on 0.0.0.0:9502\n";
        });

        $server->on('open', function ($server, $request) {
            WebSocketHandler::onOpen($server, $request);
        });

        $server->on('message', function ($server, $frame) {
            Log::info("WS MESSAGE-m: " . $frame->data);
            WebSocketHandler::onMessage($server, $frame);
        });

        $server->on('close', function ($server, $fd) {
            WebSocketHandler::onClose($server, $fd);
        });

        $server->start();
    }
}