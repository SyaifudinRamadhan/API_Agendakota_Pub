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
        Schema::create('event_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->references('id')->on('events')->onDelete('cascade')->onUpdate('cascade');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('desc');
            $table->string('cover');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_sessions');
    }
};
