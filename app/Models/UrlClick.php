<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UrlClick extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped
     * 
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignedable
     * 
     * @var array<int, string>
     */
    protected $fillable = [
        'shortened_url_id',
        'visitor_ip',
        'user_agent',
        'referer',
        'country',
        'city',
        'device_type',
        'browser',
        'pltform',
        'clicked_at',
    ];

    /**
     * The attributes that should be cast.
     * 
     * @var array<string, string>
     */
    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    /**
     * Get the shortend URL that the click belongs to.
     */
    public function shortenedUrl()
    {
        return $this->belongaTo(shortenedUrl::class);
    }
}
