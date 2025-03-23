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
        Schema::create('failed_trx_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('mail_target');
            $table->string('mail_sec_target')->nullable();
            $table->text('fn_path');
            $table->string('type');
            $table->longText('str_data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_trx_notifications');
    }
};
