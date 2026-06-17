<?php

namespace App\Http\Resources;

use App\Models\PhotoQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PhotoQuestion */
class PhotoQuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
