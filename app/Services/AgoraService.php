<?php

namespace App\Services;

use CyberDeep\LaravelAgoraTokenGenerator\Services\Agora;
use Exception;

class AgoraService
{
    private string $appId;

    public function __construct()
    {
        $this->appId = config('agora.app_id', env('AGORA_APP_ID', ''));
    }

    /**
     * Generate Agora RTC token using cyberdeep/laravel-agora-token-generator.
     *
     * @param  string  $channelName   Agora channel name
     * @param  int     $uid           User ID
     * @param  string  $role          'broadcaster' | 'audience'
     * @param  bool    $audioOnly     true = audio only, false = video+audio
     */
    public function generateToken(
        string $channelName,
        int    $uid,
        string $role     = 'broadcaster',
        bool   $audioOnly = false
    ): string {
        // join(false) = publisher/broadcaster
        // join(true)  = subscriber/audience
        $isSubscriber = $role === 'audience';

        return Agora::make($uid)
            ->channel($channelName)
            ->uId($uid)
            ->join($isSubscriber)          // false = broadcaster, true = audience
            ->audioOnly($audioOnly)        // false = video+audio, true = audio only
            ->token();
    }

    /**
     * Generate token for a host (broadcaster, video+audio).
     */
    public function generateHostToken(string $channelName, int $uid): string
    {
        return $this->generateToken($channelName, $uid, 'broadcaster', false);
    }

    /**
     * Generate token for a viewer (audience, video+audio).
     */
    public function generateViewerToken(string $channelName, int $uid): string
    {
        return $this->generateToken($channelName, $uid, 'audience', false);
    }

    /**
     * Generate token for audio-only broadcaster (party room seat).
     */
    public function generateAudioToken(string $channelName, int $uid): string
    {
        return $this->generateToken($channelName, $uid, 'broadcaster', true);
    }

    public function getAppId(): string
    {
        return $this->appId;
    }
}