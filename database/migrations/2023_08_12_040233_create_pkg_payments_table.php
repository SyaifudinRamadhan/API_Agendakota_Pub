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
        Schema::create('pkg_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->references('id')->on('events')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignUuid('pkg_id')->references('id')->on('pkg_pricings')->onUpdate('cascade')->onDelete('cascade');
            $table->string('token_trx');
            $table->string('pay_state');
            $table->string('order_id');
            $table->string('price');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pkg_payments');
    }
};
