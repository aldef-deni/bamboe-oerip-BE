<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyPoint extends Model
{
    protected $fillable = [
        'user_id',
        'booking_id',
        'points',
        'type',
        'description',
        'balance_after',
        'expired_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
        'expired_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopeEarned($query)
    {
        return $query->where('type', 'earn');
    }

    public function scopeSpent($query)
    {
        return $query->where('type', 'spend');
    }

    public function scopeExpired($query)
    {
        return $query->where('type', 'expire');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
    }
}