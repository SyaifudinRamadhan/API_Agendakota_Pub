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
        Schema::create('profit_settings', function (Blueprint $table) {
            $table->id();
            $table->float('ticket_commision'); // commision percentage of ticket (ex: 3% / ticket)
            $table->integer('admin_fee_trx'); // ticket transaction fee every user buy ticket. This fee is fixed per transaction group of ticket (ex: Rp. 3000,- / transaction)
            $table->integer('admin_fee_wd'); // ticket transaction fee every user buy ticket with certain pay method. This fee is fixed per transaction group of ticket (ex: Rp. 3000,- / transaction if using VA Bank, other it, is free)
            $table->float('mul_pay_gate_fee'); // multiplication of payment gateway fee (ex: for VA Bank xendit fee / transaction is Rp. 4000,-. So you can multiply 4000 with mul_pay_gate_fee value for example)
            $table->float('tax_fee'); // percentage of tax fee (ex: ppn 11%)
            $table->float('wa_quota_price_inv')->default(0); // price (in rupiah's) of whatsapp service / invitation has sent
            $table->float('wa_quota_price_ntf')->default(0); // price (in rupiah's) of whatsapp service / notification has sent
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profit_settings');
    }
};
