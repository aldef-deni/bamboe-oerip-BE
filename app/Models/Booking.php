<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\LoyaltyPoint;

class Booking extends Model
{
    protected $fillable = [
        'booking_code',
        'user_id',
        'villa_id',
        'promo_id',
        'promo_code',
        'check_in',
        'check_out',
        'nights',
        'guests',
        'guest_name',
        'guest_phone',
        'guest_email',
        'price_per_night',
        'subtotal',
        'service_fee',
        'discount',
        'total',
        'status',
        'notes',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'villa_id' => 'integer',
        'promo_id' => 'integer',
        'check_in' => 'date',
        'check_out' => 'date',
        'price_per_night' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public const ACTIVE_STATUSES = ['pending', 'awaiting_verification', 'confirmed', 'completed'];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->booking_code)) {
                $booking->booking_code = static::generateCode();
            }
        });

        // Earning poin dipusatkan di App\Services\LoyaltyService (idempoten,
        // dipicu saat pembayaran diverifikasi / status confirmed|completed).
    }

    public static function generateCode(): string
    {
        do {
            $code = 'VB' . now()->format('ymd') . strtoupper(Str::random(4));
        } while (static::where('booking_code', $code)->exists());

        return $code;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class);
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function loyaltyPoints(): HasMany
    {
        return $this->hasMany(LoyaltyPoint::class);
    }
}
