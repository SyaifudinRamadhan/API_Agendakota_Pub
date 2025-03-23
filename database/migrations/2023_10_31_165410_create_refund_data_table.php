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
        Schema::create('refund_data', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('purchase_id')->nullable()->references('id')->on('purchases')->onDelete('set null')->onUpdate('cascade');
            $table->foreignUuid('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreignUuid('ticket_id')->references('id')->on('tickets')->onDelete('cascade')->onUpdate('cascade');
            $table->foreignUuid('event_id')->references('id')->on('events')->onDelete('cascade')->onUpdate('cascade');
            $table->string('bank_code');
            $table->string('account_name');
            $table->string('account_number');
            $table->string('phone_number');
            $table->text('message');
            $table->float('percentage')->default(100.0);
            $table->integer('nominal');
            $table->integer('basic_nominal');
            $table->boolean('approve_org')->default(false);
            $table->boolean('approve_admin')->default(false);
            $table->boolean('finish')->default(false);
            $table->string('mode')->default('auto');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_data');
    }
};
