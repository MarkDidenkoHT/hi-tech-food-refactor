<?php

namespace App\Services\Calendar;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WooCommerceReservationService
{
    /**
     * @param  array<string, array{key?: string, secret?: string, reservations_token?: string}>  $credentials
     */
    public function __construct(
        private readonly array $credentials,
    ) {}

    /**
     * Get table reservations from a WooCommerce/WordPress store as read-only
     * calendar events, cached briefly. Returns an empty list when the store
     * has no reservations token configured.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getReservations(string $domain): array
    {
        $token = $this->credentials[$domain]['reservations_token'] ?? null;

        if ($token === null || $token === '') {
            return [];
        }

        return Cache::remember("woocommerce-reservations:{$domain}", now()->addMinutes(5), function () use ($domain, $token) {
            $response = Http::withToken($token)
                ->acceptJson()
                ->get("https://{$domain}/wp-json/wc/v3/reservations");

            if (! $response->successful()) {
                Log::warning("Failed to fetch WooCommerce reservations from {$domain}: {$response->status()}");

                return [];
            }

            $reservations = $response->json();

            if (! is_array($reservations)) {
                return [];
            }

            return array_values(array_map(
                fn (array $reservation) => $this->normalize($reservation),
                array_filter($reservations, 'is_array'),
            ));
        });
    }

    /**
     * Normalize a raw WP reservation into the calendar event shape used by the
     * frontend. These events are read-only (source = "wordpress").
     *
     * @param  array<string, mixed>  $reservation
     * @return array<string, mixed>
     */
    private function normalize(array $reservation): array
    {
        $dateTime = $reservation['date_time'] ?? $reservation['reservation_date'] ?? null;
        $date = null;
        $time = null;

        if (is_string($dateTime) && $dateTime !== '') {
            $timestamp = strtotime($dateTime);

            if ($timestamp !== false) {
                $date = date('Y-m-d', $timestamp);
                $time = date('H:i', $timestamp);
            }
        }

        $name = (string) ($reservation['name'] ?? '');
        $location = (string) ($reservation['location'] ?? '');

        $notes = trim((string) ($reservation['notes'] ?? ''));

        if ($location !== '') {
            $notes = $notes === '' ? "Локация: {$location}" : "Локация: {$location}\n{$notes}";
        }

        return [
            'id' => 'wp_'.($reservation['id'] ?? uniqid()),
            'source' => 'wordpress',
            'editable' => false,
            'title' => $name !== '' ? "Бронь: {$name}" : 'Бронирование стола',
            'event_date' => $date,
            'event_time' => $time,
            'guests' => (int) ($reservation['person'] ?? $reservation['guests'] ?? 0),
            'contact' => (string) ($reservation['phone'] ?? $reservation['contact'] ?? ''),
            'notes' => $notes,
            'type_name' => 'Бронь',
            'type_color' => '#3498db',
        ];
    }
}
