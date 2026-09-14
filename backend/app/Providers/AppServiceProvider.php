<?php

namespace App\Providers;

use App\Services\YandexMaps\Contracts\ReviewsFetcherContract;
use App\Services\YandexMaps\PlaywrightReviewsFetcher;
use App\Services\YandexMaps\ReviewsFetcher;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // YANDEX_PARSER_DRIVER=http falls back to the page-1-only http
        // fetcher (no browser needed) - playwright is the default since it's
        // the only one that gets every review, not just the first ~50.
        $this->app->bind(ReviewsFetcherContract::class, function ($app) {
            return match (config('yandex.driver')) {
                'http' => $app->make(ReviewsFetcher::class),
                default => $app->make(PlaywrightReviewsFetcher::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // frontend types assume a flat resource body, not the default {"data": ...} envelope
        JsonResource::withoutWrapping();
    }
}
