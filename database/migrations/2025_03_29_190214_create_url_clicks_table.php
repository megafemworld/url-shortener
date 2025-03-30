<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUrlClicksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('url_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shortened_url_id')->constrained()->onDelete('cascade');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('operating_system', 50)->nullable();
            $table->text('referer')->nullable();
            $table->unsignedInteger('shard_id')->default(0);
            $table->timestamps();
            
            // Indexes for analytics queries
            $table->index(['shortened_url_id', 'created_at']);
            $table->index('device_type');
            $table->index('browser');
            $table->index('shard_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('url_clicks');
    }
}