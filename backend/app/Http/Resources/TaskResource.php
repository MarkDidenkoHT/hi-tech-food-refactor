<?php

namespace App\Http\Resources;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
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
            'description' => $this->description,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'restaurant' => [
                'id' => $this->restaurant->id,
                'name' => $this->restaurant->name,
            ],
            'area' => $this->whenLoaded('checklistQuestion', fn () => $this->checklistQuestion?->area),
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserName($this->creator)),
            'completed_by' => $this->whenLoaded('completer', fn () => $this->formatUserName($this->completer)),
            'created_at' => $this->created_at,
            'completed_at' => $this->completed_at,
        ];
    }

    private function formatUserName(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        return trim("{$user->first_name} {$user->last_name}");
    }
}
