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
        Schema::create('daily_ticket_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->references('id')->on('tickets')->onDelete('cascade')->onUpdate('cascade');
            // limit quantity work by visit date
            $table->integer('limit_quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_ticket_limits');
    }
};
