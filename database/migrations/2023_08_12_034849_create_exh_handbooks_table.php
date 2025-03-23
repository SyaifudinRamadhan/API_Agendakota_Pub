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
        Schema::create('exh_handbooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exh_id')->references('id')->on('exhibitors')->unDelete('cascade')->onUpdate('cascade');
            $table->text('file_name');
            $table->text('slug');
            $table->string('type_file');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exh_handbooks');
    }
};
