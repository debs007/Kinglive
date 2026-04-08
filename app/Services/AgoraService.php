<?php

namespace App\Services;

use Exception;

/**
 * Agora RTC Token Generator (AccessToken2 spec).
 *
 * Install the official SDK:
 *   composer require agora/token-builder
 *
 * The static build methods below wrap the SDK. If you prefer not to use
 * the SDK, the manual HMAC-SHA256 implementation is included as a fallback.
 */
class AgoraService
{
    private string $appId;
    private string $appCertificate;
    private int    $tokenExpiry;

    public function __construct()
    {
        $this->appId          = config('services.agora.app_id', '');
        $this->appCertificate = config('services.agora.app_certificate', '');
        $this->tokenExpiry    = (int) config('services.agora.token_expiry', 21600); // 6 h
    }

    /**
     * Generate an Agora RTC channel token.
     *
     * @param  string  $channelName  The Agora channel name
     * @param  int     $uid          Numeric user ID (0 = wildcard)
     * @param  string  $role         'publisher' | 'subscriber'
     */
    public function generateToken(string $channelName, int $uid, string $role = 'publisher'): string
    {
        if (empty($this->appId) || empty($this->appCertificate)) {
            throw new Exception('Agora App ID or Certificate is not configured.');
        }

        $privilegeExpiredTs = time() + $this->tokenExpiry;

        // ── Using agora/token-builder SDK ─────────────────────────────────
        if (class_exists('\AgoraIO\Media\RtcTokenBuilder')) {
            $agoraRole = $role === 'publisher'
                ? \AgoraIO\Media\RtcTokenBuilder::RolePublisher
                : \AgoraIO\Media\RtcTokenBuilder::RoleSubscriber;

            return \AgoraIO\Media\RtcTokenBuilder::buildTokenWithUid(
                appID:              $this->appId,
                appCertificate:     $this->appCertificate,
                channelName:        $channelName,
                uid:                $uid,
                role:               $agoraRole,
                privilegeExpiredTs: $privilegeExpiredTs,
            );
        }

        // ── Manual fallback (AccessToken v1) ──────────────────────────────
        return $this->buildTokenManually($channelName, $uid, $role, $privilegeExpiredTs);
    }

    /**
     * Generate an Agora RTM token for a user (messaging channel).
     */
    public function generateRtmToken(int $uid): string
    {
        if (class_exists('\AgoraIO\Rtm\RtmTokenBuilder')) {
            return \AgoraIO\Rtm\RtmTokenBuilder::buildToken(
                appID:              $this->appId,
                appCertificate:     $this->appCertificate,
                userId:             (string) $uid,
                role:               \AgoraIO\Rtm\RtmTokenBuilder::RoleRtmUser,
                privilegeExpiredTs: time() + $this->tokenExpiry,
            );
        }

        return '';
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildTokenManually(
        string $channelName,
        int $uid,
        string $role,
        int $expiredTs
    ): string {
        $roleInt    = $role === 'publisher' ? 1 : 2;
        $nonce      = random_int(1, PHP_INT_MAX);
        $timestamp  = time();
        $message    = $this->appId . $timestamp . $nonce . $uid . $channelName . $roleInt . $expiredTs;
        $signature  = hash_hmac('sha256', $message, $this->appCertificate);

        $content = $this->appId . $timestamp . $nonce . $uid . $channelName . $roleInt . $expiredTs . $signature;

        return base64_encode($content);
    }
}
