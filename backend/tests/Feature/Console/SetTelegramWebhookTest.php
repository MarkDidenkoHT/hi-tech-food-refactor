<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetTelegramWebhookTest extends TestCase
{
    public function test_it_registers_the_webhook_when_configured(): void
    {
        config([
            'app.url' => 'https://example.test',
            'services.telegram.bot_token' => 'TESTTOKEN',
            'services.telegram.webhook_secret' => 'sekret',
        ]);

        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $this->artisan('telegram:set-webhook')->assertSuccessful();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/setWebhook')
                && $request['url'] === 'https://example.test/telegram/webhook/sekret'
                && $request['secret_token'] === 'sekret';
        });
    }

    public function test_it_skips_when_not_configured(): void
    {
        config([
            'app.url' => 'https://example.test',
            'services.telegram.bot_token' => '',
            'services.telegram.webhook_secret' => '',
        ]);

        Http::fake();

        $this->artisan('telegram:set-webhook')->assertSuccessful();

        Http::assertNothingSent();
    }
}
