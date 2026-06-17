<?php

namespace Tests\Unit\Services;

use App\Services\Menu\WooCommerceMenuService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooCommerceMenuServiceTest extends TestCase
{
    public function test_it_paginates_until_a_short_page_is_returned(): void
    {
        $firstPage = array_map(fn (int $i) => ['name' => "Товар {$i}"], range(1, 100));
        $secondPage = [['name' => 'Последний товар']];

        Http::fake([
            'https://casta.md/wp-json/wc/v3/products*' => Http::sequence()
                ->push($firstPage, 200)
                ->push($secondPage, 200),
        ]);

        $service = new WooCommerceMenuService([
            'casta.md' => ['key' => 'key', 'secret' => 'secret'],
        ]);

        $names = $service->getProductNames('casta.md');

        $this->assertCount(101, $names);
        $this->assertSame('Товар 1', $names[0]);
        $this->assertSame('Последний товар', $names[100]);
        Http::assertSentCount(2);
    }

    public function test_it_caches_results_between_calls(): void
    {
        Http::fake([
            'https://casta.md/wp-json/wc/v3/products*' => Http::response([
                ['name' => 'Товар 1'],
            ], 200),
        ]);

        $service = new WooCommerceMenuService([
            'casta.md' => ['key' => 'key', 'secret' => 'secret'],
        ]);

        $service->getProductNames('casta.md');
        $service->getProductNames('casta.md');

        Http::assertSentCount(1);
    }

    public function test_it_returns_empty_array_when_no_credentials_configured(): void
    {
        $service = new WooCommerceMenuService([]);

        $this->assertSame([], $service->getProductNames('unknown.md'));
    }

    public function test_it_returns_empty_array_on_request_failure(): void
    {
        Http::fake([
            'https://casta.md/wp-json/wc/v3/products*' => Http::response(null, 500),
        ]);

        $service = new WooCommerceMenuService([
            'casta.md' => ['key' => 'key', 'secret' => 'secret'],
        ]);

        $this->assertSame([], $service->getProductNames('casta.md'));
    }
}
