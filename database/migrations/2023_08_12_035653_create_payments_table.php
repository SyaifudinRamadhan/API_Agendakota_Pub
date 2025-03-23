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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->string('token_trx');
            $table->string('pay_state');
            $table->string('order_id');
            $table->string('price');
            $table->integer('admin_fee');
            $table->integer('platform_fee');
            $table->string('code_method')->nullable();
            $table->text('virtual_acc')->nullable();
            $table->text('qr_str')->nullable();
            $table->text('pay_links')->nullable();
            $table->dateTime('expired')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
