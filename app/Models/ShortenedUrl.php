<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShortenedUrl extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     * @var array<int, string>
     */
    protected $fillable = [
        'original_url',
        'slug',
        'user_id',
        'is_custom',
        'expires_at',
    ];
    /**
     * The attributes that should be cast to native types.
     * @var array<string, string>
     */
    protected $casts = [
        'is_custom' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the shortened URL.
     */

    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the clicks for the shortened URL.
     */

    public function clicks() {
        return $this->hasMany(UrlClick::class);
     }

    /**
     * GEt the total click count for the shortened URL
     */

    public function getClickCountAttribute()
     {
        return $this->clicks->count();
     }

     /**
      * Check if the URL has expired
      */
    
    public function isExpired()
    {
        if ($this->expires_at == null) {
            return false;
        }

        return now()->gt($this->expires_at);
    }

    /**
     * Generate a short URL
     */

    public function getShortUrlAttribute()
    {
        return url($this->slug);
    }
    
}
