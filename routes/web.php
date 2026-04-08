<?php

use App\Http\Controllers\Admin\BanController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GameReportController;
use App\Http\Controllers\Admin\GiftReportController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| King Live — Admin Web Routes  (prefix: /admin)
|--------------------------------------------------------------------------
*/

// ── Admin Auth ────────────────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {

    Route::get ('login',  [DashboardController::class, 'loginForm'])->name('login');
    Route::post('login',  [DashboardController::class, 'login'])->name('login.post');
    Route::post('logout', [DashboardController::class, 'logout'])->name('logout');

    // ── Protected ─────────────────────────────────────────────────────────────
    Route::middleware('admin.auth')->group(function () {

        // Dashboard
        Route::get('/',               [DashboardController::class, 'index'])->name('dashboard');
        Route::get('api/live-rooms',  [DashboardController::class, 'liveRoomsJson'])->name('api.live-rooms');

        // Users
        Route::get ('users',               [UserController::class, 'index'])->name('users.index');
        Route::get ('users/{id}',          [UserController::class, 'show'])->name('users.show');
        Route::put ('users/{id}/role',     [UserController::class, 'updateRole'])->name('users.role');
        Route::post('users/{id}/coins',    [UserController::class, 'adjustCoins'])->name('users.coins');
        Route::post('users/{id}/toggle',   [UserController::class, 'toggleActive'])->name('users.toggle');

        // Bans
        Route::get ('bans',                [BanController::class, 'index'])->name('bans.index');
        Route::post('bans',                [BanController::class, 'store'])->name('bans.store');
        Route::post('bans/unban',          [BanController::class, 'unban'])->name('bans.unban');
        Route::get ('bans/history/{id}',   [BanController::class, 'history'])->name('bans.history');

        // Rooms
        Route::get ('rooms',               [RoomController::class, 'index'])->name('rooms.index');
        Route::get ('rooms/{id}',          [RoomController::class, 'show'])->name('rooms.show');
        Route::post('rooms/{id}/end',      [RoomController::class, 'endRoom'])->name('rooms.end');

        // Gifts
        Route::get  ('gifts/report',       [GiftReportController::class, 'report'])->name('gifts.report');
        Route::get  ('gifts/manage',       [GiftReportController::class, 'manage'])->name('gifts.manage');
        Route::post ('gifts',              [GiftReportController::class, 'store'])->name('gifts.store');
        Route::put  ('gifts/{id}',         [GiftReportController::class, 'update'])->name('gifts.update');
        Route::delete('gifts/{id}',        [GiftReportController::class, 'destroy'])->name('gifts.destroy');
        Route::get  ('gifts/export',       [GiftReportController::class, 'exportCsv'])->name('gifts.export');

        // Games
        Route::get ('games/report',        [GameReportController::class, 'report'])->name('games.report');
        Route::get ('games/manage',        [GameReportController::class, 'manage'])->name('games.manage');
        Route::post('games',               [GameReportController::class, 'store'])->name('games.store');
        Route::put ('games/{id}',          [GameReportController::class, 'update'])->name('games.update');

        // Withdrawals
        Route::get ('withdrawals',              [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::post('withdrawals/{id}/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve');
        Route::post('withdrawals/{id}/paid',    [WithdrawalController::class, 'markPaid'])->name('withdrawals.paid');
        Route::post('withdrawals/{id}/reject',  [WithdrawalController::class, 'reject'])->name('withdrawals.reject');

        // Settings
        Route::get ('settings',            [SettingsController::class, 'index'])->name('settings.index');
        Route::post('settings',            [SettingsController::class, 'update'])->name('settings.update');
    });
});

// Redirect root to admin
Route::get('/', fn () => redirect('/admin'));
