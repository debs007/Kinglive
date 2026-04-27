<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))

    ->withMiddleware(function (Middleware $middleware) {

        // register web + api stacks
        $middleware->web();
       // $middleware->api();

        // register aliases
        $middleware->alias([
            'admin.auth'        => \App\Http\Middleware\AdminAuthenticate::class,
            'agency.auth'       => \App\Http\Middleware\AgencyAuthenticate::class,
            'coin_seller.auth'  => \App\Http\Middleware\CoinSellerAuthenticate::class,
            'api.auth'          => \App\Http\Middleware\ApiAuthenticate::class,
            'banned'            => \App\Http\Middleware\CheckBanned::class,
        ]);
    })

    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // 👇 THIS IS THE MISSING PIECE
        // then: function () {
        //     // force all web routes to use web middleware
        //     Route::middleware('web')->group(base_path('routes/web.php'));
        // }
    )

    ->withExceptions(function (Exceptions $exceptions) {
        //
    })

    ->create();