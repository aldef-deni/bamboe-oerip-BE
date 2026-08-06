<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promo extends Model
{
    protected $fillable = [
        'code',
        'description',
        'image',
        'type',
        'value',
        'min_spend',
        'max_discount',
        'quota',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
        'is_popup',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_spend' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_popup' => 'boolean',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Promo yang sedang aktif & layak ditampilkan.
     */
    public function scopeActive($query)
    {
        $today = now()->toDateString();

        // Bandingkan per-tanggal agar promo berlaku penuh sepanjang hari mulai & hari kedaluwarsa
        // (menghindari bug "expires_at 00:00" yang membuat promo langsung dianggap kedaluwarsa).
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $today))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', $today))
            ->where(fn ($q) => $q->whereNull('quota')->orWhereColumn('used_count', '<', 'quota'));
    }

    /**
     * Kembalikan pesan error bila promo tidak berlaku untuk subtotal ini, atau null bila valid.
     */
    public function validationError(float $subtotal): ?string
    {
        if (! $this->is_active) {
            return 'Kode promo tidak aktif.';
        }
        $today = now()->startOfDay();
        if ($this->starts_at && $today->lt($this->starts_at->copy()->startOfDay())) {
            return 'Kode promo belum berlaku.';
        }
        if ($this->expires_at && $today->gt($this->expires_at->copy()->startOfDay())) {
            return 'Kode promo sudah kedaluwarsa.';
        }
        if ($this->quota !== null && $this->used_count >= $this->quota) {
            return 'Kuota kode promo sudah habis.';
        }
        if ($this->min_spend && $subtotal < (float) $this->min_spend) {
            return 'Minimal transaksi Rp ' . number_format((float) $this->min_spend, 0, ',', '.') . ' untuk memakai promo ini.';
        }
        return null;
    }

    public function discountFor(float $subtotal): float
    {
        if ($this->type === 'percentage') {
            $discount = $subtotal * (float) $this->value / 100;
            if ($this->max_discount) {
                $discount = min($discount, (float) $this->max_discount);
            }
        } else {
            $discount = (float) $this->value;
        }

        return round(min($discount, $subtotal), 2);
    }
}
