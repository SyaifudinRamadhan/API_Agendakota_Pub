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
        Schema::create('receptionists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->references('id')->on('events')->unDelete('cascade')->onUpdate('cascade');
            $table->string('name');
            $table->string('photo');
            $table->string('email');
            $table->string('phone');
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('twitter')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receptionists');
    }
};
