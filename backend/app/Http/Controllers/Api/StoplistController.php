<?php

namespace App\Http\Controllers\Api;

use App\Enums\StoplistSection;
use App\Enums\StoplistStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreStoplistEntryRequest;
use App\Http\Resources\StoplistEntryResource;
use App\Models\Restaurant;
use App\Models\StoplistEntry;
use App\Services\Menu\MenuResolver;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StoplistController extends Controller
{
    public function __construct(
        private readonly MenuResolver $menuResolver,
        private readonly TelegramBotService $telegramBotService,
    ) {}

    /**
     * Show the active (unresolved) stoplist entries for a restaurant, grouped by section and status.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = (int) $request->query('restaurant_id');

        abort_unless($restaurantId > 0 && $request->user()->belongsToRestaurant($restaurantId), 403);

        $restaurant = Restaurant::findOrFail($restaurantId);

        $entries = StoplistEntry::where('restaurant_id', $restaurantId)
            ->whereNull('resolved_at')
            ->with(['creator'])
            ->orderBy('created_at')
            ->get();

        $grouped = [];

        foreach (StoplistSection::cases() as $section) {
            foreach (StoplistStatus::cases() as $status) {
                $grouped[$section->value][$status->value] = StoplistEntryResource::collection(
                    $entries->filter(fn (StoplistEntry $entry) => $entry->section === $section && $entry->status === $status)->values()
                );
            }
        }

        return response()->json([
            ...$grouped,
            'editable_menu' => $restaurant->woocommerce_domain === null,
            'telegram_configured' => $restaurant->telegram_group_chat_id !== null,
        ]);
    }

    /**
     * Search the available menu items for a restaurant.
     */
    public function menu(Request $request): JsonResponse
    {
        $restaurantId = (int) $request->query('restaurant_id');

        abort_unless($restaurantId > 0 && $request->user()->belongsToRestaurant($restaurantId), 403);

        $restaurant = Restaurant::findOrFail($restaurantId);

        $items = $this->menuResolver->getItems($restaurant);

        $search = mb_strtolower((string) $request->query('search', ''));

        if ($search !== '') {
            $items = array_values(array_filter(
                $items,
                fn (string $item) => str_contains(mb_strtolower($item), $search)
            ));
        }

        return response()->json([
            'data' => array_values($items),
        ]);
    }

    /**
     * Add a new stoplist entry.
     */
    public function store(StoreStoplistEntryRequest $request): StoplistEntryResource|JsonResponse
    {
        $data = $request->validated();

        $duplicateExists = StoplistEntry::where('restaurant_id', $data['restaurant_id'])
            ->where('section', $data['section'])
            ->whereNull('resolved_at')
            ->get()
            ->contains(fn (StoplistEntry $entry) => mb_strtolower($entry->item) === mb_strtolower($data['item']));

        if ($duplicateExists) {
            return response()->json(['message' => 'Эта позиция уже в стоп-листе.'], 422);
        }

        $entry = StoplistEntry::create([
            'restaurant_id' => $data['restaurant_id'],
            'section' => $data['section'],
            'status' => $data['status'],
            'item' => $data['item'],
            'comment' => $data['comment'] ?? '',
            'created_by' => $request->user()->id,
        ]);

        $entry->load(['creator']);

        $restaurant = Restaurant::findOrFail($data['restaurant_id']);

        if ($restaurant->telegram_group_chat_id !== null) {
            $this->telegramBotService->sendMessage(
                $restaurant->telegram_group_chat_id,
                $this->formatStopMessage($restaurant, $entry),
            );
        }

        return new StoplistEntryResource($entry);
    }

    /**
     * Mark a stoplist entry as resolved (item back in stock).
     */
    public function resolve(Request $request, StoplistEntry $stoplistEntry): StoplistEntryResource
    {
        abort_unless($request->user()->belongsToRestaurant($stoplistEntry->restaurant_id), 403);

        $stoplistEntry->update([
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        $stoplistEntry->load(['creator', 'resolver']);

        $restaurant = $stoplistEntry->restaurant;

        if ($restaurant->telegram_group_chat_id !== null) {
            $this->telegramBotService->sendMessage(
                $restaurant->telegram_group_chat_id,
                $this->formatResolvedMessage($restaurant, $stoplistEntry),
            );
        }

        return new StoplistEntryResource($stoplistEntry);
    }

    /**
     * Send the current stop-list as a single summary message to the restaurant's Telegram group.
     */
    public function send(Request $request): JsonResponse
    {
        $restaurantId = (int) $request->input('restaurant_id');

        abort_unless($restaurantId > 0 && $request->user()->belongsToRestaurant($restaurantId), 403);

        $restaurant = Restaurant::findOrFail($restaurantId);

        if ($restaurant->telegram_group_chat_id === null) {
            return response()->json(['message' => 'Для этого ресторана не настроена группа в Telegram.'], 422);
        }

        $entries = StoplistEntry::where('restaurant_id', $restaurantId)
            ->whereNull('resolved_at')
            ->orderBy('created_at')
            ->get();

        if ($entries->isEmpty()) {
            return response()->json(['message' => 'Стоп-лист пуст.'], 422);
        }

        $this->telegramBotService->sendMessage(
            $restaurant->telegram_group_chat_id,
            $this->formatStoplistMessage($restaurant, $entries),
        );

        return response()->json(['message' => 'Стоп-лист отправлен в группу.']);
    }

    /**
     * @param  Collection<int, StoplistEntry>  $entries
     */
    private function formatStoplistMessage(Restaurant $restaurant, Collection $entries): string
    {
        $statusLabels = [
            StoplistStatus::Stop->value => '❌ <b>СТОП</b>',
            StoplistStatus::Limit->value => '⚠️ <b>ЛИМИТ</b>',
            StoplistStatus::Play->value => '🔥 <b>ПРОДАЁМ</b>',
        ];

        $message = sprintf('<b>%s</b> | Стоп-лист — %s', now()->format('d.m.Y'), $restaurant->name);

        foreach (StoplistSection::cases() as $section) {
            $sectionEntries = $entries->where('section', $section);

            if ($sectionEntries->isEmpty()) {
                continue;
            }

            $sectionLabel = $section === StoplistSection::Kitchen ? 'Кухня' : 'Бар';
            $message .= "\n\n<b>{$sectionLabel}</b>";

            foreach (StoplistStatus::cases() as $status) {
                $statusEntries = $sectionEntries->where('status', $status);

                if ($statusEntries->isEmpty()) {
                    continue;
                }

                $lines = $statusEntries->map(function (StoplistEntry $entry) {
                    $line = '- '.$entry->item;

                    if ($entry->comment !== '') {
                        $line .= " — {$entry->comment}";
                    }

                    return $line;
                })->implode("\n");

                $message .= "\n{$statusLabels[$status->value]}\n{$lines}";
            }
        }

        return $message;
    }

    private function formatStopMessage(Restaurant $restaurant, StoplistEntry $entry): string
    {
        $statusLabel = match ($entry->status) {
            StoplistStatus::Stop => '❌ <b>СТОП</b>',
            StoplistStatus::Limit => '⚠️ <b>ЛИМИТ</b>',
            StoplistStatus::Play => '🔥 <b>ПРОДАЁМ</b>',
        };

        $sectionLabel = $entry->section === StoplistSection::Kitchen ? 'Кухня' : 'Бар';

        $message = sprintf(
            "%s | %s\n%s — %s",
            $statusLabel,
            $restaurant->name,
            $sectionLabel,
            $entry->item,
        );

        if ($entry->comment !== '') {
            $message .= sprintf("\n<i>%s</i>", $entry->comment);
        }

        return $message;
    }

    private function formatResolvedMessage(Restaurant $restaurant, StoplistEntry $entry): string
    {
        $sectionLabel = $entry->section === StoplistSection::Kitchen ? 'Кухня' : 'Бар';

        $title = $entry->status === StoplistStatus::Play ? '✅ Распродано' : '✅ Снято со стопа';

        return sprintf(
            "%s | %s\n%s — %s",
            $title,
            $restaurant->name,
            $sectionLabel,
            $entry->item,
        );
    }
}
