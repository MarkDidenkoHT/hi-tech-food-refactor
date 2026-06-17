<?php

namespace App\Services\Feedback;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WooCommerceFeedbackService
{
    /**
     * @param  array<string, array{key: string, secret: string}>  $credentials
     */
    public function __construct(
        private readonly array $credentials,
    ) {}

    /**
     * Get customer feedback submissions for a WooCommerce store, cached briefly.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFeedbacks(string $domain): array
    {
        return Cache::remember("woocommerce-feedbacks:{$domain}", now()->addMinutes(5), function () use ($domain) {
            return $this->fetch($domain, 'feedbacks');
        });
    }

    /**
     * Get order reviews for a WooCommerce store, cached briefly.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrderReviews(string $domain): array
    {
        return Cache::remember("woocommerce-order-reviews:{$domain}", now()->addMinutes(5), function () use ($domain) {
            return $this->fetch($domain, 'order-reviews');
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetch(string $domain, string $endpoint): array
    {
        $credentials = $this->credentials[$domain] ?? null;

        if ($credentials === null) {
            Log::warning("No WooCommerce credentials configured for domain: {$domain}");

            return [];
        }

        $response = Http::withBasicAuth($credentials['key'], $credentials['secret'])
            ->get("https://{$domain}/wp-json/wc/v3/{$endpoint}", [
                'per_page' => 100,
            ]);

        if (! $response->successful()) {
            Log::warning("Failed to fetch WooCommerce {$endpoint} from {$domain}: {$response->status()}");

            return [];
        }

        $items = $response->json();

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            fn ($item) => $this->normalize($item),
            array_filter($items, fn ($item) => is_array($item) || is_string($item)),
        ));
    }

    /**
     * @param  array<string, mixed>|string  $item
     * @return array<string, mixed>
     */
    private function normalize(array|string $item): array
    {
        if (is_string($item)) {
            $decoded = json_decode($item, true);
            $item = is_array($decoded) ? $decoded : ['message' => $item];
        }

        $name = $item['name'] ?? $item['author'] ?? '';

        if ($name === '' && isset($item['title']) && preg_match('/Имя: «([^»]+)»/u', (string) $item['title'], $matches)) {
            $name = $matches[1];
        }

        if ($name === 'Доставка адрес') {
            $name = '';
        }

        return [
            'date' => $item['date'] ?? $item['date_created'] ?? null,
            'name' => $name,
            'email' => $item['email'] ?? null,
            'phone' => $item['phone'] ?? null,
            'waiter' => $item['waiter'] ?? null,
            'message' => $item['message'] ?? null,
            'review' => $item['review'] ?? null,
            'rating' => isset($item['rating']) ? (int) $item['rating'] : null,
            'website' => $item['website'] ?? null,
        ];
    }
}
