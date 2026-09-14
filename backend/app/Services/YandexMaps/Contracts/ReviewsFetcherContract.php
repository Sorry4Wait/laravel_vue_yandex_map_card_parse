<?php

namespace App\Services\YandexMaps\Contracts;

interface ReviewsFetcherContract
{
    public function resolveBusinessId(string $orgUrl): string;

    /**
     * @param  callable(array<int, array>): void  $onPage
     * @param  callable(array): void  $onCard
     * @return int total reviews fetched
     */
    public function fetchAllReviews(string $orgUrl, callable $onPage, callable $onCard): int;
}
