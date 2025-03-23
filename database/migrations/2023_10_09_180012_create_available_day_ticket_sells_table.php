<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('available_day_ticket_sells', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->references('id')->on('events')->onUpdate('cascade')->onDelete('cascade');
            $table->string('day');
            $table->time('start_time')->default('07:00:00');
            $table->time('max_limit_time')->default('23:59:00');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('available_day_ticket_sells');
    }
};
