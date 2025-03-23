<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProfitSetting;

class ProfitSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'ticket_commision' => 0.03,
            'admin_fee_trx' => 3000,
            'admin_fee_wd' => 0,
            'mul_pay_gate_fee' => 2,
            'tax_fee' => 0.11,
        ];
        ProfitSetting::create($data);
    }
}
