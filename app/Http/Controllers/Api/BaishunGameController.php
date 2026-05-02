<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * BaishunGame integration controller.
 *
 * Our server acts as middleware between Flutter and BaishunGame.
 *
 * Flutter flow:
 *  1. GET  /api/game/config?uid=X&timestamp=T&sign=S  → returns config + code + game list
 *  2. GET  /api/game/sstoken?code=X                   → exchanges code for ss_token
 *
 * BaishunGame callbacks (no auth):
 *  3. POST /api/v1/api/get_user_info   → return user info + coin balance
 *  4. POST /api/v1/api/change_balance  → deduct/credit coins
 *  5. POST /api/v1/api/game_report     → log game events
 */
class BaishunGameController extends Controller
{
    // BaishunGame credentials
    private const APP_ID      = '9287143082';
    private const APP_KEY     = 'TGjOiwnwVn51NEKpckNnzm6HIDvwh7Rc';
    private const APP_CHANNEL = 'kinglive';
    private const BASE_URL    = 'https://game-center-test.jieyou.shop';
    private const GSP         = 101; // Singapore server

    // Our own channel key — used to verify Flutter requests
    // Flutter signs with: md5(APP_CHANNEL_KEY + uid + timestamp)
    private const APP_CHANNEL_KEY = 'TGjOiwnwVn51NEKpckNnzm6HIDvwh7Rc';

    private const COIN_ICON = 'https://d2kcubbsllg59u.cloudfront.net/logo.png';

    // ── Game list (from config — same as their baishun.php game_list) ─────────
    private const GAME_LIST = [
        ['id' => 1022, 'name' => 'FishingStar',
         'icon' => 'https://game-center-test.jieyou.shop/game-icons/0c47decda96e37b30c4efcf020d67489.png',
         'url'  => 'https://game-center-test.jieyou.shop/game-packages/common-web/fishing/4.1.9/web-mobile/index.html',
         'status' => 1],
        ['id' => 1083, 'name' => 'CrazyFruit',
         'icon' => 'https://game-center-test.jieyou.shop/game-icons/8e8fc65fc2b8b5e74a0634496df7d2a3.png',
         'url'  => 'https://game-center-test.jieyou.shop/game-packages/common-web/lucky-fruit/1.1.9/web-mobile/index.html',
         'status' => 1],
        ['id' => 1041, 'name' => 'TeenPatti',
         'icon' => 'https://game-center-test.jieyou.shop/game-icons/54915048dd2a44b11b7166c188deb074.png',
         'url'  => 'https://game-center-test.jieyou.shop/game-packages/common-web/teenpatti/1.4.4/web-mobile/index.html',
         'status' => 1],
        ['id' => 1006, 'name' => 'Greedy',
         'icon' => 'https://game-center-test.jieyou.shop/game-icons/530b538e3d7ae6bcf1b78a942fd56546.png',
         'url'  => 'https://game-center-test.jieyou.shop/game-packages/common-web/greedy/1.3.3/web-mobile/index.html',
         'status' => 1],
        ['id' => 1051, 'name' => 'Roulette',
         'icon' => 'https://game-center-test.jieyou.shop/game-icons/f59abcd97c04.png',
         'url'  => 'https://game-center-test.jieyou.shop/game-packages/common-web/roulette/1.0.0/web-mobile/index.html',
         'status' => 1],
    ];

    // ── 1. Flutter → us: get game config + code ───────────────────────────────

    public function getConfig(Request $request): JsonResponse
    {
        $uid       = $request->query('uid') ?? (string) auth()->id();
        $timestamp = $request->query('timestamp', (string) time());
        $sign      = $request->query('sign', '');
        $roomId    = $request->query('room_id', 0);
        $avatarUrl = $request->query('avatar_url', '');

        // Verify sign: md5(APP_CHANNEL_KEY + uid + timestamp)
        $localSign = md5(self::APP_CHANNEL_KEY . $uid . $timestamp);
        if ($sign && $localSign !== $sign) {
            return response()->json(['code' => 1003, 'msg' => 'Sign verification failed']);
        }

        // Verify user exists
        $user = User::find((int) $uid);
        if (! $user) {
            return response()->json(['code' => 1002, 'msg' => 'User not found']);
        }

        // Generate code and cache it
        $code = md5(time() . $uid);
        Cache::put('baishun_code_' . $code, $uid, 3600);

        // Game list (filter active only)
        // $games = array_values(array_filter(
        //     self::GAME_LIST,
        //     fn ($g) => ($g['status'] ?? 0) === 1
        // ));
        
        $games = Cache::remember('baishun_games', 60, function () {
            return \App\Models\Game::active()
                ->get()
                ->map(fn ($game) => [
                    'id'     => (int) $game->game_id,
                    'name'   => $game->name,
                    'icon'   => $game->thumbnail_url,
                    'url'    => $game->url,
                    'status' => $game->is_active ? 1 : 0,
                ])
                ->values()
                ->toArray();
        });

        $data = [
            'appChannel' => self::APP_CHANNEL,
            'appId'      => self::APP_ID,
            'userId'     => (string) $uid,
            'code'       => $code,
            'roomId'     => (int) $roomId,
            'gameMode'   => 2, // 2 = half screen
            'language'   => 2,
            'gameConfig' => [
                'sceneMode'    => 0,
                'currencyIcon' => self::COIN_ICON,
            ],
            'gsp'   => self::GSP,
            'lists' => $games,
        ];

        return response()->json(['code' => 200, 'msg' => 'success', 'data' => $data]);
    }

    // ── 2. Flutter → us: exchange code for ss_token ───────────────────────────

    // public function getSsToken(Request $request): JsonResponse
    // {
    //      Log::info("raw request sstoken", [
    //         'data' => $request->all()
    //     ]);
        
    //     $code = $request->query('code', '');
    //     $uid  = Cache::get('baishun_code_' . $code);

    //     if (! $uid) {
    //         return response()->json(['error' => 'Invalid or expired code'], 400);
    //     }

    //     $nonce     = bin2hex(random_bytes(8));
    //     $timestamp = time();
    //     $sign      = md5($nonce . self::APP_KEY . $timestamp);

    //     $response = Http::withHeaders([
    //         'Content-Type' => 'application/json',
    //         'app-channel'  => self::APP_CHANNEL,
    //         'app-id'       => self::APP_ID,
    //     ])->post(self::BASE_URL . '/api/v1/get_sstoken', [
    //         'app_id'           => (int) self::APP_ID,
    //         'user_id'          => (string) $uid,
    //         'code'             => $code,
    //         'signature'        => $sign,
    //         'signature_nonce'  => $nonce,
    //         'timestamp'        => $timestamp,
    //     ]);

    //     if (! $response->successful()) {
    //         Log::info('BAISHUN RAW RESPONSE', [
    //             'status' => $response->status(),
    //             'body'   => $response->body(),
    //         ]);
    //         return response()->json(['error' => "Baishun error"], 500);
    //     }

    //     $data = $response->json();
    //     if (($data['code'] ?? -1) !== 0) {
    //         return response()->json(['error' => $data['message'] ?? 'Error from BaishunGame'], 500);
    //     }

    //     return response()->json($data['data']);
    // }
    
    public function getSsToken(Request $request): JsonResponse
    {
        Log::info("raw request sstoken", [
            'raw' => $request->getContent(),
            'json' => $request->json()->all(),
        ]);
    
        // ✅ Parse JSON body
        // $code = data_get($request->json()->all(), 'data.code');
        // $uid  = data_get($request->json()->all(), 'data.user_id');
        
        $payload = $request->json()->all();

        $code = $payload['code'] ?? null;
        $uid  = $payload['user_id'] ?? null;
    
        if (! $code || ! $uid) {
            Log::info("raw request _1", [
                'code' => 1001,
                'message' => 'Invalid payload'
            ]);
            return response()->json([
                'code' => 1001,
                'message' => 'Invalid payload'
            ]);
        }
    
        // ✅ Check cache
        $cachedUid = Cache::get('baishun_code_' . $code);
    
        if (! $cachedUid) {
            
            Log::info("raw request _2", [
                'code' => 1001,
                'message' => 'code invalid'
            ]);
            return response()->json([
                'code' => 1001,
                'message' => 'code invalid'
            ]);
        }
    
        Cache::forget('baishun_code_' . $code);
    
        // ✅ Generate token
        $ssToken = \Tymon\JWTAuth\Facades\JWTAuth::fromUser(
            \App\Models\User::find($cachedUid),
            ['site' => $request->getHost()]
        );
    
        $expireDate = (time() + config('jwt.ttl')) * 1000;
    
        return response()->json([
            'code' => 0,
            'message' => 'succeed',
            'data' => [
                'ss_token'    => $ssToken,
                'expire_date' => $expireDate,
            ]
        ]);
    }
        
        private function generateSsToken($uid): string
        {
            return \Tymon\JWTAuth\Facades\JWTAuth::fromUser(
                \App\Models\User::find($uid),
                [
                    'site' => request()->getHost()
                ]
            );
        }

    // ── 3. BaishunGame → us: verify signature ────────────────────────────────

    private function checkSign(array $data): bool
    {
        $localSign = md5(($data['signature_nonce'] ?? '') . self::APP_KEY . ($data['timestamp'] ?? ''));
        return $localSign === ($data['signature'] ?? '');
    }

    // ── 4. BaishunGame → us: get user info ───────────────────────────────────

    public function getUserInfo(Request $request): JsonResponse
    {
        // if (! $this->checkSign($request->all())) {
        //     return response()->json(['code' => 1003, 'message' => 'Sign error']);
        // }
        Log::info("raw request", [
            'data' => $request->all()
        ]);
        $userId = $request->input('user_id');
        $user   = User::find((int) $userId);

        if (! $user) {
            return response()->json(['code' => 1002, 'message' => 'User not found']);
        }

        return response()->json([
            'code'    => 0,
            'message' => 'succeed',
            'data'    => [
                'user_id'     => (string) $user->id,
                'user_name'   => $user->display_name ?? $user->username,
                'user_avatar' => $user->avatar_url   ?? '',
                'balance'     => (float) $user->coin_balance,
            ],
            'unique_id' => strval(time().rand(10000,99999)),
        ]);
    }

    // ── 5. BaishunGame → us: change balance ──────────────────────────────────

    public function changeBalance(Request $request): JsonResponse
    {
        if (! $this->checkSign($request->all())) {
            return response()->json(['code' => 1003, 'message' => 'Sign error']);
        }

        $userId  = $request->input('user_id');
        $diff    = (int) $request->input('currency_diff', 0);
        $gameId  = $request->input('game_id');
        $orderId = $request->input('order_id', Str::uuid());
        $roomId  = $request->input('room_id');
        $diffMsg = $request->input('diff_msg', 'bet');

        // Idempotency — same order_id won't process twice
        $cacheKey = "game_order:{$orderId}";
        if (Cache::has($cacheKey)) {
            $balance = User::find((int) $userId)?->coin_balance ?? 0;
            return response()->json([
                'code' => 0, 'message' => 'succeed',
                'data' => ['currency_balance' => (float) $balance],
                'unique_id' => strval(time().rand(10000,99999)),
            ]);
        }

        try {
            $newBalance = DB::transaction(function () use ($userId, $diff, $gameId, $orderId, $roomId, $diffMsg) {
                $user = User::lockForUpdate()->find((int) $userId);

                if (! $user) throw new \Exception('User not found', 1002);

                if ($diff < 0 && $user->coin_balance < abs($diff)) {
                    throw new \Exception('Coins is not enough', 1008);
                }

                $user->increment('coin_balance', $diff);
                $user->refresh();

                CoinTransaction::create([
                    'user_id'      => $user->id,
                    'type'         => $diff < 0 ? 'game_bet' : 'game_reward',
                    'amount'       => $diff,
                    'balance_after'=> $user->coin_balance,
                    'reference'    => "game:{$gameId}:order:{$orderId}:room:{$roomId}",
                ]);

                return $user->coin_balance;
            });

            Cache::put($cacheKey, true, now()->addDay());

            return response()->json([
                'code'    => 0,
                'message' => 'succeed',
                'data'    => ['currency_balance' => (float) $newBalance],
                'unique_id' => strval(time().rand(10000,99999)),
            ]);

        } catch (\Exception $e) {
            Log::error("BaishunGame changeBalance: {$e->getMessage()} user={$userId}");
            return response()->json([
                'code'    => $e->getCode() ?: 500,
                'message' => $e->getMessage(),
            ]);
        }
    }

    // ── 6. BaishunGame → us: game status report ───────────────────────────────

    public function gameReport(Request $request): JsonResponse
    {
        Log::info('BaishunGame report: ' . json_encode($request->all()));
        return response()->json(['code' => 0, 'message' => 'succeed', 'unique_id' => strval(time().rand(10000,99999))]);
    }
}
