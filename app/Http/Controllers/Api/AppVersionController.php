<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    /**
     * GET /api/app/version
     *
     * Called by the Flutter app on launch (before auth).
     * Returns version config so the app can decide whether to force update.
     */
    public function check(Request $request): JsonResponse
    {
        $currentVersion = $request->header('X-App-Version')
                        ?? $request->query('v', '0.0.0');
        $platform       = $request->header('X-App-Platform')
                        ?? $request->query('p', 'android');

        $latestVersion  = Setting::get('app_latest_version', '1.0.0');
        $minVersion     = Setting::get('app_min_version',    '1.0.0');
        $maintenance    = Setting::get('app_maintenance_mode', '0') === '1';
        $maintMsg       = Setting::get('app_maintenance_message',
                              'We are under maintenance. Please check back soon.');

        $updateUrl = $platform === 'ios'
            ? Setting::get('app_ios_url',     '')
            : Setting::get('app_android_url', '');

        $forceUpdate    = $this->versionLessThan($currentVersion, $minVersion);
        $updateAvailable = $this->versionLessThan($currentVersion, $latestVersion);

        return response()->json([
            'latest_version'   => $latestVersion,
            'min_version'      => $minVersion,
            'current_version'  => $currentVersion,
            'force_update'     => $forceUpdate,
            'update_available' => $updateAvailable,
            'update_url'       => $updateUrl,
            'update_title'     => Setting::get('app_update_title', 'Update Available'),
            'update_message'   => Setting::get('app_update_message',
                                     'A new version is available. Please update to continue.'),
            'maintenance'      => $maintenance,
            'maintenance_message' => $maintMsg,
        ]);
    }

    /**
     * Compare semantic versions. Returns true if $a < $b.
     * e.g. versionLessThan('1.1.0', '1.2.0') → true
     */
    private function versionLessThan(string $a, string $b): bool
    {
        $aParts = array_map('intval', explode('.', $a));
        $bParts = array_map('intval', explode('.', $b));

        // Pad to same length
        while (count($aParts) < 3) $aParts[] = 0;
        while (count($bParts) < 3) $bParts[] = 0;

        for ($i = 0; $i < 3; $i++) {
            if ($aParts[$i] < $bParts[$i]) return true;
            if ($aParts[$i] > $bParts[$i]) return false;
        }
        return false; // equal
    }
}