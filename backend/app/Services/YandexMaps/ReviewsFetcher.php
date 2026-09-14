<?php

namespace App\Services\YandexMaps;

use App\Services\YandexMaps\Contracts\ReviewsFetcherContract;
use App\Services\YandexMaps\Exceptions\YandexSourceChangedException;

/**
 * Reads the org card + first ~50 reviews from Yandex's own server-rendered
 * inline JSON state - one plain GET, no browser, no reverse-engineered API.
 *
 * Can't get pages 2+: Yandex's "show more" endpoint needs a client-computed
 * `s` signature we couldn't crack (see PlaywrightReviewsFetcher, which gets
 * full pagination via a real browser instead). Kept as the cheap fallback
 * (`YANDEX_PARSER_DRIVER=http`) for environments without python/playwright.
 */
class ReviewsFetcher implements ReviewsFetcherContract
{
    public function __construct(
        private readonly AntiBanHttpClient $http,
        private readonly OrgUrlResolver $urlResolver,
    ) {}

    public function resolveBusinessId(string $orgUrl): string
    {
        return $this->urlResolver->resolveBusinessId($orgUrl);
    }

    /**
     * Fetches the org card + first page of reviews. $onCard gets the
     * aggregates, $onPage gets the reviews (callbacks so this stays
     * source-agnostic to callers, same as PlaywrightReviewsFetcher).
     *
     * @param  callable(array<int, array>): void  $onPage
     * @param  callable(array): void  $onCard
     * @return int total reviews fetched
     */
    public function fetchAllReviews(string $orgUrl, callable $onPage, callable $onCard): int
    {
        $reviewsUrl = $this->reviewsTabUrl($orgUrl);

        $html = $this->http->getHtml($reviewsUrl);
        $state = $this->extractEmbeddedState($html);
        $node = $this->findOrgCardNode($state);

        $reviews = array_map([$this, 'normalizeReview'], $node['reviewResults']['reviews'] ?? []);

        $onCard($this->cardFromNode($node));
        $onPage($reviews);

        return count($reviews);
    }

    private function reviewsTabUrl(string $orgUrl): string
    {
        $withoutQuery = strtok($orgUrl, '?') ?: $orgUrl;

        return rtrim($withoutQuery, '/').'/reviews/';
    }

    private function extractEmbeddedState(string $html): array
    {
        // yandex inlines the whole page state as one big <script> with no
        // src - it's consistently the largest inline script on the page
        if (! preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#is', $html, $matches) || empty($matches[1])) {
            throw new YandexSourceChangedException('no inline <script> blocks found on the org page - markup changed more than expected');
        }

        $blob = '';
        foreach ($matches[1] as $candidate) {
            if (strlen($candidate) > strlen($blob)) {
                $blob = $candidate;
            }
        }

        $state = json_decode($blob, true);

        if (! is_array($state) || json_last_error() !== JSON_ERROR_NONE) {
            throw new YandexSourceChangedException('largest inline script on the org page is not valid json - the ssr state format probably changed');
        }

        return $state;
    }

    /**
     * The org card nests at different depths depending on how the page was
     * reached, so we walk the tree for the ratingData+reviewResults pair
     * instead of hardcoding a path.
     */
    private function findOrgCardNode(array $state): array
    {
        $found = $this->searchForCardNode($state);

        if ($found === null) {
            throw new YandexSourceChangedException("couldn't find a node with rating/review data anywhere in the page state - ssr shape probably changed");
        }

        return $found;
    }

    private function searchForCardNode(mixed $node): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        if (isset($node['ratingData'], $node['reviewResults'])) {
            return $node;
        }

        foreach ($node as $value) {
            if (is_array($value) && ($found = $this->searchForCardNode($value)) !== null) {
                return $found;
            }
        }

        return null;
    }

    private function cardFromNode(array $node): array
    {
        $rating = $node['ratingData'] ?? [];

        return [
            'name' => $node['title'] ?? null,
            'average_rating' => isset($rating['ratingValue']) ? (float) $rating['ratingValue'] : null,
            'ratings_count' => isset($rating['ratingCount']) ? (int) $rating['ratingCount'] : null,
            'reviews_count' => isset($rating['reviewCount']) ? (int) $rating['reviewCount'] : null,
        ];
    }

    /**
     * @return array{external_id: string, author_name: ?string, rating: ?int, text: ?string, published_at: ?string}
     */
    private function normalizeReview(array $raw): array
    {
        $authorName = $raw['author']['name'] ?? null;
        $rating = $raw['rating'] ?? null;
        $text = $raw['text'] ?? null;
        $publishedAt = $raw['updatedTime'] ?? null;

        $externalId = $raw['reviewId'] ?? null;
        if ($externalId === null) {
            // no stable id from yandex, hash the content so reparsing dedups
            $externalId = 'hash:'.sha1(($authorName ?? '').'|'.($text ?? '').'|'.($publishedAt ?? ''));
        }

        if ($authorName === null && $text === null && $rating === null) {
            // not "review has no text" - every field is missing, so this
            // isn't a review object at all
            throw new YandexSourceChangedException(
                'a review entry had none of the expected fields, response shape probably changed: '
                .substr(json_encode($raw), 0, 500)
            );
        }

        return [
            'external_id' => (string) $externalId,
            'author_name' => $authorName,
            'rating' => $rating !== null ? (int) round($rating) : null,
            'text' => $text,
            'published_at' => $publishedAt,
        ];
    }
}
