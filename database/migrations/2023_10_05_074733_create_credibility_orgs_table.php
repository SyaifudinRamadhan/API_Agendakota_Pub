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
        Schema::create('credibility_orgs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->references('id')->on('organizations')->onUpdate('cascade')->onDelete('cascade');
            $table->string("type_legality");
            $table->string('company_name');
            $table->string('business_entity');
            $table->string('pic_name');
            $table->string('pic_nic');
            $table->string('pic_nic_image');
            $table->string('company_phone');
            $table->string('tax_id_number');
            $table->string('tax_image');
            $table->string('address');
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credibility_orgs');
    }
};
