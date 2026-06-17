<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'role' => ['sometimes', Rule::enum(Role::class)],
            'is_active' => ['sometimes', 'boolean'],
            'restaurant_ids' => ['sometimes', 'array'],
            'restaurant_ids.*' => ['integer', 'exists:restaurants,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.enum' => 'Недопустимая роль.',
            'is_active.boolean' => 'Поле активности должно быть true или false.',
            'restaurant_ids.array' => 'Список ресторанов указан некорректно.',
            'restaurant_ids.*.integer' => 'Некорректный ресторан в списке.',
            'restaurant_ids.*.exists' => 'Один из выбранных ресторанов не найден.',
        ];
    }
}
