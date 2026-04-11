<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GiftController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\NotificationController;

/*
|--------------------------------------------------------------------------
| King Live — API Routes  (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Public ────────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('register',   [AuthController::class, 'register']);
        Route::post('login',      [AuthController::class, 'login']);
        Route::post('otp/send',   [AuthController::class, 'sendOtp']);
        Route::post('otp/verify', [AuthController::class, 'verifyOtp']);
        Route::post('refresh',    [AuthController::class, 'refresh']);
    });
    
    

    // ── Authenticated ─────────────────────────────────────────────────────────
    Route::middleware(['api.auth', 'banned'])->group(function () {

        // Auth
        Route::post  ('auth/logout',        [AuthController::class, 'logout']);
        Route::get   ('auth/me',            [AuthController::class, 'me']);
        Route::put   ('auth/profile',       [AuthController::class, 'updateProfile']);
        Route::post  ('auth/device-token',  [AuthController::class, 'updateDeviceToken']);

        // Users
        Route::get   ('users/followers',     [UserController::class, 'myFollowers']);
        Route::get   ('users/{id}',          [UserController::class, 'show']);
        Route::post  ('users/{id}/follow',   [UserController::class, 'follow']);
        Route::delete('users/{id}/follow',   [UserController::class, 'unfollow']);
        Route::get   ('users/{id}/rooms',    [UserController::class, 'rooms']);

        // Rooms
        Route::get   ('rooms',               [RoomController::class, 'index']);
        Route::get   ('rooms/recommended',   [RoomController::class, 'recommended']);
        Route::get   ('rooms/history',       [RoomController::class, 'history']);
        Route::post  ('rooms',               [RoomController::class, 'store']);
        Route::get   ('rooms/{id}',          [RoomController::class, 'show']);
        Route::post  ('rooms/{id}/end',      [RoomController::class, 'end']);
        Route::post  ('rooms/{id}/heartbeat',[RoomController::class, 'heartbeat']);
        Route::post  ('rooms/{id}/token',    [RoomController::class, 'refreshToken']);

        // Gifts
        Route::get   ('gifts',                   [GiftController::class, 'index']);
        Route::post  ('gifts/send',              [GiftController::class, 'send']);
        Route::get   ('gifts/room/{roomId}/top', [GiftController::class, 'topGifters']);
        Route::get   ('gifts/history',           [GiftController::class, 'history']);

        // Games
        Route::get   ('games',                   [GameController::class, 'index']);
        Route::get   ('games/{gameId}/url',      [GameController::class, 'getUrl']);
        Route::post  ('games/sessions',          [GameController::class, 'startSession']);
        Route::put   ('games/sessions/{id}',     [GameController::class, 'endSession']);

        // Wallet
        Route::get   ('wallet/balance',          [WalletController::class, 'balance']);
        Route::get   ('wallet/packages',         [WalletController::class, 'packages']);
        Route::post  ('wallet/purchase',         [WalletController::class, 'purchaseCoins']);
        Route::post  ('wallet/withdraw',         [WalletController::class, 'requestWithdrawal']);
        Route::get   ('wallet/transactions',     [WalletController::class, 'transactions']);
        Route::get   ('wallet/withdrawals',      [WalletController::class, 'withdrawalHistory']);
        
        // Notifications
        Route::get   ('notifications',           [NotificationController::class, 'index']);
        Route::get   ('notifications/unread',    [NotificationController::class, 'unreadCount']);
        Route::post  ('notifications/read-all',  [NotificationController::class, 'markAllRead']);
        Route::post  ('notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::delete('notifications/{id}',      [NotificationController::class, 'destroy']);

        // PK invite to user (not room)
        Route::get   ('users/followers',          [UserController::class, 'myFollowers']);
        Route::post  ('pk/invite',               [UserController::class, 'sendPkInvite']);
        
        //MediaController
        // Avatar upload URL
    Route::post('/media/avatar-upload-url', [MediaController::class, 'avatarUploadUrl']);

    // Cover upload URL
    Route::post('/media/cover-upload-url', [MediaController::class, 'coverUploadUrl']);

    // Room thumbnail upload URL
    Route::post('/media/thumbnail-upload-url', [MediaController::class, 'roomThumbnailUploadUrl']);

    // Gift SVGA upload URL (admin only)
    Route::post('/media/gift-upload-url', [MediaController::class, 'giftSvgaUploadUrl']);
    });
});
