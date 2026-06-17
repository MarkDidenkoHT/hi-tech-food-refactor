<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Enums\StoplistSection;
use App\Enums\StoplistStatus;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\StoplistEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoplistTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_unresolved_entries_grouped_by_section_and_status(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        $user = $this->createUserFor($restaurant, Role::Manager);

        $this->createEntry($restaurant, $user, StoplistSection::Kitchen, StoplistStatus::Stop, 'Окрошка');
        $resolved = $this->createEntry($restaurant, $user, StoplistSection::Bar, StoplistStatus::Limit, 'Вино');
        $resolved->update(['resolved_at' => now(), 'resolved_by' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/stoplist?restaurant_id={$restaurant->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'kitchen.stop')
            ->assertJsonCount(0, 'kitchen.limit')
            ->assertJsonCount(0, 'bar.stop')
            ->assertJsonCount(0, 'bar.limit')
            ->assertJsonPath('kitchen.stop.0.item', 'Окрошка')
            ->assertJsonPath('editable_menu', true);
    }

    public function test_index_is_forbidden_for_a_non_belonging_user(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        $other = Restaurant::create(['name' => 'Каста', 'slug' => 'kasta', 'is_active' => true]);
        $user = $this->createUserFor($other, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/stoplist?restaurant_id={$restaurant->id}");

        $response->assertForbidden();
    }

    public function test_store_creates_entry_for_hardcoded_menu_restaurant(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist', [
                'restaurant_id' => $restaurant->id,
                'section' => 'kitchen',
                'status' => 'stop',
                'item' => 'Окрошка с говядиной',
                'comment' => 'Закончилась полностью',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.item', 'Окрошка с говядиной')
            ->assertJsonPath('data.section', 'kitchen')
            ->assertJsonPath('data.status', 'stop')
            ->assertJsonPath('data.created_by', trim("{$user->first_name} {$user->last_name}"));

        $this->assertDatabaseHas('stoplist_entries', [
            'restaurant_id' => $restaurant->id,
            'item' => 'Окрошка с говядиной',
            'created_by' => $user->id,
        ]);
    }

    public function test_store_rejects_item_not_in_menu(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist', [
                'restaurant_id' => $restaurant->id,
                'section' => 'kitchen',
                'status' => 'stop',
                'item' => 'Несуществующее блюдо',
                'comment' => 'Закончилась полностью',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('item');
    }

    public function test_store_rejects_invalid_comments(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $invalidComments = [
            'ок', // too short
            'Заааааакончилась', // more than 3 repeated characters in a row
            'нет', // generic phrase
        ];

        foreach ($invalidComments as $comment) {
            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/stoplist', [
                    'restaurant_id' => $restaurant->id,
                    'section' => 'kitchen',
                    'status' => 'stop',
                    'item' => 'Окрошка с говядиной',
                    'comment' => $comment,
                ]);

            $response->assertUnprocessable()->assertJsonValidationErrors('comment');
        }
    }

    public function test_store_creates_play_entry_without_comment(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist', [
                'restaurant_id' => $restaurant->id,
                'section' => 'kitchen',
                'status' => 'play',
                'item' => 'Окрошка с говядиной',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'play')
            ->assertJsonPath('data.comment', '');

        $index = $this->actingAs($user, 'sanctum')
            ->getJson("/api/stoplist?restaurant_id={$restaurant->id}");

        $index->assertOk()->assertJsonCount(1, 'kitchen.play');
    }

    public function test_store_rejects_invalid_comment_for_play_when_provided(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist', [
                'restaurant_id' => $restaurant->id,
                'section' => 'kitchen',
                'status' => 'play',
                'item' => 'Окрошка с говядиной',
                'comment' => 'нет',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('comment');
    }

    public function test_store_and_resolve_send_play_specific_telegram_messages(): void
    {
        Http::fake();

        $restaurant = $this->createHardcodedMenuRestaurant();
        $restaurant->update(['telegram_group_chat_id' => 123456]);
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $store = $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist', [
                'restaurant_id' => $restaurant->id,
                'section' => 'kitchen',
                'status' => 'play',
                'item' => 'Окрошка с говядиной',
            ]);

        $store->assertCreated();
        $entryId = $store->json('data.id');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'ПРОДАЁМ'));

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/stoplist/{$entryId}/resolve")
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], 'Распродано'));
    }

    public function test_store_sends_telegram_message_when_chat_id_configured(): void
    {
        Http::fake();

        $restaurant = $this->createHardcodedMenuRestaurant();
        $restaurant->update(['telegram_group_chat_id' => 123456]);
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist', [
                'restaurant_id' => $restaurant->id,
                'section' => 'kitchen',
                'status' => 'stop',
                'item' => 'Окрошка с говядиной',
                'comment' => 'Закончилась полностью',
            ])
            ->assertCreated();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && $request['chat_id'] === 123456;
        });
    }

    public function test_store_escapes_user_text_in_telegram_message(): void
    {
        Http::fake();

        $restaurant = $this->createHardcodedMenuRestaurant();
        $restaurant->update(['telegram_group_chat_id' => 123456]);
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist', [
                'restaurant_id' => $restaurant->id,
                'section' => 'kitchen',
                'status' => 'stop',
                'item' => 'Окрошка с говядиной',
                'comment' => 'сломалось <b>срочно</b>',
            ])
            ->assertCreated();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && str_contains($request['text'], 'сломалось &lt;b&gt;срочно&lt;/b&gt;')
                && ! str_contains($request['text'], '<b>срочно</b>');
        });
    }

    public function test_store_does_not_send_telegram_message_when_chat_id_not_configured(): void
    {
        Http::fake();

        $restaurant = $this->createHardcodedMenuRestaurant();
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist', [
                'restaurant_id' => $restaurant->id,
                'section' => 'kitchen',
                'status' => 'stop',
                'item' => 'Окрошка с говядиной',
                'comment' => 'Закончилась полностью',
            ])
            ->assertCreated();

        Http::assertNothingSent();
    }

    public function test_store_rejects_duplicate_active_item_in_same_section(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $this->createEntry($restaurant, $user, StoplistSection::Kitchen, StoplistStatus::Stop, 'Окрошка с говядиной');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist', [
                'restaurant_id' => $restaurant->id,
                'section' => 'kitchen',
                'status' => 'limit',
                'item' => 'Окрошка с говядиной',
                'comment' => 'Осталось мало',
            ]);

        $response->assertStatus(422)->assertJsonPath('message', 'Эта позиция уже в стоп-листе.');
    }

    public function test_store_allows_same_item_in_different_section(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Окрошка с говядиной', 'sort_order' => 1, 'is_active' => true]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $this->createEntry($restaurant, $user, StoplistSection::Kitchen, StoplistStatus::Stop, 'Окрошка с говядиной');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist', [
                'restaurant_id' => $restaurant->id,
                'section' => 'bar',
                'status' => 'stop',
                'item' => 'Окрошка с говядиной',
                'comment' => 'Осталось мало',
            ]);

        $response->assertCreated();
    }

    public function test_send_posts_grouped_summary_to_telegram_group(): void
    {
        Http::fake();

        $restaurant = $this->createHardcodedMenuRestaurant();
        $restaurant->update(['telegram_group_chat_id' => 123456]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $this->createEntry($restaurant, $user, StoplistSection::Kitchen, StoplistStatus::Stop, 'Окрошка');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist/send', ['restaurant_id' => $restaurant->id]);

        $response->assertOk()->assertJsonPath('message', 'Стоп-лист отправлен в группу.');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && $request['chat_id'] === 123456
            && str_contains($request['text'], 'СТОП')
            && str_contains($request['text'], 'Окрошка'));
    }

    public function test_send_fails_when_telegram_group_not_configured(): void
    {
        Http::fake();

        $restaurant = $this->createHardcodedMenuRestaurant();
        $user = $this->createUserFor($restaurant, Role::Manager);

        $this->createEntry($restaurant, $user, StoplistSection::Kitchen, StoplistStatus::Stop, 'Окрошка');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist/send', ['restaurant_id' => $restaurant->id]);

        $response->assertStatus(422)->assertJsonPath('message', 'Для этого ресторана не настроена группа в Telegram.');
        Http::assertNothingSent();
    }

    public function test_send_fails_when_stoplist_is_empty(): void
    {
        Http::fake();

        $restaurant = $this->createHardcodedMenuRestaurant();
        $restaurant->update(['telegram_group_chat_id' => 123456]);
        $user = $this->createUserFor($restaurant, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist/send', ['restaurant_id' => $restaurant->id]);

        $response->assertStatus(422)->assertJsonPath('message', 'Стоп-лист пуст.');
        Http::assertNothingSent();
    }

    public function test_send_is_forbidden_for_a_non_belonging_user(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        $other = Restaurant::create(['name' => 'Каста', 'slug' => 'kasta', 'is_active' => true]);
        $user = $this->createUserFor($other, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist/send', ['restaurant_id' => $restaurant->id]);

        $response->assertForbidden();
    }

    public function test_resolve_marks_entry_resolved_and_removes_it_from_active_list(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        $user = $this->createUserFor($restaurant, Role::Manager);
        $entry = $this->createEntry($restaurant, $user, StoplistSection::Kitchen, StoplistStatus::Stop, 'Окрошка');

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/stoplist/{$entry->id}/resolve");

        $response->assertOk()->assertJsonPath('data.id', $entry->id);

        $entry->refresh();
        $this->assertNotNull($entry->resolved_at);
        $this->assertSame($user->id, $entry->resolved_by);

        $index = $this->actingAs($user, 'sanctum')
            ->getJson("/api/stoplist?restaurant_id={$restaurant->id}");

        $index->assertOk()->assertJsonCount(0, 'kitchen.stop');
    }

    public function test_resolve_is_forbidden_for_a_non_belonging_user(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        $other = Restaurant::create(['name' => 'Каста', 'slug' => 'kasta', 'is_active' => true]);
        $owner = $this->createUserFor($restaurant, Role::Manager);
        $entry = $this->createEntry($restaurant, $owner, StoplistSection::Kitchen, StoplistStatus::Stop, 'Окрошка');

        $intruder = $this->createUserFor($other, Role::Manager);

        $response = $this->actingAs($intruder, 'sanctum')
            ->patchJson("/api/stoplist/{$entry->id}/resolve");

        $response->assertForbidden();
    }

    public function test_store_for_woocommerce_restaurant_accepts_item_from_remote_menu(): void
    {
        Http::fake([
            'https://casta.md/wp-json/wc/v3/products*' => Http::response([
                ['name' => 'Пицца Маргарита'],
            ], 200),
        ]);

        $restaurant = Restaurant::create([
            'name' => 'Каста',
            'slug' => 'kasta',
            'is_active' => true,
            'woocommerce_domain' => 'casta.md',
        ]);

        $user = $this->createUserFor($restaurant, Role::Manager);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/stoplist', [
                'restaurant_id' => $restaurant->id,
                'section' => 'bar',
                'status' => 'limit',
                'item' => 'Пицца Маргарита',
                'comment' => 'Осталось мало',
            ]);

        $response->assertCreated()->assertJsonPath('data.item', 'Пицца Маргарита');
    }

    public function test_menu_returns_the_full_item_list(): void
    {
        $restaurant = $this->createHardcodedMenuRestaurant();
        $user = $this->createUserFor($restaurant, Role::Manager);

        for ($i = 1; $i <= 25; $i++) {
            MenuItem::create([
                'restaurant_id' => $restaurant->id,
                'name' => "Позиция {$i}",
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/stoplist/menu?restaurant_id={$restaurant->id}");

        $response->assertOk()->assertJsonCount(25, 'data');
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

    private function createEntry(Restaurant $restaurant, User $user, StoplistSection $section, StoplistStatus $status, string $item): StoplistEntry
    {
        return StoplistEntry::create([
            'restaurant_id' => $restaurant->id,
            'section' => $section,
            'status' => $status,
            'item' => $item,
            'comment' => 'Тестовый комментарий',
            'created_by' => $user->id,
        ]);
    }
}
