<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'yandex_business_id',
        'yandex_url',
        'name',
        'average_rating',
        'ratings_count',
        'reviews_count',
        'last_parsed_at',
    ];

    protected $casts = [
        'average_rating' => 'float',
        'last_parsed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function parseRuns(): HasMany
    {
        return $this->hasMany(ParseRun::class);
    }

    public function latestParseRun(): ?ParseRun
    {
        return $this->parseRuns()->latest('id')->first();
    }
}
