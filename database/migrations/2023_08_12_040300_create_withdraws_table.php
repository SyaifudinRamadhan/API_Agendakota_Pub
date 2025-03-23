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
        Schema::create('withdraws', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->references('id')->on('events')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignUuid('org_id')->references('id')->on('organizations')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignUuid('bill_acc_id')->references('id')->on('bill_accounts')->onUpdate('cascade')->onDelete('cascade');
            $table->integer('nominal');
            $table->integer('commision');
            $table->integer('admin_fee_wd');
            $table->integer('basic_nominal');
            $table->integer('status')->default(0);
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
        Schema::dropIfExists('withdraws');
    }
};
