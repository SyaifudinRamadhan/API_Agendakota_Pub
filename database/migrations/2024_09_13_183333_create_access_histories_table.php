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
        Schema::create('access_histories', function (Blueprint $table) {
            $table->id();
            // In future, this cient_id will used to create open API as an ID from registered client
            $table->string('client_id');
            $table->string('email')->nullable();
            $table->bigInteger('timestamp_access')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_histories');
    }
};
