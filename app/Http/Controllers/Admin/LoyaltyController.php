<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyPoint;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoyaltyTierService;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    /**
     * Ambil konfigurasi tier + rupiah per poin (untuk halaman pengaturan superadmin).
     */
    public function getTiers()
    {
        return response()->json([
            'tiers' => LoyaltyTierService::getTiers(),
            'rupiah_per_point' => LoyaltyTierService::getRupiahPerPoint(),
        ]);
    }

    /**
     * Simpan konfigurasi 4 tier + rupiah per poin.
     */
    public function saveTiers(Request $request)
    {
        $data = $request->validate([
            'rupiah_per_point' => ['required', 'integer', 'min:1'],
            'tiers' => ['required', 'array', 'size:4'],
            'tiers.*.slug' => ['required', 'string', 'in:bronze,silver,gold,platinum'],
            'tiers.*.name' => ['required', 'string', 'max:50'],
            'tiers.*.min_points' => ['required', 'integer', 'min:0'],
            'tiers.*.max_points' => ['nullable', 'integer', 'min:0'],
            'tiers.*.multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'tiers.*.color' => ['nullable', 'string', 'max:20'],
            'tiers.*.gradient_from' => ['nullable', 'string', 'max:20'],
            'tiers.*.gradient_to' => ['nullable', 'string', 'max:20'],
            'tiers.*.icon' => ['nullable', 'string', 'max:30'],
            'tiers.*.benefits' => ['nullable', 'array'],
            'tiers.*.benefits.*' => ['string', 'max:255'],
        ]);

        // Urutkan berdasarkan min_points agar resolusi tier konsisten
        $tiers = collect($data['tiers'])->sortBy('min_points')->values()->all();

        LoyaltyTierService::saveTiers($tiers);
        Setting::put('loyalty_rupiah_per_point', (string) $data['rupiah_per_point']);

        return response()->json([
            'message' => 'Pengaturan tier loyalitas disimpan.',
            'tiers' => LoyaltyTierService::getTiers(),
            'rupiah_per_point' => LoyaltyTierService::getRupiahPerPoint(),
        ]);
    }

    public function index(Request $request)
    {
        $query = LoyaltyPoint::with(['user:id,name,email', 'booking:id,booking_code']);

        if ($search = $request->query('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return response()->json($query->latest()->paginate((int) $request->query('per_page', 20)));
    }

    public function stats(Request $request)
    {
        $totalEarned = LoyaltyPoint::where('type', 'earn')->sum('points');
        $totalSpent = LoyaltyPoint::where('type', 'spend')->sum('points');
        $totalExpired = LoyaltyPoint::where('type', 'expire')->sum('points');
        $activeBalance = LoyaltyPoint::where('type', 'earn')
            ->where(function ($q) {
                $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            ->sum('points') - LoyaltyPoint::where('type', 'spend')->sum('points');

        $topUsers = User::withSum(['loyaltyPoints as total_earned' => function ($q) {
            $q->where('type', 'earn');
        }], 'points')->orderByDesc('total_earned')->limit(10)->get(['id', 'name', 'email']);

        return response()->json([
            'total_earned' => $totalEarned,
            'total_spent' => $totalSpent,
            'total_expired' => $totalExpired,
            'active_balance' => $activeBalance,
            'top_users' => $topUsers,
        ]);
    }

    /**
     * Saldo poin tersedia = poin earn aktif + penyesuaian manual − yang dibelanjakan.
     */
    private function availableBalance(User $user): int
    {
        $earn = $user->loyaltyPoints()
            ->where(function ($q) {
                $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            ->where('type', 'earn')
            ->sum('points');

        $adjust = $user->loyaltyPoints()->where('type', 'adjust')->sum('points');
        $spent = $user->loyaltyPoints()->where('type', 'spend')->sum('points');

        return (int) ($earn + $adjust - $spent);
    }

    /**
     * Ringkasan loyalti seorang pelanggan (untuk panel kelola manual).
     */
    public function userInfo(User $user)
    {
        $info = LoyaltyTierService::getUserTierInfo($user);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'balance' => $this->availableBalance($user),
            'lifetime_points' => $info['lifetime_points'],
            'current_tier' => $info['current_tier'],
            'is_override' => $info['is_override'] ?? false,
            'tier_override' => $user->tier_override,
        ]);
    }

    /**
     * Input poin manual (tambah / kurang) untuk seorang pelanggan.
     */
    public function adjust(Request $request, User $user)
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'direction' => ['nullable', 'in:add,subtract'],
        ]);

        $signed = ($data['direction'] ?? 'add') === 'subtract'
            ? -abs($data['points'])
            : abs($data['points']);

        $balanceAfter = $this->availableBalance($user) + $signed;

        $point = LoyaltyPoint::create([
            'user_id' => $user->id,
            'booking_id' => null,
            'points' => $signed,
            'type' => 'adjust',
            'description' => $data['description'],
            'balance_after' => $balanceAfter,
            'expired_at' => null,
        ]);

        return response()->json([
            'message' => $signed >= 0
                ? abs($signed) . ' poin ditambahkan ke ' . $user->name . '.'
                : abs($signed) . ' poin dikurangi dari ' . $user->name . '.',
            'point' => $point,
            'balance' => $balanceAfter,
        ], 201);
    }

    /**
     * Tetapkan / kembalikan tier pelanggan secara manual.
     */
    public function setTier(Request $request, User $user)
    {
        $data = $request->validate([
            'tier' => ['nullable', 'in:bronze,silver,gold,platinum'],
        ]);

        $user->tier_override = $data['tier'] ?: null;
        $user->save();

        return response()->json([
            'message' => $user->tier_override
                ? 'Tier pelanggan ditetapkan manual ke ' . ucfirst($user->tier_override) . '.'
                : 'Tier dikembalikan otomatis (mengikuti poin).',
            'tier_override' => $user->tier_override,
        ]);
    }
}