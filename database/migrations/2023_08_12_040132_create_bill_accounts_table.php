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
        Schema::create('bill_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->references('id')->on('organizations')->onUpdate('cascade')->onDelete('cascade');
            $table->string('bank_name');
            $table->string('acc_name');
            $table->string('acc_number');
            $table->boolean('status')->default(false);
            $table->string('otp');
            $table->dateTime('exp_to_verify');
            $table->integer('deleted')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_accounts');
    }
};
