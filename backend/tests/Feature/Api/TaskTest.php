<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Restaurant;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_by_status(): void
    {
        $restaurant = $this->createRestaurant();
        $user = $this->createUserFor($restaurant, Role::Staff);

        $this->createTask($restaurant, TaskStatus::Open, 'Открытая задача');
        $this->createTask($restaurant, TaskStatus::Done, 'Выполненная задача');

        $open = $this->actingAs($user, 'sanctum')
            ->getJson("/api/tasks?restaurant_id={$restaurant->id}&status=open");

        $open->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Открытая задача');

        $done = $this->actingAs($user, 'sanctum')
            ->getJson("/api/tasks?restaurant_id={$restaurant->id}&status=done");

        $done->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Выполненная задача');
    }

    public function test_store_creates_a_manual_task(): void
    {
        $restaurant = $this->createRestaurant();
        $user = $this->createUserFor($restaurant, Role::Staff);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'restaurant_id' => $restaurant->id,
                'description' => 'Проверить холодильник',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.description', 'Проверить холодильник')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.source', 'manual');

        $this->assertDatabaseHas('tasks', [
            'restaurant_id' => $restaurant->id,
            'description' => 'Проверить холодильник',
            'source' => TaskSource::Manual->value,
            'status' => TaskStatus::Open->value,
            'created_by' => $user->id,
        ]);
    }

    public function test_update_toggles_open_and_done(): void
    {
        $restaurant = $this->createRestaurant();
        $user = $this->createUserFor($restaurant, Role::Staff);
        $task = $this->createTask($restaurant, TaskStatus::Open, 'Задача');

        $done = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}", ['status' => 'done']);

        $done->assertOk()->assertJsonPath('data.status', 'done');

        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertSame($user->id, $task->completed_by);
        $this->assertNotNull($task->completed_at);

        $reopened = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}", ['status' => 'open']);

        $reopened->assertOk()->assertJsonPath('data.status', 'open');

        $task->refresh();
        $this->assertSame(TaskStatus::Open, $task->status);
        $this->assertNull($task->completed_by);
        $this->assertNull($task->completed_at);
    }

    private function createRestaurant(): Restaurant
    {
        return Restaurant::create([
            'name' => 'Тоскана',
            'slug' => 'toskana',
            'is_active' => true,
        ]);
    }

    private function createUserFor(Restaurant $restaurant, Role $role): User
    {
        $user = User::factory()->role($role)->create();
        $user->restaurants()->attach($restaurant);

        return $user;
    }

    private function createTask(Restaurant $restaurant, TaskStatus $status, string $description): Task
    {
        return Task::create([
            'restaurant_id' => $restaurant->id,
            'description' => $description,
            'source' => TaskSource::Manual,
            'status' => $status,
        ]);
    }
}
