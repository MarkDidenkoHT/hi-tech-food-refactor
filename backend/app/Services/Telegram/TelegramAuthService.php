<?php

namespace App\Services\Telegram;

class TelegramAuthService
{
    /**
     * Maximum age (in seconds) of an `auth_date` before initData is considered stale.
     */
    private const MAX_AUTH_AGE = 86400; // 24 hours

    public function __construct(
        private readonly string $botToken,
    ) {}

    /**
     * Validate Telegram WebApp `initData` and return its parsed fields.
     *
     * @return array<string, string>|null Null if the payload is invalid or expired.
     */
    public function validate(string $initData): ?array
    {
        parse_str($initData, $data);

        if (! isset($data['hash']) || ! is_string($data['hash'])) {
            return null;
        }

        $hash = $data['hash'];
        unset($data['hash']);

        ksort($data);

        $checkString = collect($data)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $computedHash = hash_hmac('sha256', $checkString, $secretKey);

        if (! hash_equals($computedHash, $hash)) {
            return null;
        }

        $authDate = (int) ($data['auth_date'] ?? 0);

        if ($authDate <= 0 || (time() - $authDate) > self::MAX_AUTH_AGE) {
            return null;
        }

        return $data;
    }

    /**
     * Extract the Telegram user payload from validated initData.
     *
     * @param  array<string, string>  $data
     * @return array<string, mixed>|null
     */
    public function user(array $data): ?array
    {
        if (! isset($data['user'])) {
            return null;
        }

        $user = json_decode($data['user'], true);

        return is_array($user) ? $user : null;
    }
}
