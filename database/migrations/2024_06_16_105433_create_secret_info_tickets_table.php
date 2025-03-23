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
        Schema::create('secret_info_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->references('id')->on('tickets')->onDelete('cascade')->onUpdate('cascade');
            $table->string('meet_link');
            $table->text('desc');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secret_info_tickets');
    }
};
