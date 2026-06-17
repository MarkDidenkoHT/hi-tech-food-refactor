<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_unavailable_for_a_restaurant_without_woocommerce_domain(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Санторини Подворье',
            'slug' => 'santorini-podvore',
            'is_active' => true,
        ]);

        $user = $this->createUserFor($restaurant, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/feedback?restaurant_id={$restaurant->id}");

        $response->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('feedbacks', [])
            ->assertJsonPath('order_reviews', []);
    }

    public function test_index_returns_normalized_feedbacks_and_order_reviews_for_woocommerce_restaurant(): void
    {
        Http::fake([
            'https://casta.md/wp-json/wc/v3/feedbacks*' => Http::response([
                [
                    'id' => 24528,
                    'title' => 'Имя: «Сергей», Сайт: «casta.md»',
                    'website' => 'casta.md',
                    'name' => 'Сергей',
                    'phone' => '+373-69655160',
                    'email' => '',
                    'message' => 'Очень вкусно',
                    'waiter' => '1004750',
                    'date' => '2026-05-31 18:26:20',
                ],
            ], 200),
            'https://casta.md/wp-json/wc/v3/order-reviews*' => Http::response([
                [
                    'post_id' => 6248,
                    'review' => 'Отличный заказ',
                    'rating' => 5,
                    'name' => 'Доставка адрес',
                    'email' => '',
                    'phone' => '77706999',
                    'date' => '2023-11-11 13:19:58',
                ],
            ], 200),
        ]);

        $restaurant = Restaurant::create([
            'name' => 'Каста',
            'slug' => 'kasta',
            'is_active' => true,
            'woocommerce_domain' => 'casta.md',
        ]);

        $user = $this->createUserFor($restaurant, Role::Staff);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/feedback?restaurant_id={$restaurant->id}");

        $response->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('feedbacks.0.name', 'Сергей')
            ->assertJsonPath('feedbacks.0.message', 'Очень вкусно')
            ->assertJsonPath('feedbacks.0.waiter', '1004750')
            ->assertJsonPath('order_reviews.0.review', 'Отличный заказ')
            ->assertJsonPath('order_reviews.0.rating', 5)
            ->assertJsonPath('order_reviews.0.name', '');
    }

    public function test_index_is_forbidden_for_a_non_belonging_user(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Каста',
            'slug' => 'kasta',
            'is_active' => true,
            'woocommerce_domain' => 'casta.md',
        ]);

        $other = Restaurant::create([
            'name' => 'Санторини Подворье',
            'slug' => 'santorini-podvore',
            'is_active' => true,
        ]);

        $user = $this->createUserFor($other, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/feedback?restaurant_id={$restaurant->id}");

        $response->assertForbidden();
    }

    private function createUserFor(Restaurant $restaurant, Role $role): User
    {
        $user = User::factory()->role($role)->create();
        $user->restaurants()->attach($restaurant);

        return $user;
    }
}
