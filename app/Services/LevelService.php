<?php

namespace App\Services;

class LevelService
{
    /**
     * Level thresholds in coins (total lifetime sending).
     * Key = level, Value = coins required to reach that level.
     */
    public static array $thresholds = [
        1   => 0,
        2   => 50_000,
        3   => 100_000,
        4   => 300_000,
        5   => 400_000,
        6   => 500_000,
        7   => 700_000,
        8   => 1_000_000,
        9   => 1_500_000,
        10  => 1_700_000,
        11  => 2_000_000,
        12  => 2_500_000,
        13  => 3_000_000,
        14  => 3_500_000,
        15  => 4_000_000,
        16  => 4_500_000,
        17  => 5_000_000,
        18  => 5_500_000,
        19  => 6_000_000,
        20  => 6_500_000,
        21  => 7_000_000,
        22  => 7_500_000,
        23  => 8_000_000,
        24  => 9_000_000,
        25  => 10_000_000,
        26  => 11_000_000,
        27  => 12_000_000,
        28  => 13_000_000,
        29  => 14_000_000,
        30  => 15_000_000,
        31  => 16_000_000,
        32  => 17_000_000,
        33  => 18_000_000,
        34  => 19_000_000,
        35  => 20_000_000,
        36  => 21_000_000,
        37  => 22_000_000,
        38  => 23_000_000,
        39  => 24_000_000,
        40  => 25_000_000,
        41  => 26_000_000,
        42  => 27_000_000,
        43  => 28_000_000,
        44  => 29_000_000,
        45  => 30_000_000,
        46  => 31_000_000,
        47  => 32_000_000,
        48  => 33_000_000,
        49  => 34_000_000,
        50  => 35_000_000,
        51  => 36_000_000,
        52  => 37_000_000,
        53  => 38_000_000,
        54  => 39_000_000,
        55  => 40_000_000,
        56  => 42_000_000,
        57  => 44_000_000,
        58  => 46_000_000,
        59  => 48_000_000,
        60  => 50_000_000,
        61  => 52_000_000,
        62  => 54_000_000,
        63  => 56_000_000,
        64  => 58_000_000,
        65  => 60_000_000,
        66  => 63_000_000,
        67  => 66_000_000,
        68  => 69_000_000,
        69  => 72_000_000,
        70  => 75_000_000,
        71  => 78_000_000,
        72  => 81_000_000,
        73  => 84_000_000,
        74  => 87_000_000,
        75  => 90_000_000,
        76  => 94_000_000,
        77  => 98_000_000,
        78  => 102_000_000,
        79  => 104_000_000,
        80  => 106_000_000,
        81  => 108_000_000,
        82  => 110_000_000,
        83  => 112_000_000,
        84  => 113_000_000,
        85  => 114_000_000,
        86  => 115_000_000,
        87  => 116_000_000,
        88  => 117_000_000,
        89  => 118_000_000,
        90  => 119_000_000,
        91  => 120_000_000,
        92  => 121_000_000,
        93  => 122_000_000,
        94  => 123_000_000,
        95  => 124_000_000,
        96  => 125_000_000,
        97  => 126_000_000,
        98  => 127_000_000,
        99  => 128_000_000,
        100 => 129_000_000,
        101 => 130_000_000,
        102 => 131_000_000,
        103 => 132_000_000,
        104 => 133_000_000,
        105 => 134_000_000,
        106 => 135_000_000,
        107 => 136_000_000,
        108 => 137_000_000,
        109 => 138_000_000,
        110 => 139_000_000,
        111 => 140_000_000,
        112 => 141_000_000,
        113 => 142_000_000,
        114 => 143_000_000,
        115 => 144_000_000,
        116 => 145_000_000,
        117 => 146_000_000,
        118 => 147_000_000,
        119 => 148_000_000,
        120 => 149_000_000,
        121 => 150_000_000,
        122 => 151_000_000,
        123 => 152_000_000,
        124 => 153_000_000,
        125 => 154_000_000,
        126 => 155_000_000,
        127 => 156_000_000,
        128 => 157_000_000,
        129 => 158_000_000,
        130 => 159_000_000,
        131 => 160_000_000,
        132 => 161_000_000,
        133 => 162_000_000,
        134 => 163_000_000,
        135 => 164_000_000,
        136 => 165_000_000,
        137 => 166_000_000,
        138 => 167_000_000,
        139 => 168_000_000,
        140 => 169_000_000,
        141 => 170_000_000,
        142 => 171_000_000,
        143 => 172_000_000,
        144 => 173_000_000,
        145 => 174_000_000,
        146 => 175_000_000,
        147 => 176_000_000,
        148 => 177_000_000,
        149 => 178_000_000,
        150 => 179_000_000,
        151 => 180_000_000,
        152 => 181_000_000,
        153 => 182_000_000,
        154 => 183_000_000,
        155 => 184_000_000,
        156 => 185_000_000,
        157 => 186_000_000,
        158 => 190_000_000,
        159 => 210_000_000,
        160 => 230_000_000,
        161 => 250_000_000,
        162 => 270_000_000,
        163 => 290_000_000,
        164 => 310_000_000,
        165 => 320_000_000,
        166 => 350_000_000,
        167 => 370_000_000,
        168 => 400_000_000,
        169 => 430_000_000,
        170 => 450_000_000,
        171 => 470_000_000,
        172 => 490_000_000,
        173 => 520_000_000,
        174 => 550_000_000,
        175 => 600_000_000,
        176 => 650_000_000,
        177 => 700_000_000,
        178 => 700_000_000, // max
    ];

    public static function getMaxLevel(): int
    {
        return max(array_keys(static::$thresholds));
    }

    /**
     * Calculate level from total coins sent.
     * Never decreases — always pick highest level whose threshold is met.
     */
    public static function calculate(int $totalCoinsSent): int
    {
        $level = 1;
        foreach (static::$thresholds as $lvl => $required) {
            if ($totalCoinsSent >= $required) {
                $level = $lvl;
            } else {
                break;
            }
        }
        return $level;
    }

    /**
     * Coins needed to reach next level from current total.
     */
    public static function coinsToNextLevel(int $totalCoinsSent): ?int
    {
        $maxLevel = static::getMaxLevel();
        foreach (static::$thresholds as $lvl => $required) {
            if ($totalCoinsSent < $required) {
                return $required - $totalCoinsSent;
            }
        }
        return null; // max level reached
    }

    /**
     * Update user level after sending coins.
     * Returns new level if it changed, null if unchanged.
     */
    // Tier boundary levels — when crossing one of these, auto-grant the level frame
    public static array $tierBoundaries = [1, 20, 40, 60, 80, 100, 120, 140, 160];

    public static function updateUserLevel(\App\Models\User $user, int $coinsSpent): ?int
    {
        $oldLevel = (int) $user->level;
        $user->increment('total_coins_sent', $coinsSpent);
        $user->refresh();

        $newLevel = static::calculate($user->total_coins_sent);

        if ($newLevel > $oldLevel) {
            $user->update(['level' => $newLevel]);

            // Check if user crossed a tier boundary — auto-grant level frame
            foreach (static::$tierBoundaries as $boundary) {
                if ($oldLevel < $boundary && $newLevel >= $boundary) {
                    static::grantTierFrame($user, $boundary);
                }
            }

            return $newLevel;
        }

        return null;
    }

    /**
     * Auto-grant the LevelFrame for a tier to the user's inventory and apply it.
     * Creates a matching Frame record if one doesn't exist yet.
     */
    private static function grantTierFrame(\App\Models\User $user, int $tierMin): void
    {
        // Find the tier's max from $tierBoundaries
        $boundaries  = static::$tierBoundaries;
        $idx         = array_search($tierMin, $boundaries);
        $tierMax     = ($idx !== false && isset($boundaries[$idx + 1]))
            ? $boundaries[$idx + 1] - 1
            : 999;

        // Find the LevelFrame for this tier
        $levelFrame = \App\Models\LevelFrame::where('is_active', true)
            ->where('min_level', '>=', $tierMin)
            ->where('min_level', '<=', $tierMax)
            ->orderBy('min_level')
            ->first();

        if (! $levelFrame) return;

        // Find or create a matching Frame record (keyed by svga_url)
        $frame = \App\Models\Frame::firstOrCreate(
            ['svga_url' => $levelFrame->svga_url],
            [
                'name'          => $levelFrame->name,
                'thumbnail_url' => $levelFrame->thumbnail_url,
                'price'         => 0,
                'is_active'     => true,
                'sort_order'    => 9999,
            ]
        );

        // Add to user's inventory if not already there
        \App\Models\UserFrame::firstOrCreate(
            ['user_id' => $user->id, 'frame_id' => $frame->id],
            ['source'  => 'level_up']
        );

        // Auto-apply — new tier frame replaces the old one automatically
        $user->update(['frame_url' => $levelFrame->svga_url]);

        \Illuminate\Support\Facades\Log::info(
            "LevelFrame granted: user={$user->id} tier={$tierMin} frame={$frame->id}"
        );
    }
}