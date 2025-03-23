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
        Schema::create('log_add_inv_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('org_id')->references('id')->on('organizations')->onDelete('cascade')->onUpdate('cascade');
            $table->string('type');
            $table->integer('quota');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_add_inv_quotas');
    }
};
