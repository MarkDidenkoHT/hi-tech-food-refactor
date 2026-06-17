<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
        <title>Dev login &mdash; {{ config('app.name', 'Restaurant App') }}</title>

        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-bg p-4 text-text">
        <h1 class="mb-1 text-lg font-semibold">Dev login</h1>
        <p class="mb-4 text-sm text-hint">
            Local-only: pick a user to open the Mini App with a freshly signed
            <code>initData</code> for them.
        </p>

        <ul class="flex flex-col gap-2">
            @forelse ($users as $user)
                <li>
                    <a href="{{ url('/dev-login/'.$user->telegram_id) }}"
                        class="block rounded-2xl bg-surface p-4 shadow-sm">
                        <span class="font-medium">{{ trim($user->first_name.' '.($user->last_name ?? '')) }}</span>
                        <span class="text-sm text-hint"> &middot; {{ $user->role->value }} &middot; {{ $user->telegram_id }}</span>
                    </a>
                </li>
            @empty
                <li class="text-sm text-hint">No users found. Run the spreadsheet import or create one via the admin API.</li>
            @endforelse
        </ul>
    </body>
</html>
