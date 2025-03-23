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
        Schema::create('voucher_tikets', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('voucher_id')->references('id')->on('vouchers')->onDelete('cascade')->onUpdate('cascade');
            $table->foreignUuid('ticket_id')->references('id')->on('tickets')->onDelete('cascade')->onUpdate('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_tikets');
    }
};
