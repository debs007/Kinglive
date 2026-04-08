<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Get a platform setting value.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('format_coins')) {
    /**
     * Format a coin number for display (e.g. 1200 -> "1.2K").
     */
    function format_coins(int $amount): string
    {
        if ($amount >= 1_000_000) {
            return round($amount / 1_000_000, 1) . 'M';
        }

        if ($amount >= 1_000) {
            return round($amount / 1_000, 1) . 'K';
        }

        return (string) $amount;
    }
}
