<?php

use App\Http\Controllers\Admin\AgencyController;
use App\Http\Controllers\Admin\SyncUserLevelsController;
use App\Http\Controllers\Admin\BackgroundController;
use App\Http\Controllers\Admin\FrameController;
use App\Http\Controllers\Admin\SalaryController;
use App\Http\Controllers\Admin\LevelFrameController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Agency\AgencyPortalController;
use App\Http\Controllers\CoinSeller\CoinSellerPortalController;
use App\Http\Controllers\Admin\CoinSellerController;
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

// ── Coin Seller Portal ───────────────────────────────────────────────────────────
Route::prefix('coin-seller')->name('coin_seller.')->group(function () {
    Route::get ('login',  [CoinSellerPortalController::class, 'loginForm'])->name('login');
    Route::post('login',  [CoinSellerPortalController::class, 'login'])->name('login.post');
    Route::post('logout', [CoinSellerPortalController::class, 'logout'])->name('logout');

    Route::middleware(\App\Http\Middleware\CoinSellerAuthenticate::class)->group(function () {
        Route::get ('/',                          [CoinSellerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get ('users',                      [CoinSellerPortalController::class, 'users'])->name('users');
        Route::post('users/{id}/add-coins',       [CoinSellerPortalController::class, 'addCoins'])->name('users.add_coins');
        Route::get ('transactions',               [CoinSellerPortalController::class, 'transactions'])->name('transactions');
        Route::post('update-profile',              [CoinSellerPortalController::class, 'updateProfile'])->name('update_profile');
    });
});

// ── Agency Portal ────────────────────────────────────────────────────────────────
Route::prefix('agency-portal')->name('agency.')->group(function () {

    Route::get ('login',  [AgencyPortalController::class, 'loginForm'])->name('login');
    Route::post('login',  [AgencyPortalController::class, 'login'])->name('login.post');
    Route::post('logout', [AgencyPortalController::class, 'logout'])->name('logout');

    Route::middleware(\App\Http\Middleware\AgencyAuthenticate::class)->group(function () {
        Route::get('/',        [AgencyPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('members',  [AgencyPortalController::class, 'members'])->name('members');
        Route::get('requests', [AgencyPortalController::class, 'requests'])->name('requests');
        Route::post('requests/{id}/approve', [AgencyPortalController::class, 'approve'])->name('requests.approve');
        Route::post('requests/{id}/reject',  [AgencyPortalController::class, 'reject'])->name('requests.reject');
        Route::post('members/{id}/remove',   [AgencyPortalController::class, 'removeMember'])->name('members.remove');
    });
});

// ── Admin Auth ────────────────────────────────────────────────────────────────
// ── Public legal pages ──────────────────────────────────────────────────────
Route::get ('/privacy-policy',         [LegalController::class, 'privacy'])->name('privacy');
Route::get ('/terms-conditions',        [LegalController::class, 'terms'])->name('terms');
Route::get ('/delete-account',          [LegalController::class, 'deleteAccount'])->name('delete.account');
Route::post('/delete-account',          [LegalController::class, 'deleteAccountSubmit'])->name('delete.account.submit');
Route::get ('/delete-account/success',  [LegalController::class, 'deleteAccountSuccess'])->name('delete.account.success');

Route::prefix('admin')->name('admin.')->group(function () {

    Route::get ('login',  [DashboardController::class, 'loginForm'])->name('login');
    Route::post('login',  [DashboardController::class, 'login'])->name('login.post');
    Route::post('logout', [DashboardController::class, 'logout'])->name('logout');

    // ── Protected ─────────────────────────────────────────────────────────────
    Route::middleware(\App\Http\Middleware\AdminAuthenticate::class)->group(function () {

        // Dashboard
        Route::get('/',               [DashboardController::class, 'index'])->name('dashboard');
        Route::get('api/live-rooms',  [DashboardController::class, 'liveRoomsJson'])->name('api.live-rooms');

        // Users
        Route::get ('users',               [UserController::class, 'index'])->name('users.index');
        // Level sync — must be BEFORE users/{id} to avoid wildcard match
        Route::post('users/sync-levels', [SyncUserLevelsController::class, 'sync'])->name('users.sync_levels');
        Route::get ('users/level-stats', [SyncUserLevelsController::class, 'stats'])->name('users.level_stats');

        Route::get ('users/{id}',          [UserController::class, 'show'])->name('users.show');
        Route::put ('users/{id}/role',     [UserController::class, 'updateRole'])->name('users.role');
        Route::post('users/{id}/coins',    [UserController::class, 'adjustCoins'])->name('users.coins');
        Route::post('users/{id}/credit-reward', [UserController::class, 'creditMissedReward'])->name('users.credit_reward');
        Route::post('users/{id}/toggle',   [UserController::class, 'toggleActive'])->name('users.toggle');

        // Bans
        Route::get ('bans',                [BanController::class, 'index'])->name('bans.index');
        Route::post('bans',                [BanController::class, 'store'])->name('bans.store');
        Route::post('bans/unban',          [BanController::class, 'unban'])->name('bans.unban');
        Route::get ('bans/history/{id}',   [BanController::class, 'history'])->name('bans.history');

        // Rooms
        Route::get ('rooms',               [RoomController::class, 'index'])->name('rooms.index');
        Route::post('rooms/{id}/force-off', [RoomController::class, 'forceOff'])->name('rooms.force_off');
        Route::get ('rooms/{id}',          [RoomController::class, 'show'])->name('rooms.show');
        Route::post('rooms/{id}/end',      [RoomController::class, 'endRoom'])->name('rooms.end');

        // Backgrounds
        Route::get   ('backgrounds',                   [BackgroundController::class, 'index'])->name('backgrounds.index');
        Route::post  ('backgrounds/upload-url',        [BackgroundController::class, 'uploadUrl'])->name('backgrounds.upload_url');
        Route::post  ('backgrounds',                   [BackgroundController::class, 'store'])->name('backgrounds.store');
        Route::post  ('backgrounds/{id}/toggle',       [BackgroundController::class, 'toggle'])->name('backgrounds.toggle');
        Route::delete('backgrounds/{id}',              [BackgroundController::class, 'destroy'])->name('backgrounds.destroy');

        // Frames
        Route::get   ('frames',                        [FrameController::class, 'index'])->name('frames.index');
        Route::post  ('frames/upload-url',             [FrameController::class, 'uploadUrl'])->name('frames.upload_url');
        Route::post  ('frames',                        [FrameController::class, 'store'])->name('frames.store');
        Route::post  ('frames/{id}/toggle',            [FrameController::class, 'toggle'])->name('frames.toggle');
        Route::delete('frames/{id}',                   [FrameController::class, 'destroy'])->name('frames.destroy');
        Route::post  ('frames/give/{userId}',          [FrameController::class, 'giveToUser'])->name('frames.give');
        Route::post  ('frames/remove/{userId}',        [FrameController::class, 'removeFromUser'])->name('frames.remove');
        Route::get   ('frames/user/{userId}',          [FrameController::class, 'userFrames'])->name('frames.user');
        // Level Frames
        Route::get   ('level-frames',           [LevelFrameController::class, 'index'])->name('level_frames.index');
        Route::post  ('level-frames/upload-url',[LevelFrameController::class, 'uploadUrl'])->name('level_frames.upload_url');
        Route::post  ('level-frames',           [LevelFrameController::class, 'store'])->name('level_frames.store');
        Route::post  ('level-frames/{id}/toggle',[LevelFrameController::class, 'toggle'])->name('level_frames.toggle');
        Route::delete('level-frames/{id}',      [LevelFrameController::class, 'destroy'])->name('level_frames.destroy');

        // Salary sheet
        Route::get('salary',          [SalaryController::class, 'index'])->name('salary.index');
        Route::get('salary/download', [SalaryController::class, 'download'])->name('salary.download');

        Route::get   ('frames/all',                    fn() => response()->json(\App\Models\Frame::where('is_active', true)->orderBy('sort_order')->get(['id','name','price'])))->name('frames.all');

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

        // Coin Sellers
        Route::get   ('coin-sellers',                    [CoinSellerController::class, 'index'])->name('coin_sellers.index');
        Route::post  ('coin-sellers',                    [CoinSellerController::class, 'store'])->name('coin_sellers.store');
        Route::post  ('coin-sellers/{id}/add-coins',     [CoinSellerController::class, 'addCoins'])->name('coin_sellers.add_coins');
        Route::post  ('coin-sellers/{id}/toggle',        [CoinSellerController::class, 'toggleActive'])->name('coin_sellers.toggle');
        Route::delete('coin-sellers/{id}',               [CoinSellerController::class, 'destroy'])->name('coin_sellers.destroy');
        Route::post  ('coin-sellers/give-to-user',       [CoinSellerController::class, 'giveCoinsToUser'])->name('coin_sellers.give_to_user');
        Route::get   ('coin-sellers/transactions',       [CoinSellerController::class, 'transactions'])->name('coin_sellers.transactions');

        // Banners
        Route::get   ('banners',              [BannerController::class, 'index'])->name('banners.index');
        Route::post  ('banners',              [BannerController::class, 'store'])->name('banners.store');
        Route::post  ('banners/{id}/toggle',  [BannerController::class, 'toggleActive'])->name('banners.toggle');
        Route::delete('banners/{id}',         [BannerController::class, 'destroy'])->name('banners.destroy');

        // Agencies
        Route::get   ('agencies',                   [AgencyController::class, 'index'])->name('agencies.index');
        Route::post  ('agencies',                   [AgencyController::class, 'store'])->name('agencies.store');
        Route::put   ('agencies/{id}',              [AgencyController::class, 'update'])->name('agencies.update');
        Route::post  ('agencies/{id}/regenerate',   [AgencyController::class, 'regenerateCode'])->name('agencies.regenerate');
        Route::delete('agencies/{id}',              [AgencyController::class, 'destroy'])->name('agencies.destroy');
        Route::get   ('agencies/{id}/salary-sheet', [AgencyController::class, 'salarySheet'])->name('agencies.salary_sheet');



        // Settings
        Route::get ('settings',            [SettingsController::class, 'index'])->name('settings.index');
        Route::post('settings',            [SettingsController::class, 'update'])->name('settings.update');
    });
});

// Redirect root to admin
Route::get('/', fn () => redirect('/admin/login'));

// Deep link redirect — opens app when user taps shared live link
// Falls back to app store if app not installed
Route::get('/live/{roomId}', function (string $roomId) {
    $appScheme  = "kinglive://live/{$roomId}";
    $playStore  = "https://play.google.com/store/apps/details?id=com.kinglive.app";
    $appStore   = "https://apps.apple.com/app/king-live/id000000000";

    return response()->view('deeplink', compact('appScheme', 'playStore', 'appStore', 'roomId'));
});