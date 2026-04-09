<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // ── Super Admin ───────────────────────────────────────────────────────
        // The `role` column in the `users` table controls access:
        //   'user'        → regular app user (no admin panel access)
        //   'host'        → can go live (no admin panel access)
        //   'moderator'   → limited admin panel access
        //   'admin'       → full admin panel access
        //   'super_admin' → full access + can change other admins' roles
        //
        // Admin panel login: /admin/login
        // Credentials below (change after first login!)
        // ─────────────────────────────────────────────────────────────────────

        $adminEmail    = config('app.admin_email',    'admin@kinglive.app');
        $adminPassword = config('app.admin_password', 'Admin@KingLive123');

        $user = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'username'     => 'admin',
                'display_name' => 'King Admin',
                // Hash::make() ensures the password is bcrypt-hashed
                // even if the 'hashed' cast is not triggered via firstOrCreate
                'password'     => Hash::make($adminPassword),
                'role'         => 'super_admin',
                'is_verified'  => true,
                'is_active'    => true,
                'avatar_url'   => 'https://ui-avatars.com/api/?name=King+Admin&background=6C3483&color=FFD700&size=200',
                'coin_balance' => 0,
                'diamond_balance' => 0,
            ]
        );

        $this->command->info("✅ Admin created: {$user->email} (role: {$user->role})");
        $this->command->info("   Login at: /admin/login");
        $this->command->info("   Password: {$adminPassword}");
        $this->command->info("   ⚠  Change your password after first login!");
    }
}
