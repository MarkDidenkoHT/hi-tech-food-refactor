<?php

namespace App\Services\Menu;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WooCommerceMenuService
{
    /**
     * @param  array<string, array{key: string, secret: string}>  $credentials
     */
    public function __construct(
        private readonly array $credentials,
    ) {}

    /**
     * Get the list of product names from a WooCommerce store, cached for an hour.
     *
     * @return array<int, string>
     */
    public function getProductNames(string $domain): array
    {
        return Cache::remember("woocommerce-menu:{$domain}", now()->addHour(), function () use ($domain) {
            $credentials = $this->credentials[$domain] ?? null;

            if ($credentials === null) {
                Log::warning("No WooCommerce credentials configured for domain: {$domain}");

                return [];
            }

            $names = [];

            for ($page = 1; $page <= 4; $page++) {
                $response = Http::withBasicAuth($credentials['key'], $credentials['secret'])
                    ->get("https://{$domain}/wp-json/wc/v3/products", [
                        'per_page' => 100,
                        'page' => $page,
                    ]);

                if (! $response->successful()) {
                    Log::warning("Failed to fetch WooCommerce products from {$domain} (page {$page}): {$response->status()}");

                    break;
                }

                $products = $response->json();

                if (empty($products)) {
                    break;
                }

                foreach ($products as $product) {
                    $names[] = $product['name'];
                }

                if (count($products) < 100) {
                    break;
                }
            }

            return $names;
        });
    }
}
