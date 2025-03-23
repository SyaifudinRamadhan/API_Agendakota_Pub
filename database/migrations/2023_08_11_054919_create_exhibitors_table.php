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
        Schema::create('exhibitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->references('id')->on('events')->unDelete('cascade')->onUpdate('cascade');
            $table->string('name');
            $table->string('email');
            $table->string('category');
            $table->string('address');
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('twitter')->nullable();
            $table->string('website')->nullable();
            $table->boolean('virtual_booth')->nullable();
            $table->string('booth_link')->nullable();
            $table->string('logo');
            $table->string('booth_image');
            $table->string('phone')->nullable();
            $table->text('description');
            $table->string('video')->nullable();
            $table->boolean('overview');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exhibitors');
    }
};
