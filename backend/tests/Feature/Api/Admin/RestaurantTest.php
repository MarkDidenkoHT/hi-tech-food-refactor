<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_includes_the_telegram_group_chat_id(): void
    {
        $admin = $this->createAdmin();

        Restaurant::create([
            'name' => 'Тоскана',
            'slug' => 'toskana',
            'is_active' => true,
            'telegram_group_chat_id' => -100123456789,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/restaurants');

        $response->assertOk()->assertJsonPath('data.0.telegram_group_chat_id', -100123456789);
    }

    public function test_update_sets_and_clears_the_telegram_group_chat_id(): void
    {
        $admin = $this->createAdmin();
        $restaurant = Restaurant::create(['name' => 'Тоскана', 'slug' => 'toskana', 'is_active' => true]);

        $update = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/restaurants/{$restaurant->id}", ['telegram_group_chat_id' => -100123456789]);

        $update->assertOk()->assertJsonPath('data.telegram_group_chat_id', -100123456789);

        $clear = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/restaurants/{$restaurant->id}", ['telegram_group_chat_id' => null]);

        $clear->assertOk()->assertJsonPath('data.telegram_group_chat_id', null);
    }

    public function test_endpoints_are_forbidden_for_non_admins(): void
    {
        $restaurant = Restaurant::create(['name' => 'Тоскана', 'slug' => 'toskana', 'is_active' => true]);
        $user = User::factory()->role(Role::Director)->create();
        $user->restaurants()->attach($restaurant);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/restaurants')->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/admin/restaurants/{$restaurant->id}", ['telegram_group_chat_id' => 1])
            ->assertForbidden();
    }

    private function createAdmin(): User
    {
        return User::factory()->role(Role::Admin)->create();
    }
}
