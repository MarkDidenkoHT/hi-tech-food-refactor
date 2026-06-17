<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTaskRequest;
use App\Http\Requests\Api\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * List tasks for a restaurant, filtered by status.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $restaurantId = (int) $request->query('restaurant_id');

        abort_unless($restaurantId > 0 && $request->user()->belongsToRestaurant($restaurantId), 403);

        $request->validate([
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
        ], [
            'status.enum' => 'Недопустимый статус задачи.',
        ]);

        $status = $request->query('status', TaskStatus::Open->value);

        $tasks = Task::where('restaurant_id', $restaurantId)
            ->where('status', $status)
            ->with(['restaurant', 'checklistQuestion', 'creator', 'completer'])
            ->latest()
            ->get();

        return TaskResource::collection($tasks);
    }

    /**
     * Create a manual task.
     */
    public function store(StoreTaskRequest $request): TaskResource
    {
        $data = $request->validated();

        $task = Task::create([
            'restaurant_id' => $data['restaurant_id'],
            'description' => $data['description'],
            'source' => TaskSource::Manual,
            'status' => TaskStatus::Open,
            'created_by' => $request->user()->id,
        ]);

        $task->load(['restaurant', 'checklistQuestion', 'creator', 'completer']);

        return new TaskResource($task);
    }

    /**
     * Toggle a task between open and done.
     */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $status = TaskStatus::from($request->validated()['status']);

        if ($status === TaskStatus::Done) {
            $task->update([
                'status' => $status,
                'completed_by' => $request->user()->id,
                'completed_at' => now(),
            ]);
        } else {
            $task->update([
                'status' => $status,
                'completed_by' => null,
                'completed_at' => null,
            ]);
        }

        $task->load(['restaurant', 'checklistQuestion', 'creator', 'completer']);

        return new TaskResource($task);
    }
}
