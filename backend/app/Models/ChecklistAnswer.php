<?php

namespace App\Models;

use App\Enums\ChecklistAnswerStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['checklist_submission_id', 'checklist_question_id', 'status', 'comment'])]
class ChecklistAnswer extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChecklistAnswerStatus::class,
        ];
    }

    /**
     * The submission this answer belongs to.
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(ChecklistSubmission::class, 'checklist_submission_id');
    }

    /**
     * The question this answer responds to.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(ChecklistQuestion::class, 'checklist_question_id');
    }
}
