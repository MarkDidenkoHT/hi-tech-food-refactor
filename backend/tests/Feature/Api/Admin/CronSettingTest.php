<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_defaults_to_disabled(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/cron-settings');

        $response->assertOk()->assertJsonPath('photo_question_reminders_enabled', false);
    }

    public function test_update_toggles_the_setting(): void
    {
        $admin = $this->createAdmin();

        $enable = $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/cron-settings', ['photo_question_reminders_enabled' => true]);

        $enable->assertOk()->assertJsonPath('photo_question_reminders_enabled', true);

        $show = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/cron-settings');

        $show->assertOk()->assertJsonPath('photo_question_reminders_enabled', true);

        $disable = $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/cron-settings', ['photo_question_reminders_enabled' => false]);

        $disable->assertOk()->assertJsonPath('photo_question_reminders_enabled', false);
    }

    public function test_endpoints_are_forbidden_for_non_admins(): void
    {
        $restaurant = Restaurant::create(['name' => 'Тоскана', 'slug' => 'toskana', 'is_active' => true]);
        $user = User::factory()->role(Role::Director)->create();
        $user->restaurants()->attach($restaurant);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/cron-settings')->assertForbidden();
        $this->actingAs($user, 'sanctum')->patchJson('/api/admin/cron-settings', ['photo_question_reminders_enabled' => true])->assertForbidden();
    }

    private function createAdmin(): User
    {
        return User::factory()->role(Role::Admin)->create();
    }
}
