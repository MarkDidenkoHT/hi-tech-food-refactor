<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array. Shape is kept compatible with the
     * read-only reservation events merged in alongside local events.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => 'local',
            'editable' => true,
            'title' => $this->title,
            'event_date' => $this->event_date?->format('Y-m-d'),
            'event_time' => $this->event_time !== null ? substr((string) $this->event_time, 0, 5) : null,
            'guests' => $this->guests,
            'contact' => $this->contact,
            'notes' => $this->notes,
            'event_type' => $this->event_type?->value,
            'type_name' => $this->event_type?->label(),
            'type_color' => $this->event_type?->color(),
        ];
    }
}
