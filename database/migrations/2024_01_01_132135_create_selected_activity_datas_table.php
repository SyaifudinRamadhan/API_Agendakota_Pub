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
        Schema::create('selected_activity_datas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('selected_activity_id')->references('id')->on('selected_activities')->onDelete('CASCADE')->onUpdate('CASCADE');
            $table->foreignUuid("event_id")->references('id')->on("events")->onDelete('CASCADE')->onUpdate('CASCADE');
            $table->integer('priority');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selected_activity_datas');
    }
};
