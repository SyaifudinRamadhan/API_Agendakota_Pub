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
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->references('id')->on('events')->onDelete('cascade')->onUpdate('cascade');
            $table->string('name');
            $table->string('cover')->default('/storage/ticket_covers/default.png');
            $table->text('desc');
            $table->integer('type_price');
            $table->integer('price');
            $table->integer('quantity');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('seat_number')->default(false);
            $table->string('seat_map')->nullable();
            $table->integer('max_purchase');
            $table->integer('deleted');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
