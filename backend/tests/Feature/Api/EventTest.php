<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Event;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_local_events_for_restaurant(): void
    {
        $restaurant = $this->createRestaurant();
        Event::create([
            'restaurant_id' => $restaurant->id,
            'event_type' => 'banquet',
            'title' => 'Юбилей',
            'event_date' => '2026-06-20',
            'event_time' => '19:00:00',
            'guests' => 20,
        ]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/events?restaurant_id={$restaurant->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Юбилей')
            ->assertJsonPath('data.0.event_date', '2026-06-20')
            ->assertJsonPath('data.0.event_time', '19:00')
            ->assertJsonPath('data.0.source', 'local')
            ->assertJsonPath('data.0.editable', true)
            ->assertJsonPath('data.0.event_type', 'banquet')
            ->assertJsonPath('data.0.type_name', 'Банкет');
    }

    public function test_index_merges_woocommerce_reservations(): void
    {
        config(['services.woocommerce' => [
            'casta.md' => ['reservations_token' => 'test-token'],
        ]]);

        Http::fake([
            'https://casta.md/wp-json/wc/v3/reservations*' => Http::response([
                [
                    'id' => 77,
                    'name' => 'Иван',
                    'person' => 4,
                    'phone' => '+373690000',
                    'date_time' => '2026-06-21 18:30:00',
                    'location' => 'Зал 1',
                    'notes' => 'у окна',
                ],
            ], 200),
        ]);

        $restaurant = Restaurant::create([
            'name' => 'Каста', 'slug' => 'kasta', 'is_active' => true, 'woocommerce_domain' => 'casta.md',
        ]);
        Event::create([
            'restaurant_id' => $restaurant->id,
            'title' => 'Локальное',
            'event_date' => '2026-06-20',
        ]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/events?restaurant_id={$restaurant->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => 'wp_77',
                'source' => 'wordpress',
                'editable' => false,
                'title' => 'Бронь: Иван',
                'event_date' => '2026-06-21',
                'event_time' => '18:30',
                'guests' => 4,
            ]);
    }

    public function test_index_is_forbidden_for_staff(): void
    {
        $restaurant = $this->createRestaurant();
        $user = $this->createUserFor($restaurant, Role::Staff);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events?restaurant_id={$restaurant->id}")
            ->assertForbidden();
    }

    public function test_index_is_forbidden_for_a_non_belonging_user(): void
    {
        $restaurant = $this->createRestaurant();
        $other = Restaurant::create(['name' => 'Другой', 'slug' => 'other', 'is_active' => true]);
        $user = $this->createUserFor($other, Role::Manager);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events?restaurant_id={$restaurant->id}")
            ->assertForbidden();
    }

    public function test_store_creates_event(): void
    {
        $restaurant = $this->createRestaurant();
        $user = $this->createUserFor($restaurant, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', [
                'restaurant_id' => $restaurant->id,
                'event_type' => 'banquet',
                'title' => 'Свадьба',
                'event_date' => '2026-07-01',
                'event_time' => '17:00',
                'guests' => 50,
                'contact' => '+373690001',
                'notes' => 'Большой зал',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Свадьба')
            ->assertJsonPath('data.event_type', 'banquet')
            ->assertJsonPath('data.event_time', '17:00')
            ->assertJsonPath('data.guests', 50);

        $this->assertDatabaseHas('events', [
            'restaurant_id' => $restaurant->id,
            'title' => 'Свадьба',
            'event_type' => 'banquet',
            'guests' => 50,
        ]);
    }

    public function test_store_rejects_unknown_event_type(): void
    {
        $restaurant = $this->createRestaurant();
        $user = $this->createUserFor($restaurant, Role::Manager);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', [
                'restaurant_id' => $restaurant->id,
                'event_type' => 'wedding',
                'title' => 'Событие',
                'event_date' => '2026-07-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_type');
    }

    public function test_update_modifies_event(): void
    {
        $restaurant = $this->createRestaurant();
        $event = Event::create([
            'restaurant_id' => $restaurant->id,
            'title' => 'Старое',
            'event_date' => '2026-06-20',
        ]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/events/{$event->id}", ['title' => 'Новое', 'guests' => 8])
            ->assertOk()
            ->assertJsonPath('data.title', 'Новое')
            ->assertJsonPath('data.guests', 8);
    }

    public function test_destroy_deletes_event(): void
    {
        $restaurant = $this->createRestaurant();
        $event = Event::create([
            'restaurant_id' => $restaurant->id,
            'title' => 'Удалить',
            'event_date' => '2026-06-20',
        ]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/events/{$event->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_update_is_forbidden_for_a_non_belonging_user(): void
    {
        $restaurant = $this->createRestaurant();
        $event = Event::create([
            'restaurant_id' => $restaurant->id,
            'title' => 'Событие',
            'event_date' => '2026-06-20',
        ]);
        $other = Restaurant::create(['name' => 'Другой', 'slug' => 'other', 'is_active' => true]);
        $user = $this->createUserFor($other, Role::Manager);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/events/{$event->id}", ['title' => 'Взлом'])
            ->assertForbidden();
    }

    private function createRestaurant(): Restaurant
    {
        return Restaurant::create(['name' => 'Каста', 'slug' => 'kasta', 'is_active' => true]);
    }

    private function createUserFor(Restaurant $restaurant, Role $role): User
    {
        $user = User::factory()->role($role)->create();
        $user->restaurants()->attach($restaurant);

        return $user;
    }
}
