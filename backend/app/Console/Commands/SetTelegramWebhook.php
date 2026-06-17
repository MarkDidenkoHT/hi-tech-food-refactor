<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = 'Register this deployment\'s public URL as the Telegram bot webhook';

    public function handle(TelegramBotService $bot): int
    {
        $token = (string) config('services.telegram.bot_token');
        $secret = (string) config('services.telegram.webhook_secret');
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($token === '' || $secret === '' || $appUrl === '') {
            // Don't fail container startup when the bot isn't configured yet.
            $this->warn('Telegram bot token, webhook secret, or APP_URL not set — skipping webhook registration.');

            return self::SUCCESS;
        }

        $url = "{$appUrl}/telegram/webhook/{$secret}";

        if ($bot->setWebhook($url, $secret)) {
            $this->info("Telegram webhook registered: {$appUrl}/telegram/webhook/***");

            return self::SUCCESS;
        }

        $this->error('Failed to register the Telegram webhook.');

        return self::FAILURE;
    }
}
