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
        Schema::create('limit_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('event_id')->references('id')->on('events')->onDelete('cascade')->onUpdate('cascade');
            $table->integer('limit_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('limit_reschedules');
    }
};
