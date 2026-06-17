<?php

namespace Tests\Feature\Console;

use App\Console\Commands\SendPhotoQuestionReminders;
use App\Models\PhotoQuestion;
use App\Models\Restaurant;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendPhotoQuestionRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_nothing_when_disabled(): void
    {
        Http::fake();

        $restaurant = $this->createRestaurant();
        $this->createQuestions($restaurant);

        $this->artisan('app:send-photo-question-reminders')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_it_sends_the_next_question_and_cycles_through_them(): void
    {
        Http::fake();

        Setting::setBool(SendPhotoQuestionReminders::SETTING_KEY, true);

        $restaurant = $this->createRestaurant();
        [$first, $second] = $this->createQuestions($restaurant);

        $this->artisan('app:send-photo-question-reminders')->assertExitCode(0);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], $first->question));

        $this->assertSame($first->id, $restaurant->refresh()->last_photo_question_id);

        $this->artisan('app:send-photo-question-reminders')->assertExitCode(0);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], $second->question));

        $this->assertSame($second->id, $restaurant->refresh()->last_photo_question_id);

        $this->artisan('app:send-photo-question-reminders')->assertExitCode(0);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
            && str_contains($request['text'], $first->question));

        $this->assertSame($first->id, $restaurant->refresh()->last_photo_question_id);
    }

    public function test_it_skips_restaurants_without_a_chat_id_or_questions(): void
    {
        Http::fake();

        Setting::setBool(SendPhotoQuestionReminders::SETTING_KEY, true);

        Restaurant::create(['name' => 'Без чата', 'slug' => 'no-chat', 'is_active' => true]);

        $restaurant = $this->createRestaurant();

        $this->artisan('app:send-photo-question-reminders')->assertExitCode(0);

        Http::assertNothingSent();
    }

    private function createRestaurant(): Restaurant
    {
        return Restaurant::create([
            'name' => 'Тоскана',
            'slug' => 'toskana',
            'is_active' => true,
            'telegram_group_chat_id' => 123456,
        ]);
    }

    /**
     * @return array{0: PhotoQuestion, 1: PhotoQuestion}
     */
    private function createQuestions(Restaurant $restaurant): array
    {
        $first = PhotoQuestion::create(['restaurant_id' => $restaurant->id, 'question' => 'Фото зала', 'sort_order' => 1, 'is_active' => true]);
        $second = PhotoQuestion::create(['restaurant_id' => $restaurant->id, 'question' => 'Фото кухни', 'sort_order' => 2, 'is_active' => true]);

        return [$first, $second];
    }
}
