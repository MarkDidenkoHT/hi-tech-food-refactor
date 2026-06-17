<?php

namespace App\Http\Controllers\Api;

use App\Enums\ChecklistAnswerStatus;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreChecklistSubmissionRequest;
use App\Models\ChecklistQuestion;
use App\Models\ChecklistSubmission;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistController extends Controller
{
    /**
     * Show today's checklist questions and submission (if any) for a restaurant.
     */
    public function show(Request $request): JsonResponse
    {
        $restaurantId = (int) $request->query('restaurant_id');

        abort_unless($restaurantId > 0 && $request->user()->belongsToRestaurant($restaurantId), 403);

        return response()->json($this->checklistPayload($restaurantId));
    }

    /**
     * Submit today's checklist for a restaurant.
     */
    public function store(StoreChecklistSubmissionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tasksCreated = 0;

        DB::transaction(function () use ($data, $request, &$tasksCreated) {
            $submission = ChecklistSubmission::create([
                'restaurant_id' => $data['restaurant_id'],
                'user_id' => $request->user()->id,
                'submitted_at' => now(),
            ]);

            foreach ($data['answers'] as $answer) {
                $submission->answers()->create([
                    'checklist_question_id' => $answer['checklist_question_id'],
                    'status' => $answer['status'],
                    'comment' => $answer['comment'] ?? null,
                ]);

                $needsTask = $answer['status'] === ChecklistAnswerStatus::NotOk->value || filled($answer['comment'] ?? null);

                if (! $needsTask) {
                    continue;
                }

                $alreadyOpen = Task::where('restaurant_id', $data['restaurant_id'])
                    ->where('checklist_question_id', $answer['checklist_question_id'])
                    ->where('status', TaskStatus::Open)
                    ->exists();

                if ($alreadyOpen) {
                    continue;
                }

                $question = ChecklistQuestion::find($answer['checklist_question_id']);
                $description = $question->question;

                if (filled($answer['comment'] ?? null)) {
                    $description .= ' — '.$answer['comment'];
                }

                Task::create([
                    'restaurant_id' => $data['restaurant_id'],
                    'checklist_question_id' => $answer['checklist_question_id'],
                    'description' => $description,
                    'source' => TaskSource::Checklist,
                    'status' => TaskStatus::Open,
                    'created_by' => $request->user()->id,
                ]);

                $tasksCreated++;
            }
        });

        return response()->json([
            ...$this->checklistPayload($data['restaurant_id']),
            'tasks_created' => $tasksCreated,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checklistPayload(int $restaurantId): array
    {
        $questions = ChecklistQuestion::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('area')
            ->orderBy('sort_order')
            ->get();

        $areas = $questions->groupBy('area')->map(fn ($questions, $area) => [
            'area' => $area,
            'questions' => $questions->map(fn ($question) => [
                'id' => $question->id,
                'question' => $question->question,
            ])->values(),
        ])->values();

        $submission = ChecklistSubmission::where('restaurant_id', $restaurantId)
            ->whereDate('submitted_at', today())
            ->with(['answers.question', 'user'])
            ->latest('submitted_at')
            ->first();

        return [
            'areas' => $areas,
            'submission' => $submission ? $this->formatSubmission($submission) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSubmission(ChecklistSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'submitted_at' => $submission->submitted_at,
            'submitted_by' => trim("{$submission->user->first_name} {$submission->user->last_name}"),
            'answers' => $submission->answers->map(fn ($answer) => [
                'checklist_question_id' => $answer->checklist_question_id,
                'question' => $answer->question?->question,
                'area' => $answer->question?->area,
                'status' => $answer->status->value,
                'comment' => $answer->comment,
            ])->values(),
        ];
    }
}
