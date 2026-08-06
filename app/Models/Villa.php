<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Villa extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'city',
        'district',
        'village',
        'province',
        'address',
        'latitude',
        'longitude',
        'price_per_night',
        'weekend_price',
        'max_guests',
        'bedrooms',
        'bathrooms',
        'size_sqm',
        'rating_avg',
        'review_count',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'price_per_night' => 'decimal:2',
        'weekend_price' => 'decimal:2',
        'rating_avg' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected $appends = ['primary_image_url'];

    protected static function booted(): void
    {
        static::creating(function (Villa $villa) {
            if (empty($villa->slug)) {
                $villa->slug = static::uniqueSlug($villa->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public function images(): HasMany
    {
        return $this->hasMany(VillaImage::class)->orderBy('sort_order');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $image = $this->images->firstWhere('is_primary', true) ?? $this->images->first();
        return $image ? asset('storage/' . $image->path) : null;
    }

    public function recalculateRating(): void
    {
        $this->review_count = $this->reviews()->count();
        $this->rating_avg = $this->review_count > 0
            ? round($this->reviews()->avg('rating'), 2)
            : 0;
        $this->saveQuietly();
    }
}
