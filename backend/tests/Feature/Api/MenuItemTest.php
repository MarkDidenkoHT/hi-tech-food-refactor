<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_visible_to_all_roles_for_a_hardcoded_menu_restaurant(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);

        $user = $this->createUserFor($restaurant, Role::Staff);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/menu-items?restaurant_id={$restaurant->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Окрошка с говядиной');
    }

    public function test_store_update_and_delete_are_forbidden_for_staff_and_manager(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        $item = MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);

        foreach ([Role::Staff, Role::Manager] as $role) {
            $user = $this->createUserFor($restaurant, $role);

            $this->actingAs($user, 'sanctum')
                ->postJson('/api/menu-items', ['restaurant_id' => $restaurant->id, 'name' => 'Новая позиция'])
                ->assertForbidden();

            $this->actingAs($user, 'sanctum')
                ->patchJson("/api/menu-items/{$item->id}", ['name' => 'Изменено'])
                ->assertForbidden();

            $this->actingAs($user, 'sanctum')
                ->deleteJson("/api/menu-items/{$item->id}")
                ->assertForbidden();
        }
    }

    public function test_store_update_and_delete_succeed_for_director_and_admin(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();

        foreach ([Role::Director, Role::Admin] as $role) {
            $user = $this->createUserFor($restaurant, $role);

            $store = $this->actingAs($user, 'sanctum')
                ->postJson('/api/menu-items', ['restaurant_id' => $restaurant->id, 'name' => 'Новая позиция']);

            $store->assertCreated()->assertJsonPath('data.name', 'Новая позиция');
            $id = $store->json('data.id');

            $update = $this->actingAs($user, 'sanctum')
                ->patchJson("/api/menu-items/{$id}", ['name' => 'Обновлённая позиция']);

            $update->assertOk()->assertJsonPath('data.name', 'Обновлённая позиция');

            $delete = $this->actingAs($user, 'sanctum')->deleteJson("/api/menu-items/{$id}");

            $delete->assertNoContent();
            $this->assertDatabaseMissing('menu_items', ['id' => $id]);
        }
    }

    public function test_store_fails_for_woocommerce_backed_restaurant_even_for_admin(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Каста',
            'slug' => 'kasta',
            'is_active' => true,
            'woocommerce_domain' => 'casta.md',
        ]);

        $user = $this->createUserFor($restaurant, Role::Admin);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/menu-items', ['restaurant_id' => $restaurant->id, 'name' => 'Новая позиция']);

        $response->assertUnprocessable()->assertJsonValidationErrors('restaurant_id');
    }

    private function createHardcodedMenuRestaurant(): Restaurant
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
}
