<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

/**
 * Local-only helper for testing the Mini App in a regular browser, where
 * Telegram's WebApp `initData` is normally unavailable. Routes for this
 * controller are only registered when the app is running in `local`.
 */
class DevLoginController extends Controller
{
    /**
     * List local users to "log in" as.
     */
    public function index(): View
    {
        $users = User::orderBy('first_name')->get();

        return view('dev-login', ['users' => $users]);
    }

    /**
     * Render the Mini App with a freshly signed `initData` for the given user.
     */
    public function show(int $telegramId): View
    {
        $user = User::where('telegram_id', $telegramId)->firstOrFail();

        return view('app', [
            'mockInitData' => $this->signedInitData($user),
        ]);
    }

    private function signedInitData(User $user): string
    {
        $botToken = (string) config('services.telegram.bot_token');

        $payload = [
            'auth_date' => (string) time(),
            'query_id' => 'DEV'.bin2hex(random_bytes(8)),
            'user' => json_encode([
                'id' => $user->telegram_id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'language_code' => 'en',
            ], JSON_UNESCAPED_UNICODE),
        ];

        ksort($payload);

        $checkString = collect($payload)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $payload['hash'] = hash_hmac('sha256', $checkString, $secretKey);

        return http_build_query($payload);
    }
}
