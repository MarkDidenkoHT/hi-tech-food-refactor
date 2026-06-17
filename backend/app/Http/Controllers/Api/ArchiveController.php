<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    /**
     * Show completed tasks for a restaurant.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = (int) $request->query('restaurant_id');

        abort_unless($restaurantId > 0 && $request->user()->belongsToRestaurant($restaurantId), 403);

        $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'search' => ['sometimes', 'string'],
        ]);

        $from = $request->query('from');
        $to = $request->query('to');
        $search = mb_strtolower(trim((string) $request->query('search', '')));

        $tasks = Task::where('restaurant_id', $restaurantId)
            ->where('status', TaskStatus::Done)
            ->with(['restaurant', 'checklistQuestion', 'creator', 'completer'])
            ->when($from, fn ($query) => $query->whereDate('completed_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('completed_at', '<=', $to))
            ->latest('completed_at')
            ->get()
            ->filter(fn (Task $task) => $search === '' || str_contains(mb_strtolower($task->description), $search))
            ->values();

        return response()->json([
            'tasks' => TaskResource::collection($tasks),
        ]);
    }
}
