<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // haloagendakota_c-2023_agdkt-2023_ifud (passwoord)
        $admin = [
            'f_name' =>  "halo",
            'l_name' => "agendakota",
            'name' => "haloagendakota",
            'email' => "halo@agendakota.id",
            'password' => bcrypt('haloagendakota_c-2023_agdkt-2023_ifud'),
            'photo' => '/storage/avatars/default.png',
            'is_active' => "1",
            'phone' => "",
            'linkedin' => "",
            'instagram' => "",
            'twitter' => "",
            'whatsapp' => "",
            "deleted" => 0,
            'two_factor' => true
        ];
        $userData = User::create($admin);
        Admin::create([
            'user_id' => $userData->id
        ]);
    }
}
