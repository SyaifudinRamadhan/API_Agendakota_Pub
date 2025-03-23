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
        Schema::create('slug_customs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->references('id')->on('events')->unDelete('cascade')->onUpdate('cascade');
            $table->string('slug_custom', 212);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slug_customs');
    }
};
