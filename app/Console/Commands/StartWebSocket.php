<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Swoole\WebSocketServer;

class StartWebSocket extends Command
{
    protected $signature = 'ws:start';
    protected $description = 'Start KingLive WebSocket server';

    public function handle()
    {
        WebSocketServer::start();
    }
}