<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AgoraService;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    public function __construct(private readonly AgoraService $agora) {}

    /**
     * Returns live app config including Agora App ID.
     * Called by Flutter on startup — no auth required (App ID is public).
     * The App Certificate/Secret is NEVER sent to the frontend.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'agora_app_id'        => $this->agora->getAppId(),
            'platform_name'       => Setting::get('platform_name', 'King Live Pro'),
            'app_latest_version'  => Setting::get('app_latest_version', '1.0.0'),
            'app_min_version'     => Setting::get('app_min_version', '1.0.0'),
            'app_android_url'     => Setting::get('app_android_url', ''),
            'app_ios_url'         => Setting::get('app_ios_url', ''),
            'app_maintenance_mode'=> Setting::get('app_maintenance_mode', false),
            'app_maintenance_message' => Setting::get('app_maintenance_message', ''),
        ]);
    }
}
