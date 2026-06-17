<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
        {telegram_id : The admin\'s numeric Telegram user id}
        {first_name : Display name}
        {--last-name= : Optional last name}
        {--username= : Optional Telegram @username (without @)}';

    protected $description = 'Create or promote a user to admin so they can sign in and bootstrap the app';

    public function handle(): int
    {
        $telegramId = (int) $this->argument('telegram_id');

        if ($telegramId <= 0) {
            $this->error('telegram_id must be a positive integer.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['telegram_id' => $telegramId],
            [
                'first_name' => $this->argument('first_name'),
                'last_name' => $this->option('last-name'),
                'username' => $this->option('username'),
                'role' => Role::Admin,
                'is_active' => true,
            ],
        );

        $this->info("Admin ready: {$user->first_name} (telegram_id {$user->telegram_id}). They can now open the Mini App and create invites.");

        return self::SUCCESS;
    }
}
