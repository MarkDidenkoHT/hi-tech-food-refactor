<?php

namespace App\Http\Requests\Api;

use App\Enums\ChecklistAnswerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChecklistSubmissionRequest extends FormRequest
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
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.checklist_question_id' => [
                'required',
                'integer',
                Rule::exists('checklist_questions', 'id')->where('restaurant_id', $this->input('restaurant_id')),
            ],
            'answers.*.status' => ['required', Rule::enum(ChecklistAnswerStatus::class)],
            'answers.*.comment' => ['nullable', 'string'],
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
            'answers.required' => 'Передайте ответы на вопросы чек-листа.',
            'answers.array' => 'Ответы указаны некорректно.',
            'answers.min' => 'Передайте ответы на вопросы чек-листа.',
            'answers.*.checklist_question_id.required' => 'Не указан вопрос чек-листа.',
            'answers.*.checklist_question_id.integer' => 'Некорректный вопрос чек-листа.',
            'answers.*.checklist_question_id.exists' => 'Один из вопросов не найден для этого ресторана.',
            'answers.*.status.required' => 'Укажите статус ответа.',
            'answers.*.status.enum' => 'Недопустимый статус ответа.',
            'answers.*.comment.string' => 'Комментарий должен быть строкой.',
        ];
    }
}
