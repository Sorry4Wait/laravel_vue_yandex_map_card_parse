<?php

namespace App\Services\YandexMaps;

use App\Services\YandexMaps\Contracts\ReviewsFetcherContract;
use App\Services\YandexMaps\Exceptions\YandexBlockedException;
use App\Services\YandexMaps\Exceptions\YandexSourceChangedException;
use App\Services\YandexMaps\Exceptions\YandexUnavailableException;
use Illuminate\Support\Facades\Process;

/**
 * Gets ALL reviews (not just the first ~50) by shelling out to
 * scripts/yandex_reviews/scrape_reviews.py, which drives headless Chromium
 * via Playwright and scrolls the reviews panel to the end.
 *
 * Yandex's virtualized list ignores synthetic/WebDriver scroll events as
 * "untrusted" (an earlier Selenium attempt failed on this - see README).
 * The python script instead sets `container.scrollTop = scrollHeight`
 * directly, a real browser action that fires a trusted `scroll` event -
 * exactly what the lazy-load listener needs. Verified on a live card
 * (KFC, Tashkent): 402/402 reviews.
 *
 * Script exit codes (see scrape_reviews.py): 0 success, 1 other error,
 * 2 looks like an antibot block.
 */
class PlaywrightReviewsFetcher implements ReviewsFetcherContract
{
    public function __construct(
        private readonly OrgUrlResolver $urlResolver,
    ) {}

    public function resolveBusinessId(string $orgUrl): string
    {
        return $this->urlResolver->resolveBusinessId($orgUrl);
    }

    public function fetchAllReviews(string $orgUrl, callable $onPage, callable $onCard): int
    {
        $result = Process::timeout(config('yandex.scraper_timeout_seconds'))
            ->run($this->command($orgUrl));

        // exit code 2 alone isn't a safe signal - python itself uses it for
        // unrelated things (e.g. "can't open file"), so we also require the
        // script's own marker before calling it a block instead of a bug
        if ($result->exitCode() === 2 && str_contains($result->errorOutput(), 'YANDEX_BLOCKED')) {
            throw new YandexBlockedException(
                "playwright scraper reports a likely block: {$result->errorOutput()}"
            );
        }

        if (! $result->successful()) {
            throw new YandexUnavailableException(
                "playwright scraper failed (exit {$result->exitCode()}): {$result->errorOutput()}"
            );
        }

        $data = json_decode($result->output(), true);

        if (! is_array($data) || ! array_key_exists('reviews', $data) || ! is_array($data['reviews'])) {
            throw new YandexSourceChangedException(
                "playwright scraper returned unparseable output: ".substr($result->output(), 0, 500)
            );
        }

        $onCard([
            'name' => $data['name'] ?? null,
            'average_rating' => $data['average_rating'] ?? null,
            'ratings_count' => $data['ratings_count'] ?? null,
            'reviews_count' => $data['reviews_count'] ?? null,
        ]);

        $reviews = array_map([$this, 'normalizeReview'], $data['reviews']);
        $reviews = array_slice($reviews, 0, config('yandex.max_reviews'));

        // report progress in the same page-sized chunks the http fetcher
        // would have, so parse_runs.reviews_fetched climbs the same way
        // regardless of which driver actually ran
        foreach (array_chunk($reviews, config('yandex.reviews_per_page')) as $page) {
            $onPage($page);
        }

        return count($reviews);
    }

    private function command(string $orgUrl): array
    {
        return [
            config('yandex.python_bin'),
            config('yandex.scraper_script'),
            $orgUrl,
            '--headless',
            '--json',
        ];
    }

    /**
     * @return array{external_id: string, author_name: ?string, rating: ?int, text: ?string, published_at: ?string}
     */
    private function normalizeReview(array $raw): array
    {
        $authorName = $raw['author_name'] ?? null;
        $rating = $raw['rating'] ?? null;
        $text = $raw['text'] ?? null;
        $externalId = $raw['external_id'] ?? null;

        if ($externalId === null || ($authorName === null && $text === null && $rating === null)) {
            throw new YandexSourceChangedException(
                'a review entry from the playwright scraper had none of the expected fields: '
                .substr(json_encode($raw), 0, 500)
            );
        }

        return [
            'external_id' => (string) $externalId,
            'author_name' => $authorName,
            'rating' => $rating !== null ? (int) round($rating) : null,
            'text' => $text,
            'published_at' => $raw['published_at'] ?? null,
        ];
    }
}
