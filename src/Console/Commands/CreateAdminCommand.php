<?php

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'atrium:user
                            {--name= : The name of the admin user}
                            {--email= : The email of the admin user}
                            {--password= : The password of the admin user}
                            {--superadmin : Make the user a superadmin (the first user always is); for an existing email, promote that user}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new admin user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // A way back in when nobody is left who may manage roles.
        if ($this->option('superadmin') && is_string($this->option('email'))) {
            $existing = AdminUser::query()->where('email', $this->option('email'))->first();

            if ($existing !== null) {
                $existing->forceFill(['is_superadmin' => true])->save();
                $this->info("'{$existing->name}' is a superadmin now.");

                return self::SUCCESS;
            }
        }

        $name = $this->option('name') ?? $this->ask('Enter admin name');
        $email = $this->option('email') ?? $this->ask('Enter admin email');
        $password = $this->option('password') ?? $this->secret('Enter admin password');

        // Validate input
        if (empty($name) || empty($email) || empty($password)) {
            $this->error('All fields are required.');

            return self::FAILURE;
        }

        // Check if user already exists
        if (AdminUser::where('email', $email)->exists()) {
            $this->error("User with email '{$email}' already exists.");

            return self::FAILURE;
        }

        // Validate email
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email format.');

            return self::FAILURE;
        }

        // Validate password length
        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        // The first administrator manages everything else, roles included.
        $superadmin = $this->option('superadmin') || ! AdminUser::query()->exists();

        // Create the user
        $user = AdminUser::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
        $user->forceFill(['is_superadmin' => $superadmin])->save();

        $this->info($superadmin
            ? "Superadmin '{$user->name}' created successfully!"
            : "Admin user '{$user->name}' created successfully! Give them a role in the panel: Roles, Administrators.");
        $this->table(
            ['ID', 'Name', 'Email'],
            [[$user->id, $user->name, $user->email]]
        );

        return self::SUCCESS;
    }
}
