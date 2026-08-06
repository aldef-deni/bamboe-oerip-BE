<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

class LoyaltyTierService
{
    public static function defaultTiers(): array
    {
        return [
            [
                'slug' => 'bronze',
                'name' => 'Bronze',
                'min_points' => 0,
                'max_points' => 999,
                'color' => '#CD7F32',
                'gradient_from' => '#CD7F32',
                'gradient_to' => '#8B4513',
                'icon' => 'medal',
                'multiplier' => 1.0,
                'benefits' => [
                    'Poin dasar dari setiap pemesanan',
                    'Akses promo eksklusif member',
                ],
            ],
            [
                'slug' => 'silver',
                'name' => 'Silver',
                'min_points' => 1000,
                'max_points' => 4999,
                'color' => '#C0C0C0',
                'gradient_from' => '#C0C0C0',
                'gradient_to' => '#808080',
                'icon' => 'award',
                'multiplier' => 1.1,
                'benefits' => [
                    'Bonus poin +10% dari setiap pemesanan',
                    'Prioritas layanan customer service',
                    'Early access promo spesial',
                ],
            ],
            [
                'slug' => 'gold',
                'name' => 'Gold',
                'min_points' => 5000,
                'max_points' => 14999,
                'color' => '#FFD700',
                'gradient_from' => '#FFD700',
                'gradient_to' => '#DAA520',
                'icon' => 'crown',
                'multiplier' => 1.25,
                'benefits' => [
                    'Bonus poin +25% dari setiap pemesanan',
                    'Upgrade kamar gratis (subject to availability)',
                    'Welcome drink saat check-in',
                    'Diskon 5% untuk pemesanan berikutnya',
                ],
            ],
            [
                'slug' => 'platinum',
                'name' => 'Platinum',
                'min_points' => 15000,
                'max_points' => null,
                'color' => '#E5E4E2',
                'gradient_from' => '#E5E4E2',
                'gradient_to' => '#71797E',
                'icon' => 'gem',
                'multiplier' => 1.5,
                'benefits' => [
                    'Bonus poin +50% dari setiap pemesanan',
                    'Personal concierge service',
                    'Late check-out gratis',
                    'Diskon 10% untuk pemesanan berikutnya',
                    'Undangan event eksklusif Villa Box',
                ],
            ],
        ];
    }

    public static function getTiers(): array
    {
        $stored = Setting::get('loyalty_tiers');
        if ($stored) {
            $decoded = json_decode($stored, true);
            if (is_array($decoded) && count($decoded) >= 4) {
                return $decoded;
            }
        }

        return static::defaultTiers();
    }

    /**
     * Berapa rupiah untuk memperoleh 1 poin (mis. 10000 = Rp10.000 per 1 poin).
     * Bisa diatur admin lewat setting 'loyalty_rupiah_per_point'.
     */
    public static function getRupiahPerPoint(): int
    {
        return max(1, (int) Setting::get('loyalty_rupiah_per_point', '10000'));
    }

    public static function getLifetimePoints(User $user): int
    {
        return (int) $user->loyaltyPoints()
            ->whereIn('type', ['earn', 'adjust'])
            ->sum('points');
    }

    public static function resolveTier(int $lifetimePoints, ?array $tiers = null): array
    {
        $tiers = $tiers ?? static::getTiers();
        $current = $tiers[0];

        foreach ($tiers as $tier) {
            if ($lifetimePoints >= ($tier['min_points'] ?? 0)) {
                $current = $tier;
            }
        }

        return $current;
    }

    public static function getNextTier(array $currentTier, ?array $tiers = null): ?array
    {
        $tiers = $tiers ?? static::getTiers();
        $found = false;

        foreach ($tiers as $tier) {
            if ($found) {
                return $tier;
            }
            if (($tier['slug'] ?? '') === ($currentTier['slug'] ?? '')) {
                $found = true;
            }
        }

        return null;
    }

    public static function getUserTierInfo(User $user): array
    {
        $tiers = static::getTiers();
        $lifetimePoints = static::getLifetimePoints($user);
        $currentTier = static::resolveTier($lifetimePoints, $tiers);

        // Override manual oleh admin (bila diisi & valid)
        $isOverride = false;
        if (! empty($user->tier_override)) {
            foreach ($tiers as $t) {
                if (($t['slug'] ?? '') === $user->tier_override) {
                    $currentTier = $t;
                    $isOverride = true;
                    break;
                }
            }
        }

        $nextTier = static::getNextTier($currentTier, $tiers);
        $progress = 100;
        $pointsToNext = 0;

        if ($nextTier && ! $isOverride) {
            $rangeStart = $currentTier['min_points'] ?? 0;
            $rangeEnd = $nextTier['min_points'];
            $range = $rangeEnd - $rangeStart;
            $progress = $range > 0
                ? min(100, (int) round((($lifetimePoints - $rangeStart) / $range) * 100))
                : 0;
            $pointsToNext = max(0, $rangeEnd - $lifetimePoints);
        }

        // Tier ditetapkan manual → tampil sebagai tier tetap
        if ($isOverride) {
            $nextTier = null;
            $progress = 100;
            $pointsToNext = 0;
        }

        return [
            'lifetime_points' => $lifetimePoints,
            'current_tier' => $currentTier,
            'next_tier' => $nextTier,
            'progress_percent' => $progress,
            'points_to_next_tier' => $pointsToNext,
            'is_override' => $isOverride,
            'all_tiers' => $tiers,
        ];
    }

    public static function saveTiers(array $tiers): void
    {
        Setting::put('loyalty_tiers', json_encode($tiers, JSON_UNESCAPED_UNICODE));
    }
}
