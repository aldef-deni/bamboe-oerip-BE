<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LoyaltyPoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    /**
     * Berikan poin untuk sebuah pesanan yang sudah dibayar/dikonfirmasi.
     * Idempoten: satu pesanan hanya menghasilkan satu catatan 'earn'.
     * Poin = floor(total / rupiah_per_point) * multiplier tier saat ini.
     */
    public static function awardForBooking(Booking $booking): ?LoyaltyPoint
    {
        if (! $booking->user_id) {
            return null;
        }

        // Sudah pernah diberi poin untuk pesanan ini?
        $already = LoyaltyPoint::where('booking_id', $booking->id)
            ->where('type', 'earn')
            ->exists();
        if ($already) {
            return null;
        }

        $rupiahPerPoint = LoyaltyTierService::getRupiahPerPoint();
        $basePoints = (int) floor(((float) $booking->total) / $rupiahPerPoint);
        if ($basePoints < 1) {
            return null;
        }

        $user = $booking->user;
        if (! $user) {
            return null;
        }

        // Multiplier dari tier user saat ini (berdasarkan poin lifetime sebelum penambahan ini)
        $lifetime = LoyaltyTierService::getLifetimePoints($user);
        $tier = LoyaltyTierService::resolveTier($lifetime);
        $multiplier = (float) ($tier['multiplier'] ?? 1.0);
        $points = (int) round($basePoints * $multiplier);
        if ($points < 1) {
            return null;
        }

        return DB::transaction(function () use ($user, $booking, $points) {
            $currentEarned = $user->loyaltyPoints()
                ->where(function ($q) {
                    $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
                })
                ->where('type', 'earn')
                ->sum('points');
            $currentSpent = $user->loyaltyPoints()->where('type', 'spend')->sum('points');
            $balanceAfter = ($currentEarned + $points) - $currentSpent;

            return LoyaltyPoint::create([
                'user_id' => $user->id,
                'booking_id' => $booking->id,
                'points' => $points,
                'type' => 'earn',
                'description' => "Poin dari pesanan {$booking->booking_code}",
                'balance_after' => $balanceAfter,
                'expired_at' => null,
            ]);
        });
    }
}
