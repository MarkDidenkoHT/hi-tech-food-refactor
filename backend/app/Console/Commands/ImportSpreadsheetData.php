<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\ChecklistQuestion;
use App\Models\PhotoQuestion;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ImportSpreadsheetData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-spreadsheet {path? : Path to the .xlsx file (defaults to storage/app/imports/restaurant-app-data.xlsx)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import restaurants, users, checklist questions, and photo questions from the provided spreadsheet';

    /**
     * Maps a normalized (lowercased) restaurant name to its Restaurant model.
     *
     * @var array<string, Restaurant>
     */
    private array $restaurants = [];

    public function handle(): int
    {
        $path = $this->argument('path') ?? storage_path('app/imports/restaurant-app-data.xlsx');

        if (! is_string($path) || ! file_exists($path)) {
            $this->error("Spreadsheet not found at: {$path}");

            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($path);

        $this->importRestaurants($spreadsheet);
        $this->importUsers($spreadsheet);
        $this->importChecklistQuestions($spreadsheet);
        $this->importPhotoQuestions($spreadsheet);

        return self::SUCCESS;
    }

    /**
     * Build the canonical restaurant list from all three sheets and upsert them.
     */
    private function importRestaurants(Spreadsheet $spreadsheet): void
    {
        $names = [];

        foreach ($this->rows($spreadsheet, 'users') as $row) {
            foreach (explode(',', (string) $row['C']) as $name) {
                $this->collectRestaurantName($names, $name);
            }
        }

        foreach ($this->rows($spreadsheet, 'checklist') as $row) {
            $this->collectRestaurantName($names, (string) $row['A']);
        }

        foreach ($this->rows($spreadsheet, 'photo-questions') as $row) {
            $this->collectRestaurantName($names, (string) $row['A']);
        }

        $created = 0;
        $updated = 0;

        foreach ($names as $key => $displayName) {
            $slug = Str::slug($displayName);

            $restaurant = Restaurant::query()->where('slug', $slug)->first();

            if ($restaurant === null) {
                $restaurant = Restaurant::create([
                    'name' => $displayName,
                    'slug' => $slug,
                    'is_active' => true,
                ]);
                $created++;
            } else {
                $updated++;
            }

            $this->restaurants[$key] = $restaurant;
        }

        $this->info("Restaurants: {$created} created, {$updated} already existed.");
    }

    /**
     * @param  array<string, string>  $names
     */
    private function collectRestaurantName(array &$names, string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            return;
        }

        $key = mb_strtolower($name);
        $names[$key] ??= mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Import users and their restaurant memberships, logging duplicate telegram_ids as conflicts.
     */
    private function importUsers(Spreadsheet $spreadsheet): void
    {
        $created = 0;
        $updated = 0;
        $conflicts = [];
        $seenTelegramIds = [];

        foreach ($this->rows($spreadsheet, 'users') as $row) {
            $telegramId = (int) trim((string) $row['A']);
            $name = trim((string) $row['B']);
            $role = trim((string) $row['D']);

            if ($telegramId <= 0 || $name === '') {
                continue;
            }

            if (isset($seenTelegramIds[$telegramId])) {
                $conflicts[] = "telegram_id {$telegramId}: skipped duplicate row for \"{$name}\" (kept \"{$seenTelegramIds[$telegramId]}\")";

                continue;
            }

            $seenTelegramIds[$telegramId] = $name;

            [$firstName, $lastName] = $this->splitName($name);

            $user = User::query()->where('telegram_id', $telegramId)->first();
            $isNew = $user === null;

            $user = $user ?? new User(['telegram_id' => $telegramId]);
            $user->fill([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => Role::from($role),
                'is_active' => true,
            ]);
            $user->save();

            $isNew ? $created++ : $updated++;

            $restaurantIds = [];
            foreach (explode(',', (string) $row['C']) as $restaurantName) {
                $restaurantName = trim($restaurantName);

                if ($restaurantName === '') {
                    continue;
                }

                $restaurant = $this->restaurants[mb_strtolower($restaurantName)] ?? null;

                if ($restaurant !== null) {
                    $restaurantIds[] = $restaurant->id;
                }
            }

            $user->restaurants()->sync($restaurantIds);
        }

        $this->info("Users: {$created} created, {$updated} updated.");

        foreach ($conflicts as $conflict) {
            $this->warn("Conflict: {$conflict}");
        }
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', $name, 2);

        return [$parts[0], $parts[1] ?? null];
    }

    /**
     * Import the daily checklist questions per restaurant/area.
     */
    private function importChecklistQuestions(Spreadsheet $spreadsheet): void
    {
        $created = 0;
        $updated = 0;
        $sortOrders = [];

        foreach ($this->rows($spreadsheet, 'checklist') as $row) {
            $restaurantName = trim((string) $row['A']);
            $area = trim((string) $row['B']);
            $question = trim((string) $row['C']);

            if ($restaurantName === '' || $question === '') {
                continue;
            }

            $restaurant = $this->restaurants[mb_strtolower($restaurantName)] ?? null;

            if ($restaurant === null) {
                continue;
            }

            $sortKey = "{$restaurant->id}:{$area}";
            $sortOrders[$sortKey] = ($sortOrders[$sortKey] ?? -1) + 1;

            $question = ChecklistQuestion::query()->updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'area' => $area,
                    'question' => $question,
                ],
                [
                    'sort_order' => $sortOrders[$sortKey],
                    'is_active' => true,
                ]
            );

            $question->wasRecentlyCreated ? $created++ : $updated++;
        }

        $this->info("Checklist questions: {$created} created, {$updated} updated.");
    }

    /**
     * Import the hourly photo-task questions per restaurant.
     */
    private function importPhotoQuestions(Spreadsheet $spreadsheet): void
    {
        $created = 0;
        $updated = 0;
        $sortOrders = [];

        foreach ($this->rows($spreadsheet, 'photo-questions') as $row) {
            $restaurantName = trim((string) $row['A']);
            $question = trim((string) $row['B']);

            if ($restaurantName === '' || $question === '') {
                continue;
            }

            $restaurant = $this->restaurants[mb_strtolower($restaurantName)] ?? null;

            if ($restaurant === null) {
                continue;
            }

            $sortOrders[$restaurant->id] = ($sortOrders[$restaurant->id] ?? -1) + 1;

            $photoQuestion = PhotoQuestion::query()->updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'question' => $question,
                ],
                [
                    'sort_order' => $sortOrders[$restaurant->id],
                    'is_active' => true,
                ]
            );

            $photoQuestion->wasRecentlyCreated ? $created++ : $updated++;
        }

        $this->info("Photo questions: {$created} created, {$updated} updated.");
    }

    /**
     * Yield each data row (excluding the header) of the given sheet as a column-letter-keyed array.
     *
     * @return iterable<int, array<string, mixed>>
     */
    private function rows(Spreadsheet $spreadsheet, string $sheetName): iterable
    {
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if ($sheet === null) {
            $this->warn("Sheet not found: {$sheetName}");

            return;
        }

        $rows = $sheet->toArray(null, true, true, true);
        array_shift($rows);

        yield from $rows;
    }
}
