<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\ChecklistQuestion;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_grouped_questions_and_no_submission_initially(): void
    {
        $restaurant = $this->createRestaurant();
        $question = $this->createQuestion($restaurant);
        $user = $this->createStaffFor($restaurant);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/checklist?restaurant_id={$restaurant->id}");

        $response->assertOk()
            ->assertJsonPath('submission', null)
            ->assertJsonPath('areas.0.area', 'Кухня')
            ->assertJsonPath('areas.0.questions.0.id', $question->id);
    }

    public function test_post_creates_submission_and_task_for_not_ok_answer(): void
    {
        $restaurant = $this->createRestaurant();
        $question = $this->createQuestion($restaurant);
        $user = $this->createStaffFor($restaurant);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/checklist', [
                'restaurant_id' => $restaurant->id,
                'answers' => [
                    ['checklist_question_id' => $question->id, 'status' => 'not_ok', 'comment' => 'Грязно'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('tasks_created', 1)
            ->assertJsonPath('submission.submitted_by', trim("{$user->first_name} {$user->last_name}"));

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas('tasks', [
            'restaurant_id' => $restaurant->id,
            'checklist_question_id' => $question->id,
            'status' => 'open',
            'source' => 'checklist',
        ]);
    }

    public function test_resubmitting_does_not_duplicate_an_open_task(): void
    {
        $restaurant = $this->createRestaurant();
        $question = $this->createQuestion($restaurant);
        $user = $this->createStaffFor($restaurant);

        $payload = [
            'restaurant_id' => $restaurant->id,
            'answers' => [
                ['checklist_question_id' => $question->id, 'status' => 'not_ok', 'comment' => null],
            ],
        ];

        $first = $this->actingAs($user, 'sanctum')->postJson('/api/checklist', $payload);
        $first->assertOk()->assertJsonPath('tasks_created', 1);

        $second = $this->actingAs($user, 'sanctum')->postJson('/api/checklist', $payload);
        $second->assertOk()->assertJsonPath('tasks_created', 0);

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_user_not_belonging_to_restaurant_is_forbidden(): void
    {
        $restaurant = $this->createRestaurant();
        $this->createQuestion($restaurant);
        $user = User::factory()->role(Role::Staff)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/checklist?restaurant_id={$restaurant->id}");

        $response->assertForbidden();
    }

    private function createRestaurant(): Restaurant
    {
        return Restaurant::create([
            'name' => 'Тоскана',
            'slug' => 'toskana',
            'is_active' => true,
        ]);
    }

    private function createQuestion(Restaurant $restaurant): ChecklistQuestion
    {
        return ChecklistQuestion::create([
            'restaurant_id' => $restaurant->id,
            'area' => 'Кухня',
            'question' => 'Чисто на кухне?',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createStaffFor(Restaurant $restaurant): User
    {
        $user = User::factory()->role(Role::Staff)->create();
        $user->restaurants()->attach($restaurant);

        return $user;
    }
}
