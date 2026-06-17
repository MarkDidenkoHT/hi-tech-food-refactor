<?php

namespace App\Providers;

use App\Services\Calendar\WooCommerceReservationService;
use App\Services\Feedback\WooCommerceFeedbackService;
use App\Services\Menu\WooCommerceMenuService;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TelegramAuthService::class, fn () => new TelegramAuthService(
            (string) config('services.telegram.bot_token'),
        ));

        $this->app->singleton(TelegramBotService::class, fn () => new TelegramBotService(
            (string) config('services.telegram.bot_token'),
        ));

        $this->app->singleton(WooCommerceMenuService::class, fn () => new WooCommerceMenuService(
            (array) config('services.woocommerce'),
        ));

        $this->app->singleton(WooCommerceFeedbackService::class, fn () => new WooCommerceFeedbackService(
            (array) config('services.woocommerce'),
        ));

        $this->app->singleton(WooCommerceReservationService::class, fn () => new WooCommerceReservationService(
            (array) config('services.woocommerce'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
