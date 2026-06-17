<?php

namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\User;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramBotService $bot,
    ) {}

    /**
     * Handle an incoming Telegram bot webhook update.
     */
    public function handle(Request $request, string $secret): Response
    {
        $expectedSecret = (string) config('services.telegram.webhook_secret');

        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $secret)) {
            abort(404);
        }

        if (! hash_equals($expectedSecret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(404);
        }

        $message = $request->input('message');

        if (! is_array($message) || ! isset($message['from']['id'], $message['text'])) {
            return response('', 200);
        }

        $text = trim((string) $message['text']);

        if (str_starts_with($text, '/start')) {
            $this->handleStart($message);
        }

        return response('', 200);
    }

    /**
     * Handle a `/start [code]` command.
     *
     * @param  array<string, mixed>  $message
     */
    private function handleStart(array $message): void
    {
        $from = $message['from'];
        $chatId = (int) $from['id'];

        $existing = User::where('telegram_id', $chatId)->first();

        if ($existing !== null) {
            $this->sendWelcome($chatId, $existing->first_name);

            return;
        }

        $code = trim(substr($message['text'], strlen('/start')));

        if ($code === '') {
            $this->bot->sendMessage($chatId, 'Contact your administrator for an invite link.');

            return;
        }

        $invite = Invite::where('code', $code)->first();

        if ($invite === null || ! $invite->isUsable()) {
            $this->bot->sendMessage($chatId, 'This invite link is invalid or has expired. Contact your administrator for a new one.');

            return;
        }

        $user = DB::transaction(function () use ($invite, $from, $chatId) {
            $user = User::create([
                'telegram_id' => $chatId,
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? 'User',
                'last_name' => $from['last_name'] ?? null,
                'role' => $invite->role,
                'is_active' => true,
            ]);

            if ($invite->restaurant_id !== null) {
                $user->restaurants()->attach($invite->restaurant_id);
            }

            $invite->forceFill([
                'used_at' => now(),
                'used_by' => $user->id,
            ])->save();

            return $user;
        });

        $this->sendWelcome($chatId, $user->first_name);
    }

    private function sendWelcome(int $chatId, string $firstName): void
    {
        $appUrl = rtrim((string) config('app.url'), '/').'/app';

        $this->bot->sendMessage(
            $chatId,
            'Welcome, '.e($firstName).'! Tap the button below to open the app.',
            $this->bot->miniAppKeyboard('Open App', $appUrl)
        );
    }
}
