<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'siteguard:make-admin {email}';

    protected $description = 'Grant SiteGuard admin access to an existing user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No user with that email exists.');

            return self::FAILURE;
        }
        $user->forceFill(['is_admin' => true])->save();
        $this->info('Admin access granted to '.$user->email);

        return self::SUCCESS;
    }
}
