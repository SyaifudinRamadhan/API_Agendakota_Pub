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
        Schema::create('reserved_seats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pch_id')->references('id')->on('purchases')->onUpdate('cascade')->onDelete('cascade');
            $table->integer('seat_number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reserved_seats');
    }
};
