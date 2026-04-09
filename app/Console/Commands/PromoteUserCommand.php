<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteUserCommand extends Command
{
    protected $signature = 'admin:promote
                            {email : The email of the user to promote}
                            {--role=admin : Role to assign (admin|super_admin|moderator)}';

    protected $description = 'Promote an existing user to admin/moderator role';

    public function handle(): int
    {
        $email = $this->argument('email');
        $role  = $this->option('role');

        $allowed = ['admin', 'super_admin', 'moderator'];

        if (! in_array($role, $allowed)) {
            $this->error("Invalid role '{$role}'. Allowed: " . implode(', ', $allowed));
            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        $oldRole = $user->role;
        $user->update(['role' => $role, 'is_active' => true]);

        $this->info("✅ {$user->username} ({$email}) promoted from '{$oldRole}' → '{$role}'");
        $this->info("   Can now log in at: " . url('/admin/login'));

        return self::SUCCESS;
    }
}
