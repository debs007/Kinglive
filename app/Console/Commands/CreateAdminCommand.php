<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
                            {--email= : Admin email address}
                            {--password= : Admin password}
                            {--username= : Admin username}';

    protected $description = 'Create or update an admin user for the King Live admin panel';

    public function handle(): int
    {
        $email    = $this->option('email')    ?? $this->ask('Email address', 'admin@kinglive.app');
        $password = $this->option('password') ?? $this->secret('Password (min 8 chars)');
        $username = $this->option('username') ?? $this->ask('Username', 'admin');

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'username'     => $username,
                'display_name' => 'King Admin',
                'password'     => Hash::make($password),
                'role'         => 'super_admin',
                'is_verified'  => true,
                'is_active'    => true,
                'avatar_url'   => "https://ui-avatars.com/api/?name={$username}&background=6C3483&color=FFD700&size=200",
                'coin_balance' => 0,
                'diamond_balance' => 0,
            ]
        );

        $action = $user->wasRecentlyCreated ? 'created' : 'updated';

        $this->newLine();
        $this->info("✅ Admin {$action} successfully!");
        $this->table(
            ['Field', 'Value'],
            [
                ['Email',    $user->email],
                ['Username', $user->username],
                ['Role',     $user->role],
                ['Login URL', url('/admin/login')],
            ]
        );
        $this->newLine();
        $this->warn('⚠  Keep your credentials safe. Change your password after first login.');

        return self::SUCCESS;
    }
}
