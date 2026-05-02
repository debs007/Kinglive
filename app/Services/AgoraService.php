<?php

namespace App\Services;

use App\Models\Setting;
use CyberDeep\LaravelAgoraTokenGenerator\Services\Agora;
use Illuminate\Support\Facades\Cache;

class AgoraService
{
    private string $appId;

    public function __construct()
    {
        // Override agora config from DB if values are stored there
        $appId      = Setting::get('agora_app_id');
        $cert       = Setting::get('agora_app_certificate');

        if ($appId && $cert) {
            config(['agora.app_id'      => $appId]);
            config(['agora.certificate' => $cert]);
        }

        $this->appId = config('agora.app_id', env('AGORA_APP_ID', ''));
    }

    public function generateToken(
        string $channelName,
        int    $uid,
        string $role      = 'broadcaster',
        bool   $audioOnly = false
    ): string {
        $isSubscriber = $role === 'audience';

        return Agora::make($uid)
            ->channel($channelName)
            ->uId($uid)
            ->join($isSubscriber)
            ->audioOnly($audioOnly)
            ->token();
    }

    public function generateHostToken(string $channelName, int $uid): string
    {
        return $this->generateToken($channelName, $uid, 'broadcaster', false);
    }

    public function generateViewerToken(string $channelName, int $uid): string
    {
        return $this->generateToken($channelName, $uid, 'audience', false);
    }

    public function generateAudioToken(string $channelName, int $uid): string
    {
        return $this->generateToken($channelName, $uid, 'broadcaster', true);
    }

    public function getAppId(): string
    {
        return $this->appId;
    }
}