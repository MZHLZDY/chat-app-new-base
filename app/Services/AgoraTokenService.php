<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

// load library agora
require_once app_path('Libraries/Agora/RtcTokenBuilder.php');
require_once app_path('Libraries/Agora/AccessToken.php');

class AgoraTokenService
{
    private string $appId;
    private string $appCertificate;

    public function __construct()
    {
        $this->appId = config('services.agora.app_id');
        $this->appCertificate = config('services.agora.app_certificate');
    }

    /** 
     * Generate Agora RTC Token
     * 
     * @param string $channelName Channel name (unique per call)
     * @param int $uid User ID (caller_id atau callee_id)
     * @param string $role 'publisher' (bisa publish audio/video) atau 'subscriber' (hanya terima)
     * @param int $expireTimeInSeconds Token Expire dalam detik (default: 3600 = 1 jam)
     * @return string Agora RTC Token
    */

    public function generateRtcToken(
        string $channelName,
        int $uid,
        string $role = 'publisher',
        int $expireTime = 3600
    ): string {
        $currentTimestamp = time();
        $privillegeExpiredTs = $currentTimestamp + $expireTime;

        // role constants dari RtcTokenBuilder
        // 1 = RolePublisher, 2 = RoleSubscriber
        $roleValue = $role === 'publisher'
            ? \RtcTokenBuilder::RolePublisher
            : \RtcTokenBuilder::RoleSubscriber;

            \Log::info('🔑 Membuat token Agora', [
                'channel_name' => $channelName,
                'uid' => $uid,
                'uid_type' => gettype($uid),
            ]);
        
        $token = \RtcTokenBuilder::buildTokenWithUid(
            $this->appId,
            $this->appCertificate,
            $channelName,
            $uid,
            $roleValue,
            $privillegeExpiredTs
        );

        \Log::info('✅ Token Agora berhasil dibuat', [
            'uid' => $uid,
            'token_first_50' => substr($token, 0, 50),
            'token_length' => strlen($token),
        ]);

        return $token;
    }

    /** 
     * Generate token untuk caller & callee sekaligus
     * 
     * @param string $channelName
     * @param int $callerId
     * @param int $calleeId
     * @return array
    */
    public function generateTokensForCall(
        string $channelName,
        int $callerId,
        int $calleeId
    ): array {
        return [
            'caller_token' => $this->generateRtcToken($channelName, $callerId, 'publisher'),
            'callee_token' => $this->generateRtcToken($channelName, $calleeId, 'publisher'),
            'channel_name' => $channelName,
            'app_id' => $this->appId,
            'expire_time' => 3600,
        ];
    }

    /** 
     * Validate apakah agora credentials sudah di set
     * 
     * @return bool
    */
    public function isConfigured(): bool
    {
        return !empty($this->appId) &&
                !empty($this->appCertificate) &&
                $this->appCertificate !== 'YOUR_APP_CERTIFICATE' &&
                strlen($this->appId) > 10 && // App ID minimal 10 karakter
                strlen($this->appCertificate) > 10; // App Certificate minimal 10 karakter

    }

    /** 
     * Get App ID (untuk Frontend)
     * 
     * @return string
    */
    public function getAppId(): string
    {
        return $this->appId;
    }

    public static function generateToken($appId, $appCertificate, $channelName, $uid, $expireTime = 3600)
    {
        $currentTimestamp = time();
        $privillegeExpiredTs = $currentTimestamp + $expireTime;

        return \RtcTokenBuilder::buildTokenWithUid(
            $appId,
            $appCertificate,
            $channelName,
            $uid,
            \RtcTokenBuilder::RolePublisher,
            $privillegeExpiredTs
        );
    }
}