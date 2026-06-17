<?php

namespace App\Http\Requests\Api;

use App\Enums\StoplistSection;
use App\Enums\StoplistStatus;
use App\Models\Restaurant;
use App\Rules\StoplistComment;
use App\Services\Menu\MenuResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStoplistEntryRequest extends FormRequest
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
            'section' => ['required', Rule::enum(StoplistSection::class)],
            'status' => ['required', Rule::enum(StoplistStatus::class)],
            'item' => ['required', 'string'],
            'comment' => ['required_unless:status,play', 'nullable', 'string'],
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
            'section.required' => 'Укажите раздел.',
            'section.enum' => 'Недопустимый раздел.',
            'status.required' => 'Укажите статус.',
            'status.enum' => 'Недопустимый статус.',
            'item.required' => 'Укажите позицию меню.',
            'item.string' => 'Позиция меню должна быть строкой.',
            'comment.required_unless' => 'Укажите комментарий.',
            'comment.string' => 'Комментарий должен быть строкой.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $restaurantId = $this->integer('restaurant_id');
            $item = $this->string('item')->toString();

            if ($restaurantId > 0 && $item !== '') {
                $restaurant = Restaurant::find($restaurantId);

                if ($restaurant !== null) {
                    $items = app(MenuResolver::class)->getItems($restaurant);

                    if (! in_array($item, $items, true)) {
                        $validator->errors()->add('item', 'Нужно выбрать позицию из меню.');
                    }
                }
            }

            $comment = $this->string('comment')->toString();

            if ($comment !== '') {
                (new StoplistComment)->validate('comment', $comment, function (string $message) use ($validator) {
                    $validator->errors()->add('comment', $message);
                });
            }
        });
    }
}
