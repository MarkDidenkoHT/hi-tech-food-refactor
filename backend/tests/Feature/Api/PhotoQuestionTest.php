<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\PhotoQuestion;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotoQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_visible_to_all_roles_scoped_to_their_restaurant(): void
    {
        $restaurant = $this->createRestaurant();
        $other = Restaurant::create(['name' => 'Каста', 'slug' => 'kasta', 'is_active' => true]);

        PhotoQuestion::create(['restaurant_id' => $restaurant->id, 'question' => 'Фото зала', 'sort_order' => 1, 'is_active' => true]);
        PhotoQuestion::create(['restaurant_id' => $other->id, 'question' => 'Чужой ресторан', 'sort_order' => 1, 'is_active' => true]);

        $user = $this->createUserFor($restaurant, Role::Staff);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/photo-questions?restaurant_id={$restaurant->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.question', 'Фото зала');
    }

    public function test_store_is_forbidden_for_staff_and_manager(): void
    {
        $restaurant = $this->createRestaurant();

        foreach ([Role::Staff, Role::Manager] as $role) {
            $user = $this->createUserFor($restaurant, $role);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/photo-questions', [
                    'restaurant_id' => $restaurant->id,
                    'question' => 'Новое задание',
                ]);

            $response->assertForbidden();
        }
    }

    public function test_store_update_and_delete_succeed_for_director_and_admin(): void
    {
        $restaurant = $this->createRestaurant();

        foreach ([Role::Director, Role::Admin] as $role) {
            $user = $this->createUserFor($restaurant, $role);

            $store = $this->actingAs($user, 'sanctum')
                ->postJson('/api/photo-questions', [
                    'restaurant_id' => $restaurant->id,
                    'question' => 'Новое задание',
                ]);

            $store->assertCreated()->assertJsonPath('data.question', 'Новое задание');
            $id = $store->json('data.id');

            $update = $this->actingAs($user, 'sanctum')
                ->patchJson("/api/photo-questions/{$id}", ['question' => 'Обновлённое задание']);

            $update->assertOk()->assertJsonPath('data.question', 'Обновлённое задание');

            $delete = $this->actingAs($user, 'sanctum')->deleteJson("/api/photo-questions/{$id}");

            $delete->assertNoContent();
            $this->assertDatabaseMissing('photo_questions', ['id' => $id]);
        }
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
}
