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
        Schema::create('special_day_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('special_day_id')->references('id')->on('special_days')->onDelete('cascade')->onUpdate('cascade');
            $table->foreignUuid('event_id')->references('id')->on('events')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('priority');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_day_events');
    }
};
