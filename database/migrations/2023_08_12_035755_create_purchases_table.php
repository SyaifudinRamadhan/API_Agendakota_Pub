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
        Schema::create('purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignUuid('pay_id')->references('id')->on('payments')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignUuid('ticket_id')->references('id')->on('tickets')->onUpdate('cascade')->onDelete('cascade');
            $table->string('code');
            $table->integer('amount');
            $table->integer('tax_amount');
            $table->boolean('is_mine')->default(false);
            $table->boolean('org_inv')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
