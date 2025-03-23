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
        Schema::create('pkg_pricings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('description');
            // $table->integer('event_same_time');
            $table->double('ticket_commission');
            $table->integer('session_count');
            $table->boolean('custom_link');
            $table->integer('sponsor_count');
            $table->integer('exhibitor_count');
            $table->integer('partner_media_count');
            $table->boolean('report_download');
            $table->integer('max_attachment');
            $table->integer('price');
            $table->integer('deleted');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pkg_pricings');
    }
};
