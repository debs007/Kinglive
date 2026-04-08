<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private int $codeLength = 6;
    private int $ttlMinutes = 10;

    public function send(string $phone): bool
    {
        $code = str_pad(random_int(0, 999999), $this->codeLength, '0', STR_PAD_LEFT);

        Cache::put("otp:{$phone}", $code, now()->addMinutes($this->ttlMinutes));

        if (app()->isProduction()) {
            return $this->sendViaSms($phone, $code);
        }

        // In development log the OTP for testing
        Log::info("OTP for {$phone}: {$code}");
        return true;
    }

    public function verify(string $phone, string $code): bool
    {
        $stored = Cache::get("otp:{$phone}");

        if ($stored && $stored === $code) {
            Cache::forget("otp:{$phone}");
            return true;
        }

        return false;
    }

    private function sendViaSms(string $phone, string $code): bool
    {
        // Integrate your SMS provider here (Twilio, Nexmo, etc.)
        // Example using Twilio:
        try {
            $response = Http::withBasicAuth(
                config('services.twilio.sid'),
                config('services.twilio.token'),
            )->post("https://api.twilio.com/2010-04-01/Accounts/".config('services.twilio.sid')."/Messages.json", [
                'From' => config('services.twilio.from'),
                'To'   => $phone,
                'Body' => "Your King Live verification code is: {$code}. Valid for {$this->ttlMinutes} minutes.",
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('OTP send failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
