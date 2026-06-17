<?php

namespace App\Http\Requests\Api;

use App\Models\PhotoQuestion;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePhotoQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var PhotoQuestion $photoQuestion */
        $photoQuestion = $this->route('photoQuestion');

        return $this->user()->belongsToRestaurant($photoQuestion->restaurant_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'Укажите текст задания.',
            'question.string' => 'Текст задания должен быть строкой.',
        ];
    }
}
