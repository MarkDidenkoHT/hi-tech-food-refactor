<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TelegramLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Telegram\TelegramAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly TelegramAuthService $telegramAuth,
    ) {}

    /**
     * Authenticate a Telegram Mini App user via `initData` and issue an API token.
     */
    public function telegramLogin(TelegramLoginRequest $request): JsonResponse
    {
        $data = $this->telegramAuth->validate($request->string('init_data')->toString());

        if ($data === null) {
            return response()->json([
                'message' => 'Неверные данные авторизации Telegram.',
            ], 401);
        }

        $telegramUser = $this->telegramAuth->user($data);

        if ($telegramUser === null || ! isset($telegramUser['id'])) {
            return response()->json([
                'message' => 'Неверные данные авторизации Telegram.',
            ], 401);
        }

        $user = User::where('telegram_id', $telegramUser['id'])->first();

        if ($user === null || ! $user->is_active) {
            return response()->json([
                'message' => 'Вы не зарегистрированы. Обратитесь к менеджеру за ссылкой-приглашением.',
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('telegram-mini-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('restaurants')),
        ]);
    }

    /**
     * Revoke the current API token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Вы вышли из системы.']);
    }
}
