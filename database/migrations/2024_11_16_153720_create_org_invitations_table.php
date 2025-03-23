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
        Schema::create('org_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('pch_id')->references('id')->on('purchases')->onDelete('cascade')->onUpdate('cascade');
            $table->string('email');
            $table->string('wa_num');
            $table->string('name');
            $table->text('trx_img')->nullable();
            $table->boolean('seen')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_invitations');
    }
};
