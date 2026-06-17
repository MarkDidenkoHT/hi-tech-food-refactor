<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;

class TelegramBotService
{
    public function __construct(
        private readonly string $botToken,
    ) {}

    /**
     * Send a text message to a Telegram chat.
     *
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", $payload);
    }

    /**
     * Build an inline keyboard with a single button that opens the Mini App.
     *
     * @return array<string, mixed>
     */
    public function miniAppKeyboard(string $label, string $url): array
    {
        return [
            'inline_keyboard' => [[
                ['text' => $label, 'web_app' => ['url' => $url]],
            ]],
        ];
    }
}
