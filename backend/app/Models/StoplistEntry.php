<?php

namespace App\Models;

use App\Enums\StoplistSection;
use App\Enums\StoplistStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['restaurant_id', 'section', 'status', 'item', 'comment', 'created_by', 'resolved_at', 'resolved_by'])]
class StoplistEntry extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'section' => StoplistSection::class,
            'status' => StoplistStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * The restaurant this entry belongs to.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * The user who created this entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who resolved this entry.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
