<?php

namespace App\Models;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['restaurant_id', 'checklist_question_id', 'description', 'source', 'status', 'created_by', 'completed_by', 'completed_at'])]
class Task extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'source' => TaskSource::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The restaurant this task belongs to.
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * The checklist question this task originated from, if any.
     */
    public function checklistQuestion(): BelongsTo
    {
        return $this->belongsTo(ChecklistQuestion::class);
    }

    /**
     * The user who created this task.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who completed this task.
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
