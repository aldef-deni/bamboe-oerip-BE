<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'phone',
        'avatar',
        'google_id',
        'google_avatar',
        'role',
        'is_active',
        'tier_override',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['avatar_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Pemilik properti (akses ekstranet owner). */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /** Properti milik owner ini. */
    public function ownedVillas(): HasMany
    {
        return $this->hasMany(Villa::class, 'owner_id');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }

        // Fallback ke foto profil Google (URL remote) bila ada
        return $this->google_avatar ?: null;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function loyaltyPoints(): HasMany
    {
        return $this->hasMany(LoyaltyPoint::class);
    }

    public function getTotalPointsAttribute(): int
    {
        return $this->loyaltyPoints()
            ->where(function ($q) {
                $q->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            ->where('type', 'earn')
            ->sum('points');
    }
}
