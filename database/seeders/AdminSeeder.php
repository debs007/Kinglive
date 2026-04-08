<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@kinglive.app')],
            [
                'username'     => 'admin',
                'display_name' => 'King Admin',
                'password'     => env('ADMIN_PASSWORD', 'Admin@KingLive123'),
                'role'         => 'super_admin',
                'is_verified'  => true,
                'is_active'    => true,
                'avatar_url'   => 'https://ui-avatars.com/api/?name=King+Admin&background=6C3483&color=FFD700&size=200',
            ]
        );
    }
}
