<?php

namespace App\Http\Resources;

use App\Models\StoplistEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StoplistEntry */
class StoplistEntryResource extends JsonResource
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
            'section' => $this->section->value,
            'status' => $this->status->value,
            'item' => $this->item,
            'comment' => $this->comment,
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserName($this->creator)),
            'created_at' => $this->created_at,
            'resolved_at' => $this->resolved_at,
            'resolved_by' => $this->whenLoaded('resolver', fn () => $this->formatUserName($this->resolver)),
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
