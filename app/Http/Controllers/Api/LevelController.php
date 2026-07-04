<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LevelFrame;
use App\Services\LevelService;
use Illuminate\Http\JsonResponse;

class LevelController extends Controller
{
    public function info(): JsonResponse
    {
        $user           = auth()->user();
        $currentLevel   = (int) $user->level;
        $totalCoinsSent = (int) $user->total_coins_sent;
        $maxLevel       = LevelService::getMaxLevel();

        // Current level threshold
        $currentThreshold = LevelService::$thresholds[$currentLevel] ?? 0;

        // Next level threshold
        $nextLevel     = min($currentLevel + 1, $maxLevel);
        $nextThreshold = LevelService::$thresholds[$nextLevel] ?? null;

        // Progress within current level
        $coinsIntoLevel = $totalCoinsSent - $currentThreshold;
        $coinsForLevel  = $nextThreshold !== null
            ? $nextThreshold - $currentThreshold
            : 0;
        $progress = ($coinsForLevel > 0)
            ? min(1.0, $coinsIntoLevel / $coinsForLevel)
            : 1.0;

        // Coins needed to reach next level
        $coinsToNext = $nextThreshold !== null
            ? max(0, $nextThreshold - $totalCoinsSent)
            : null;

        // Level frame for current level
        $frame = LevelFrame::forLevel($currentLevel);

        // All level milestones with frame info (for the level page grid)
        $milestones = [];
        $tiers = [
            ['min' => 1,   'max' => 19,  'label' => 'Seedling', 'emoji' => '🌱', 'color' => '#27AE60'],
            ['min' => 20,  'max' => 39,  'label' => 'Explorer', 'emoji' => '💙', 'color' => '#3498DB'],
            ['min' => 40,  'max' => 59,  'label' => 'Rising',   'emoji' => '💜', 'color' => '#9B59B6'],
            ['min' => 60,  'max' => 79,  'label' => 'Champion', 'emoji' => '👑', 'color' => '#FFD700'],
            ['min' => 80,  'max' => 99,  'label' => 'Elite',    'emoji' => '🏆', 'color' => '#FF8C00'],
            ['min' => 100, 'max' => 119, 'label' => 'Master',   'emoji' => '⚡', 'color' => '#FF6600'],
            ['min' => 120, 'max' => 139, 'label' => 'Legend',   'emoji' => '🌋', 'color' => '#FF4500'],
            ['min' => 140, 'max' => 159, 'label' => 'Warlord',  'emoji' => '💀', 'color' => '#CC0000'],
            ['min' => 160, 'max' => 178, 'label' => 'God',      'emoji' => '🔥', 'color' => '#FF0000'],
        ];

        foreach ($tiers as $tier) {
            // Show frame ONLY if its min_level falls within this tier's [min, max] range
            // This prevents a frame from showing in multiple tier boxes
            $tierFrame = LevelFrame::where('is_active', true)
                ->where('min_level', '>=', $tier['min'])
                ->where('min_level', '<=', $tier['max'])
                ->orderByDesc('min_level')
                ->first();
            $milestones[] = [
                'min_level'     => $tier['min'],
                'max_level'     => $tier['max'],
                'label'         => $tier['label'],
                'emoji'         => $tier['emoji'],
                'color'         => $tier['color'],
                'unlocked'      => $currentLevel >= $tier['min'],
                'current'       => $currentLevel >= $tier['min'] && $currentLevel <= $tier['max'],
                'frame_svga'    => $tierFrame?->svga_url,
                'frame_thumb'   => $tierFrame?->thumbnail_url,
            ];
        }

        return response()->json([
            'current_level'      => $currentLevel,
            'max_level'          => $maxLevel,
            'total_coins_sent'   => $totalCoinsSent,
            'current_threshold'  => $currentThreshold,
            'next_level'         => $nextLevel,
            'next_threshold'     => $nextThreshold,
            'coins_to_next'      => $coinsToNext,
            'progress'           => round($progress, 4),
            'coins_into_level'   => $coinsIntoLevel,
            'coins_for_level'    => $coinsForLevel,
            'level_frame'        => $frame ? [
                'svga_url'      => $frame->svga_url,
                'thumbnail_url' => $frame->thumbnail_url,
                'name'          => $frame->name,
            ] : null,
            'milestones'         => $milestones,
        ]);
    }
}