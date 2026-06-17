<?php

namespace App\Http\Requests\Api;

use App\Models\Restaurant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreMenuItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $restaurantId = $this->integer('restaurant_id');

        return $restaurantId > 0 && $this->user()->belongsToRestaurant($restaurantId);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => [
                'required',
                'integer',
                'exists:restaurants,id',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (Restaurant::find($value)?->woocommerce_domain !== null) {
                        $fail('Меню этого ресторана синхронизируется с сайтом и не редактируется вручную.');
                    }
                },
            ],
            'name' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'restaurant_id.required' => 'Укажите ресторан.',
            'restaurant_id.integer' => 'Некорректный ресторан.',
            'restaurant_id.exists' => 'Указанный ресторан не найден.',
            'name.required' => 'Укажите название позиции.',
            'name.string' => 'Название позиции должно быть строкой.',
        ];
    }
}
