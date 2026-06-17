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

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_done_tasks(): void
    {
        $restaurant = $this->createRestaurant();
        $user = $this->createUserFor($restaurant, Role::Manager);

        $this->createTask($restaurant, TaskStatus::Open, 'Открытая задача');
        $done = $this->createTask($restaurant, TaskStatus::Done, 'Выполненная задача');
        $done->update(['completed_at' => now(), 'completed_by' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/archive?restaurant_id={$restaurant->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.description', 'Выполненная задача')
            ->assertJsonMissingPath('stoplist_entries');
    }

    public function test_index_filters_by_search_text(): void
    {
        $restaurant = $this->createRestaurant();
        $user = $this->createUserFor($restaurant, Role::Manager);

        $first = $this->createTask($restaurant, TaskStatus::Done, 'Проверить холодильник');
        $first->update(['completed_at' => now()]);

        $second = $this->createTask($restaurant, TaskStatus::Done, 'Помыть пол');
        $second->update(['completed_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/archive?restaurant_id={$restaurant->id}&search=холодильник");

        $response->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.description', 'Проверить холодильник');
    }

    public function test_index_filters_by_date_range(): void
    {
        $restaurant = $this->createRestaurant();
        $user = $this->createUserFor($restaurant, Role::Manager);

        $old = $this->createTask($restaurant, TaskStatus::Done, 'Старая задача');
        $old->update(['completed_at' => now()->subDays(10)]);

        $recent = $this->createTask($restaurant, TaskStatus::Done, 'Новая задача');
        $recent->update(['completed_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/archive?restaurant_id={$restaurant->id}&from=".now()->subDay()->toDateString());

        $response->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.description', 'Новая задача');
    }

    public function test_index_is_forbidden_for_staff(): void
    {
        $restaurant = $this->createRestaurant();
        $user = $this->createUserFor($restaurant, Role::Staff);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/archive?restaurant_id={$restaurant->id}");

        $response->assertForbidden();
    }

    public function test_index_is_forbidden_for_a_non_belonging_user(): void
    {
        $restaurant = $this->createRestaurant();
        $other = Restaurant::create(['name' => 'Каста', 'slug' => 'kasta', 'is_active' => true]);
        $user = $this->createUserFor($other, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/archive?restaurant_id={$restaurant->id}");

        $response->assertForbidden();
    }

    private function createRestaurant(): Restaurant
    {
        return Restaurant::create([
            'name' => 'Санторини Подворье',
            'slug' => 'santorini-podvore',
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
