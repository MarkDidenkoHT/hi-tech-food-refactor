<?php

namespace Tests\Feature\Console;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_admin(): void
    {
        $this->artisan('app:create-admin', [
            'telegram_id' => 555000,
            'first_name' => 'Марк',
            '--username' => 'mark',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'telegram_id' => 555000,
            'first_name' => 'Марк',
            'username' => 'mark',
            'role' => Role::Admin->value,
            'is_active' => true,
        ]);
    }

    public function test_it_promotes_an_existing_user(): void
    {
        $user = User::factory()->role(Role::Staff)->create(['telegram_id' => 555111, 'is_active' => false]);

        $this->artisan('app:create-admin', [
            'telegram_id' => 555111,
            'first_name' => 'Director',
        ])->assertSuccessful();

        $user->refresh();
        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue($user->is_active);
    }
}
