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
        Schema::create('exh_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exh_id')->references('id')->on('exhibitors')->unDelete('cascade')->onUpdate('cascade');
            $table->string('name');
            $table->string('price');
            $table->string('image');
            $table->string('url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exh_products');
    }
};
