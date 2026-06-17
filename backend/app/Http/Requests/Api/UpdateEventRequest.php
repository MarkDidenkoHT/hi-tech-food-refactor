<?php

namespace App\Http\Requests\Api;

use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && $this->user()->belongsToRestaurant($event->restaurant_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_type' => ['sometimes', 'nullable', Rule::enum(EventType::class)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'event_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'event_time' => ['sometimes', 'nullable', 'date_format:H:i,H:i:s'],
            'guests' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event_type.enum' => 'Недопустимый тип события.',
            'title.required' => 'Укажите название события.',
            'title.max' => 'Название слишком длинное.',
            'event_date.required' => 'Укажите дату.',
            'event_date.date_format' => 'Некорректная дата.',
            'event_time.date_format' => 'Некорректное время.',
        ];
    }
}
