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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('f_name');
            $table->string('l_name');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('g_id')->nullable();
            $table->string('photo');
            $table->string('is_active');
            $table->string('phone');
            $table->longText('interest')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('instagram')->nullbale();
            $table->string('twitter')->nullable();
            $table->string('whatsapp');
            $table->boolean('two_factor')->default(false);
            $table->integer("deleted")->default(0);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
