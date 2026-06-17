<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GiftController;
use App\Http\Controllers\Api\DailyRewardController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\AgencyController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\BaishunGameController;
use App\Http\Controllers\Api\DirectMessageController;
use App\Http\Controllers\Api\ReelController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\CoinSellerApiController;
use App\Http\Controllers\Api\PartyVideoController;
use App\Http\Controllers\Api\AppVersionController;
//use App\Http\Controllers\Api\DirectMessage;
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
        Route::post('otp/verify',     [AuthController::class, 'verifyOtp']);
        Route::post('refresh',         [AuthController::class, 'refresh']);
        Route::post('google',          [AuthController::class, 'googleLogin']);
    });
    
    Route::get('backgrounds',  [MediaController::class, 'listBackgrounds']);
    Route::get('banners',       [BannerController::class, 'apiIndex']);
    Route::get('leaderboard',  [LeaderboardController::class, 'index']);
    Route::get('app/version', [AppVersionController::class, 'check']);
    Route::get('app/config',        [AppConfigController::class,    'config']);
    Route::get('coin-sellers/public', [CoinSellerApiController::class, 'index']);
    Route::get('popup-banners/active', fn() =>
        response()->json(\App\Models\PopupBanner::getActiveBanners())); // public — no auth needed


    
    //Baishun
    Route::post('get_user_info',  [BaishunGameController::class, 'getUserInfo']);
    Route::post('change_balance', [BaishunGameController::class, 'changeBalance']);
    Route::post('game_report',    [BaishunGameController::class, 'gameReport']);
    Route::post ('game/sstoken',        [BaishunGameController::class, 'getSsToken']);
    Route::post('game/update-sstoken', [BaishunGameController::class, 'getSsToken']);

    // ── Authenticated ─────────────────────────────────────────────────────────
    Route::middleware(['api.auth', 'banned'])->group(function () {

        // Auth
        Route::post  ('auth/logout',        [AuthController::class, 'logout']);
        Route::get   ('auth/me',            [AuthController::class, 'me']);

        // Party Videos (authenticated)
        Route::prefix('party-videos')->group(function () {
            Route::get   ('/',             [PartyVideoController::class, 'myVideos']);
            Route::post  ('/upload-url',   [PartyVideoController::class, 'uploadUrl']);
            Route::post  ('/',             [PartyVideoController::class, 'store']);
            Route::post  ('/record-play',  [PartyVideoController::class, 'recordPlay']);
            Route::delete('/{id}',         [PartyVideoController::class, 'destroy']);
        });
        Route::put   ('auth/profile',       [AuthController::class, 'updateProfile']);
        Route::post  ('auth/device-token',  [AuthController::class, 'updateDeviceToken']);

        // Users
        Route::get   ('users/followers',     [UserController::class, 'myFollowers']);
        Route::get   ('users/my-bans',       [UserController::class, 'myBannedUsers']);
        Route::delete('users/{id}/unban',    [UserController::class, 'unbanUser']);
        Route::get('users/{id}/followers', [UserController::class, 'getUserFollowers']);
        Route::get('users/{id}/following', [UserController::class, 'getUserFollowing']);
        Route::get   ('users/{id}',          [UserController::class, 'show']);
        Route::post  ('users/{id}/follow',   [UserController::class, 'follow']);
        Route::delete('users/{id}/follow',   [UserController::class, 'unfollow']);
        Route::get   ('users/{id}/rooms',    [UserController::class, 'rooms']);
        Route::get('/rooms/{roomId}/viewer-count', [RoomController::class, 'viewerCount']);
        
        // Reels
        Route::get   ('reels',                        [ReelController::class, 'feed']);
        Route::get   ('reels/my',                     [ReelController::class, 'myReels']);
        Route::get   ('reels/user/{userId}',          [ReelController::class, 'userReels']);
        Route::post  ('reels/upload-url',             [ReelController::class, 'uploadUrl']);
        Route::post  ('reels/thumbnail-upload-url',   [ReelController::class, 'thumbnailUploadUrl']);
        Route::post  ('reels',                        [ReelController::class, 'store']);
        Route::post  ('reels/{reel}/view',            [ReelController::class, 'view']);
        Route::post  ('reels/{reel}/like',            [ReelController::class, 'like']);
        Route::get   ('reels/{reel}/comments',        [ReelController::class, 'comments']);
        Route::post  ('reels/{reel}/comments',        [ReelController::class, 'comment']);
        Route::delete('reels/{reel}',                 [ReelController::class, 'destroy']);
        
        //Baushun work
        Route::get ('game/config',          [BaishunGameController::class, 'getConfig']);

        // Rooms
        Route::get   ('rooms',               [RoomController::class, 'index']);
        Route::get   ('rooms/recommended',   [RoomController::class, 'recommended']);
        Route::get   ('rooms/history',       [RoomController::class, 'history']);
        Route::post  ('rooms',               [RoomController::class, 'store']);
        Route::get   ('rooms/{id}',          [RoomController::class, 'show']);
        Route::post  ('rooms/{id}/end',      [RoomController::class, 'end']);
        Route::post  ('rooms/{id}/heartbeat',[RoomController::class, 'heartbeat']);
        Route::post  ('rooms/{id}/token',    [RoomController::class, 'refreshToken']);
        Route::get   ('rooms/{id}/viewers',  [RoomController::class, 'viewers']);

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
        Route::get   ('transactions',            [TransactionController::class, 'index']);
        Route::get   ('my-lives',               [\App\Http\Controllers\Api\MyLivesController::class, 'index']);
        Route::get   ('wallet/withdrawals',      [WalletController::class, 'withdrawalHistory']);
        
        // Diamond ↔ Coin exchange
        Route::get ('exchange/rate',     [\App\Http\Controllers\Api\ExchangeController::class, 'rate']);
        Route::post('exchange/diamonds', [\App\Http\Controllers\Api\ExchangeController::class, 'exchange']);

        // Level info and milestones
        Route::get('level/info', [\App\Http\Controllers\Api\LevelController::class, 'info']);

        // Frames — shop and inventory
        Route::get ('frames/shop',      [\App\Http\Controllers\Api\FrameController::class, 'shop']);
        Route::get ('frames/inventory', [\App\Http\Controllers\Api\FrameController::class, 'inventory']);
        Route::post('frames/buy',       [\App\Http\Controllers\Api\FrameController::class, 'buy']);
        Route::post('frames/apply',     [\App\Http\Controllers\Api\FrameController::class, 'apply']);

        // Notifications
        Route::get   ('notifications',           [NotificationController::class, 'index']);
        Route::get   ('notifications/unread',    [NotificationController::class, 'unreadCount']);
        Route::post  ('notifications/read-all',  [NotificationController::class, 'markAllRead']);
        Route::post  ('notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::delete('notifications/{id}',      [NotificationController::class, 'destroy']);

        // Posts / Newsfeed
        Route::get   ('posts',                      [PostController::class, 'index']);
        Route::post  ('posts',                      [PostController::class, 'store']);
        Route::delete('posts/{id}',                 [PostController::class, 'destroy']);
        Route::post  ('posts/{id}/like',            [PostController::class, 'like']);
        Route::get   ('posts/{id}/comments',        [PostController::class, 'comments']);
        Route::post  ('posts/{id}/comments',        [PostController::class, 'comment']);
        Route::post  ('posts/media-upload-url',     [PostController::class, 'mediaUploadUrl']);
        Route::get   ('posts/user/{userId}',        [PostController::class, 'userPosts']);

        // Direct Messages
        Route::get ('dm/conversations',                     [DirectMessageController::class, 'conversations']);
        Route::post('dm/conversations/find-or-create',      [DirectMessageController::class, 'findOrCreate']);
        Route::get ('dm/conversations/{id}/messages',       [DirectMessageController::class, 'messages']);
        Route::post('dm/conversations/{id}/messages/text',  [DirectMessageController::class, 'sendText']);
        Route::post('dm/conversations/{id}/messages/image', [DirectMessageController::class, 'sendImage']);
        Route::post('dm/conversations/{id}/messages/gift',  [DirectMessageController::class, 'sendGift']);
        Route::post('dm/image-upload-url',                  [DirectMessageController::class, 'imageUploadUrl']);
        Route::get ('dm/unread',                            [DirectMessageController::class, 'unreadCount']);
        Route::post('dm/voice-upload-url',                  [DirectMessageController::class, 'voiceUploadUrl']);
        Route::post('dm/conversations/{id}/messages/voice', [DirectMessageController::class, 'sendVoice']);

        // Agency
        Route::post('agency/request', [AgencyController::class, 'requestJoin']);
        Route::get ('agency/request', [AgencyController::class, 'myRequest']);
        Route::post('agency/leave',   [AgencyController::class, 'leave']);
        Route::get ('agency/mine',    [AgencyController::class, 'mine']);

        // Media
        Route::post('/media/avatar-upload-url',    [MediaController::class, 'avatarUploadUrl']);
        Route::post('/media/cover-upload-url',     [MediaController::class, 'coverUploadUrl']);
        Route::post('/media/thumbnail-upload-url', [MediaController::class, 'roomThumbnailUploadUrl']);
        Route::post('/media/gift-upload-url',      [MediaController::class, 'giftSvgaUploadUrl']);

        // Background management (admin)
        Route::get   ('admin/backgrounds',             [MediaController::class, 'listBackgrounds']);
        Route::post  ('admin/backgrounds/upload-url',  [MediaController::class, 'backgroundUploadUrl']);
        Route::post  ('admin/backgrounds',             [MediaController::class, 'saveBackground']);
        Route::delete('admin/backgrounds/{id}',        [MediaController::class, 'deleteBackground']);

        // ── Daily live reward — manual collect ───────────────────────────────
        Route::get ('daily-reward',         [DailyRewardController::class, 'status']);
        Route::post('daily-reward/collect', [DailyRewardController::class, 'collect']);

        // ── Diamond ↔ Coin exchange ───────────────────────────────────────────
        Route::get ('exchange/rate',     [\App\Http\Controllers\Api\ExchangeController::class, 'rate']);
        Route::post('exchange/diamonds', [\App\Http\Controllers\Api\ExchangeController::class, 'exchange']);

        // ── Level info ───────────────────────────────────────────────────────
        Route::get('level/info', [\App\Http\Controllers\Api\LevelController::class, 'info']);

        // ── Frames — shop and inventory ──────────────────────────────────────
        Route::get ('frames/shop',      [\App\Http\Controllers\Api\FrameController::class, 'shop']);
        Route::get ('frames/inventory', [\App\Http\Controllers\Api\FrameController::class, 'inventory']);
        Route::post('frames/buy',       [\App\Http\Controllers\Api\FrameController::class, 'buy']);
        Route::post('frames/apply',     [\App\Http\Controllers\Api\FrameController::class, 'apply']);
    });
});