<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyPoint;
use App\Services\LoyaltyTierService;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function balance(Request $request)
    {
        $user = $request->user();

        $activePoints = $user->loyaltyPoints()
            ->where(function ($q) {
                $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            ->where('type', 'earn')
            ->sum('points');

        $spentPoints = $user->loyaltyPoints()
            ->where('type', 'spend')
            ->sum('points');

        $tierInfo = LoyaltyTierService::getUserTierInfo($user);

        return response()->json([
            'balance' => $activePoints - $spentPoints,
            'total_earned' => $activePoints,
            'total_spent' => $spentPoints,
            'lifetime_points' => $tierInfo['lifetime_points'],
            'current_tier' => $tierInfo['current_tier'],
            'next_tier' => $tierInfo['next_tier'],
            'progress_percent' => $tierInfo['progress_percent'],
            'points_to_next_tier' => $tierInfo['points_to_next_tier'],
        ]);
    }

    public function history(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);

        $history = $request->user()->loyaltyPoints()
            ->with(['booking:id,booking_code'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($history);
    }

    public function tiers()
    {
        return response()->json([
            'tiers' => LoyaltyTierService::getTiers(),
            'rupiah_per_point' => LoyaltyTierService::getRupiahPerPoint(),
        ]);
    }
}
