<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyJoinRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyController extends Controller
{
    /** Send a join request to an agency using its code */
    public function requestJoin(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = auth()->user();

        // Already in an agency
        if ($user->agency_id) {
            return response()->json([
                'message' => 'You are already in an agency. Leave first.',
            ], 422);
        }

        $agency = Agency::where('code', strtoupper(trim($request->code)))
            ->where('is_active', true)
            ->first();

        if (! $agency) {
            return response()->json(['message' => 'Invalid agency code.'], 404);
        }

        // Check for existing pending/approved request
        $existing = AgencyJoinRequest::where('user_id', $user->id)
            ->where('agency_id', $agency->id)
            ->first();

        if ($existing) {
            if ($existing->status === 'pending') {
                return response()->json([
                    'message' => 'You already have a pending request for this agency.',
                    'status'  => 'pending',
                ], 422);
            }
            if ($existing->status === 'approved') {
                return response()->json([
                    'message' => 'Your request was already approved.',
                    'status'  => 'approved',
                ], 422);
            }
            // Rejected — allow re-request
            $existing->update(['status' => 'pending', 'responded_at' => null]);
        } else {
            AgencyJoinRequest::create([
                'user_id'   => $user->id,
                'agency_id' => $agency->id,
                'message'   => $request->message ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Join request sent! Waiting for agency approval.',
            'agency'  => [
                'id'       => $agency->id,
                'name'     => $agency->name,
                'logo_url' => $agency->logo_url,
            ],
        ]);
    }

    /** Check my request status */
    public function myRequest(): JsonResponse
    {
        $user    = auth()->user();
        $request = AgencyJoinRequest::where('user_id', $user->id)
            ->with('agency:id,name,logo_url,code')
            ->latest()
            ->first();

        return response()->json(['request' => $request]);
    }

    /** Leave current agency */
    public function leave(): JsonResponse
    {
        auth()->user()->update(['agency_id' => null]);
        return response()->json(['message' => 'Left agency.']);
    }

    /** Get my agency info */
    public function mine(): JsonResponse
    {
        $user   = auth()->user();
        $agency = $user->agency_id
            ? Agency::find($user->agency_id)
            : null;

        if (! $agency) {
            return response()->json(['agency' => null]);
        }

        return response()->json([
            'agency' => [
                'id'           => $agency->id,
                'name'         => $agency->name,
                'code'         => $agency->code,
                'logo_url'     => $agency->logo_url,
                'description'  => $agency->description,
                'member_count' => $agency->memberCount(),
            ],
        ]);
    }
}
