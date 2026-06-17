<?php

namespace App\Http\Resources;

use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invite */
class InviteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = match (true) {
            $this->used_at !== null => 'used',
            $this->expires_at !== null && $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };

        return [
            'id' => $this->id,
            'code' => $this->code,
            'link' => $this->telegramLink(),
            'status' => $status,
            'role' => $this->role->value,
            'restaurant' => new RestaurantResource($this->whenLoaded('restaurant')),
            'created_by' => $this->creator?->first_name,
            'used_by' => $this->whenLoaded('usedBy', fn () => $this->usedBy?->first_name),
            'expires_at' => $this->expires_at,
            'used_at' => $this->used_at,
            'created_at' => $this->created_at,
        ];
    }

    private function telegramLink(): ?string
    {
        $username = config('services.telegram.bot_username');

        if (! $username) {
            return null;
        }

        return "https://t.me/{$username}?start={$this->code}";
    }
}
