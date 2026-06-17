<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppShellTest extends TestCase
{
    /**
     * The Mini App shell loads successfully.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/app');

        $response->assertStatus(200);
    }

    /**
     * The root URL redirects to the Mini App.
     */
    public function test_the_root_url_redirects_to_the_app(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/app');
    }
}
