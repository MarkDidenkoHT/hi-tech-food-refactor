<?php

namespace App\Http\Requests\Api;

use App\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
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
            'event_type' => ['nullable', Rule::enum(EventType::class)],
            'title' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date_format:Y-m-d'],
            'event_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'guests' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'restaurant_id.required' => 'Укажите ресторан.',
            'restaurant_id.exists' => 'Указанный ресторан не найден.',
            'event_type.enum' => 'Недопустимый тип события.',
            'title.required' => 'Укажите название события.',
            'title.max' => 'Название слишком длинное.',
            'event_date.required' => 'Укажите дату.',
            'event_date.date_format' => 'Некорректная дата.',
            'event_time.date_format' => 'Некорректное время.',
            'guests.integer' => 'Количество гостей должно быть числом.',
            'guests.min' => 'Количество гостей не может быть отрицательным.',
        ];
    }
}
