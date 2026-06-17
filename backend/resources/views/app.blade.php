<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
        <title>{{ config('app.name', 'Restaurant App') }}</title>

        @isset($mockInitData)
            {{-- Local dev login: stand in for the real Telegram WebApp SDK so the
                 app can be tested in a regular browser. --}}
            <script>
                window.Telegram = {
                    WebApp: {
                        initData: @json($mockInitData),
                        themeParams: {},
                        colorScheme: 'light',
                        ready() {},
                        expand() {},
                        onEvent() {},
                        showConfirm(message, callback) { callback(window.confirm(message)); },
                        BackButton: { show() {}, hide() {}, onClick() {} },
                    },
                };
            </script>
        @else
            <script src="https://telegram.org/js/telegram-web-app.js"></script>
        @endisset

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
