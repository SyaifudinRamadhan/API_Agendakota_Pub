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
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->string('type');
            $table->string('name');
            $table->string('slug');
            $table->string('photo');
            $table->string('banner');
            $table->string('interest');
            $table->string('phone');
            $table->string('email');
            $table->string('linkedin')->nullable();
            $table->string('instagram')->nullbale();
            $table->string('twitter')->nullable();
            $table->string('whatsapp');
            $table->string('website')->nullable();
            $table->text('desc');
            $table->boolean('allow_create_inv')->default(false);
            $table->integer('create_inv_quota')->default(0);
            $table->integer('deleted');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
