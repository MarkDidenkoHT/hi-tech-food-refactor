<?php

namespace App\Http\Requests\Api;

use App\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var MenuItem $menuItem */
        $menuItem = $this->route('menuItem');

        return $this->user()->belongsToRestaurant($menuItem->restaurant_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название позиции.',
            'name.string' => 'Название позиции должно быть строкой.',
        ];
    }
}
