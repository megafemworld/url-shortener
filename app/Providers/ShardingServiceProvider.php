<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\UrlClick;

class ShardingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        UrlClick::creating(function ($model) {
            // Use consistent hashing to determine which shard to use
            // For simplicity, we'll just use the ID modulo the number of shards
            $shardNumber = ($model->shortened_url_id % 2) + 1;
            $model->setConnection("analytics_shard_{$shardNumber}");
        });
        
        UrlClick::retrieved(function ($model) {
            // When retrieving models, we need to determine which shard they belong to
            $shardNumber = ($model->shortened_url_id % 2) + 1;
            $model->setConnection("analytics_shard_{$shardNumber}");
        });
    }
}