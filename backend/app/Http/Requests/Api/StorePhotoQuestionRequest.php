<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePhotoQuestionRequest extends FormRequest
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
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'question' => ['required', 'string'],
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
            'question.required' => 'Укажите текст задания.',
            'question.string' => 'Текст задания должен быть строкой.',
        ];
    }
}
