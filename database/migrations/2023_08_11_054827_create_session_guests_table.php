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
        Schema::create('session_guests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->references('id')->on('event_sessions')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignUuid('guest_id')->references('id')->on('guests')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_guests');
    }
};
