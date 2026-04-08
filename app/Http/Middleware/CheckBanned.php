<?php

namespace App\Http\Middleware;

use App\Services\BanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBanned
{
    public function __construct(private BanService $banService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $this->banService->isGloballyBanned($user->id)) {
            $ban = $user->activeBan();

            return response()->json([
                'message'    => 'Your account has been suspended.',
                'reason'     => $ban?->reason,
                'expires_at' => $ban?->expires_at,
            ], 403);
        }

        return $next($request);
    }
}
