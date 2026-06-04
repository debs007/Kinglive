<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Diamond → USD Conversion Rate
    |--------------------------------------------------------------------------
    | 1000 diamonds = $1.00 USD  (rate = 0.001)
    */
    'diamond_to_usd_rate' => env('DIAMOND_TO_USD_RATE', 0.001),

    /*
    |--------------------------------------------------------------------------
    | Minimum Withdrawal Amount (diamonds)
    |--------------------------------------------------------------------------
    */
    'min_withdrawal_diamonds' => (int) env('MIN_WITHDRAWAL_DIAMONDS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Platform Gift Fee Percentage
    |--------------------------------------------------------------------------
    | Percentage of gift coins kept as platform revenue.
    | e.g. 30 = 30%  (host receives 70% as diamonds)
    */
    'gift_platform_fee_pct' => (int) env('GIFT_PLATFORM_FEE_PCT', 30),

    /*
    |--------------------------------------------------------------------------
    | Welcome Bonus Coins
    |--------------------------------------------------------------------------
    */
    'welcome_bonus_coins' => (int) env('WELCOME_BONUS_COINS', 100),

    /*
    |--------------------------------------------------------------------------
    | Live Reward Amount (diamonds)
    |--------------------------------------------------------------------------
    | Diamonds credited to host after 40+ mins of video live.
    | Change via LIVE_REWARD_DIAMONDS in .env
    */
    'live_reward_diamonds' => (int) env('LIVE_REWARD_DIAMONDS', 5000),

];