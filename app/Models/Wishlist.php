<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    protected $fillable = ['user_id', 'villa_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class);
    }
}
