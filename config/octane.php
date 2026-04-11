<?php

use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Octane\Octane;

return [

    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    | Supported: "swoole", "roadrunner", "frankenphp"
    */
    'server' => env('OCTANE_SERVER', 'swoole'),

    'https' => env('OCTANE_HTTPS', false),

    'workers' => env('OCTANE_WORKERS', 16),

    'task_workers' => env('OCTANE_TASK_WORKERS', 8),

    'max_requests' => env('OCTANE_MAX_REQUESTS', 500),

    /*
    |--------------------------------------------------------------------------
    | Swoole Configuration
    |--------------------------------------------------------------------------
    */
    'swoole' => [
        'options' => [
            'log_file'                => storage_path('logs/swoole_http.log'),
            'log_level'               => SWOOLE_LOG_ERROR,
            'package_max_length'      => 50 * 1024 * 1024,   // 50 MB
            'upload_max_filesize'     => 20 * 1024 * 1024,   // 20 MB
            'upload_tmp_dir'          => sys_get_temp_dir(),   // 20 MB
            'document_root'           => public_path(),
            'enable_static_handler'   => true,
            'static_handler_locations' => ['/storage', '/app'],

            // WebSocket
            'open_websocket_protocol' => false,

            // Performance
            'dispatch_mode'       => 2,
            'enable_coroutine'    => false,
            'max_coroutine'       => 3000,
            'socket_buffer_size'  => 2 * 1024 * 1024,
            'buffer_output_size'  => 32 * 1024 * 1024,

            // Keep-alive
            'open_tcp_keepalive'  => true,
            'tcp_keepidle'        => 60,
            'tcp_keepinterval'    => 10,
            'tcp_keepcount'       => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Warm / Flush Services
    |--------------------------------------------------------------------------
    */
    'warm' => [
        ...Octane::defaultServicesToWarm(),
        \App\Services\AgoraService::class,
        \App\Services\BanService::class,
        \App\Services\GiftService::class,
    ],

    'flush' => [],

    'garbage' => [
        'collector' => env('OCTANE_GARBAGE_COLLECTOR', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Listeners
    |--------------------------------------------------------------------------
    */
    'listeners' => [
        WorkerStarting::class  => [],
        RequestReceived::class => [...Octane::prepareApplicationForNextOperation()],
        RequestHandled::class  => [],
        TaskReceived::class    => [...Octane::prepareApplicationForNextOperation()],
        TickReceived::class    => [...Octane::prepareApplicationForNextOperation()],
    ],

];
