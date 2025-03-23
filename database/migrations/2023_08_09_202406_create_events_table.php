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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->references('id')->on('organizations')->onDelete('cascade')->onUpdate('cascade');
            $table->text('slug');
            $table->text('name');
            $table->text('category');
            $table->text('topics');
            $table->text('logo');
            $table->text('desc');
            $table->text('snk');
            $table->text('exe_type');
            $table->text('location');
            $table->text('province');
            $table->text('city');
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('is_publish');
            $table->text('instagram', 212)->nullable();
            $table->text('twitter', 212)->nullable();
            $table->text('website', 212)->nullable();
            $table->text('twn_url')->nullable();
            $table->text('custom_fields')->nullable();
            $table->text('seat_map')->nullable();
            $table->boolean('single_trx')->default(false);
            $table->boolean('visibility')->default(true);
            $table->boolean('allow_refund')->default(false);
            $table->integer('deleted');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
