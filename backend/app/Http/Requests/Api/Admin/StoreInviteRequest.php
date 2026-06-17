<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'role' => ['required', Rule::in([Role::Manager->value, Role::Staff->value])],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'restaurant_id.integer' => 'Некорректный ресторан.',
            'restaurant_id.exists' => 'Указанный ресторан не найден.',
            'role.required' => 'Укажите роль.',
            'role.in' => 'Недопустимая роль.',
            'expires_at.date' => 'Некорректная дата истечения.',
            'expires_at.after' => 'Дата истечения должна быть в будущем.',
        ];
    }
}
