<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StoplistComment implements ValidationRule
{
    /**
     * Generic phrases that don't actually explain what's missing.
     */
    private const GENERIC_PHRASES = '/^(нет|нету|не хватает|нету блюд|нет блюд|нету блюда|отсутствует|отсутствуют|отсутствует блюдо)$/iu';

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Комментарий должен быть строкой.');

            return;
        }

        $length = mb_strlen($value);

        if ($length < 3 || $length > 100) {
            $fail('Комментарий должен быть от 3 до 100 символов.');

            return;
        }

        if (preg_match('/(.)\1{3,}/u', $value) === 1) {
            $fail('Комментарий не должен содержать более 3 одинаковых символов подряд.');

            return;
        }

        if (preg_match(self::GENERIC_PHRASES, $value) === 1) {
            $fail('Комментарий должен быть более содержательным.');
        }
    }
}
