<?php

use Laravel\Horizon\Horizon;

return [

    'domain' => env('HORIZON_DOMAIN', null),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env('HORIZON_PREFIX', 'horizon:'),

    'middleware' => ['web', 'admin.auth'],

    'waits' => [
        'redis:default' => 60,
    ],

    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,
        'failed'        => 10080,
        'monitored'     => 10080,
    ],

    'silenced' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 256,

    'defaults' => [
        'supervisor-gifts' => [
            'connection' => 'redis',
            'queue'      => ['gifts'],
            'balance'    => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 10,
            'maxTime'      => 0,
            'maxJobs'      => 0,
            'memory'       => 128,
            'tries'        => 3,
            'timeout'      => 60,
            'nice'         => 0,
        ],
        'supervisor-notifications' => [
            'connection' => 'redis',
            'queue'      => ['notifications'],
            'balance'    => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 5,
            'memory'       => 128,
            'tries'        => 2,
            'timeout'      => 90,
            'nice'         => 0,
        ],
        'supervisor-default' => [
            'connection' => 'redis',
            'queue'      => ['default'],
            'balance'    => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 5,
            'memory'       => 128,
            'tries'        => 3,
            'timeout'      => 120,
            'nice'         => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-gifts'         => ['minProcesses' => 2, 'maxProcesses' => 10],
            'supervisor-notifications' => ['minProcesses' => 1, 'maxProcesses' => 5],
            'supervisor-default'       => ['minProcesses' => 1, 'maxProcesses' => 5],
        ],
        'local' => [
            'supervisor-gifts'         => ['minProcesses' => 1, 'maxProcesses' => 2],
            'supervisor-notifications' => ['minProcesses' => 1, 'maxProcesses' => 2],
            'supervisor-default'       => ['minProcesses' => 1, 'maxProcesses' => 2],
        ],
    ],

];
