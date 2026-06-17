<?php

use App\Http\Controllers\Api\Admin\CronSettingController;
use App\Http\Controllers\Api\Admin\InviteController;
use App\Http\Controllers\Api\Admin\RestaurantController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\ArchiveController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\PhotoQuestionController;
use App\Http\Controllers\Api\StoplistController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/telegram', [AuthController::class, 'telegramLogin'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/checklist', [ChecklistController::class, 'show']);
    Route::post('/checklist', [ChecklistController::class, 'store']);

    Route::get('/photo-questions', [PhotoQuestionController::class, 'index']);
    Route::post('/photo-questions', [PhotoQuestionController::class, 'store'])->middleware('role:admin,director');
    Route::patch('/photo-questions/{photoQuestion}', [PhotoQuestionController::class, 'update'])->middleware('role:admin,director');
    Route::delete('/photo-questions/{photoQuestion}', [PhotoQuestionController::class, 'destroy'])->middleware('role:admin,director');

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::patch('/tasks/{task}', [TaskController::class, 'update']);

    Route::get('/feedback', [FeedbackController::class, 'index']);

    Route::middleware('role:manager,director,admin')->group(function () {
        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);
        Route::patch('/events/{event}', [EventController::class, 'update']);
        Route::delete('/events/{event}', [EventController::class, 'destroy']);
    });

    Route::get('/stoplist', [StoplistController::class, 'index']);
    Route::get('/stoplist/menu', [StoplistController::class, 'menu']);
    Route::post('/stoplist', [StoplistController::class, 'store']);
    Route::post('/stoplist/send', [StoplistController::class, 'send']);
    Route::patch('/stoplist/{stoplistEntry}/resolve', [StoplistController::class, 'resolve']);

    Route::get('/archive', [ArchiveController::class, 'index'])->middleware('role:manager,director,admin');

    Route::get('/menu-items', [MenuItemController::class, 'index']);
    Route::post('/menu-items', [MenuItemController::class, 'store'])->middleware('role:admin,director');
    Route::patch('/menu-items/{menuItem}', [MenuItemController::class, 'update'])->middleware('role:admin,director');
    Route::delete('/menu-items/{menuItem}', [MenuItemController::class, 'destroy'])->middleware('role:admin,director');

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::apiResource('restaurants', RestaurantController::class)->only(['index', 'store', 'update']);
        Route::apiResource('users', UserController::class)->only(['index', 'update', 'destroy']);
        Route::apiResource('invites', InviteController::class)->only(['index', 'store', 'destroy']);
        Route::get('/cron-settings', [CronSettingController::class, 'show']);
        Route::patch('/cron-settings', [CronSettingController::class, 'update']);
    });
});
