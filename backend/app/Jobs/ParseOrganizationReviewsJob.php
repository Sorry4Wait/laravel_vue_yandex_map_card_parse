<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\ParseRun;
use App\Models\Review;
use App\Models\ReviewRevision;
use App\Services\YandexMaps\Contracts\ReviewsFetcherContract;
use App\Services\YandexMaps\Exceptions\YandexBlockedException;
use App\Services\YandexMaps\Exceptions\YandexSourceChangedException;
use App\Services\YandexMaps\Exceptions\YandexUnavailableException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ParseOrganizationReviewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 90];

    public int $timeout;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $parseRunId,
    ) {
        // The playwright scrape itself is allowed up to
        // yandex.scraper_timeout_seconds (see PlaywrightReviewsFetcher).
        // Without an explicit job timeout here, the queue worker's own
        // default (60s) kills the job — via pcntl, bypassing handle()'s
        // try/catch entirely — well before a real scrape of a few hundred
        // reviews can finish, and mislabels it as a Yandex "unavailable"
        // failure. The +60s covers resolveBusinessId(), DB writes, and
        // process spawn overhead around the scrape itself.
        $this->timeout = (int) config('yandex.scraper_timeout_seconds') + 60;
    }

    public function handle(ReviewsFetcherContract $fetcher): void
    {
        $organization = Organization::findOrFail($this->organizationId);
        $run = ParseRun::findOrFail($this->parseRunId);

        $run->update(['status' => ParseRun::STATUS_RUNNING, 'started_at' => now()]);

        try {
            $businessId = $fetcher->resolveBusinessId($organization->yandex_url);

            if ($organization->yandex_business_id !== $businessId) {
                $organization->yandex_business_id = $businessId;
                $organization->save();
            }

            $created = 0;
            $updated = 0;
            $card = [];

            $fetcher->fetchAllReviews(
                $organization->yandex_url,
                function (array $reviews) use ($organization, $run, &$created, &$updated) {
                    [$pageCreated, $pageUpdated] = $this->storeReviewPage($organization, $run, $reviews);
                    $created += $pageCreated;
                    $updated += $pageUpdated;
                    $run->increment('reviews_fetched', count($reviews));
                },
                function (array $cardData) use (&$card) {
                    $card = $cardData;
                },
            );

            $organization->update([
                'name' => $card['name'] ?? $organization->name,
                'average_rating' => $card['average_rating'] ?? null,
                'ratings_count' => $card['ratings_count'] ?? null,
                'reviews_count' => $card['reviews_count'] ?? null,
                'last_parsed_at' => now(),
            ]);

            $run->update([
                'status' => ParseRun::STATUS_SUCCEEDED,
                'reviews_created' => $created,
                'reviews_updated' => $updated,
                'average_rating' => $card['average_rating'] ?? null,
                'ratings_count' => $card['ratings_count'] ?? null,
                'reviews_count' => $card['reviews_count'] ?? null,
                'finished_at' => now(),
            ]);
        } catch (YandexSourceChangedException $e) {
            Log::critical('yandex parser: source shape changed, needs a look', [
                'organization_id' => $organization->id,
                'message' => $e->getMessage(),
            ]);
            $this->markFailed($run, 'source_changed', $e);
            $this->fail($e); // don't retry, retrying won't fix a broken parser
        } catch (YandexBlockedException $e) {
            $this->handleRetryableFailure($run, 'blocked', $e);
            throw $e; // let the queue retry with backoff, might clear up
        } catch (YandexUnavailableException $e) {
            $this->handleRetryableFailure($run, 'unavailable', $e);
            throw $e;
        }
    }

    /**
     * Record the failure reason without ending the run while attempts remain —
     * only the final attempt flips the run to a terminal 'failed' status.
     * Otherwise the frontend, which stops polling on 'failed', never sees the
     * automatic retry this exception is about to trigger.
     */
    private function handleRetryableFailure(ParseRun $run, string $reason, Throwable $e): void
    {
        if ($this->attempts() >= $this->tries) {
            $this->markFailed($run, $reason, $e);

            return;
        }

        $run->update([
            'failure_reason' => $reason,
            'error_message' => $e->getMessage(),
        ]);
    }

    /**
     * @param  array<int, array{external_id: string, author_name: ?string, rating: ?int, text: ?string, published_at: ?string}>  $reviews
     * @return array{0: int, 1: int} [created, updated]
     */
    private function storeReviewPage(Organization $organization, ParseRun $run, array $reviews): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($organization, $run, $reviews, &$created, &$updated) {
            foreach ($reviews as $data) {
                $existing = Review::where('organization_id', $organization->id)
                    ->where('external_id', $data['external_id'])
                    ->first();

                $publishedAt = $data['published_at'] ? Carbon::parse($data['published_at']) : null;

                if (! $existing) {
                    Review::create([
                        'organization_id' => $organization->id,
                        'external_id' => $data['external_id'],
                        'author_name' => $data['author_name'],
                        'rating' => $data['rating'],
                        'text' => $data['text'],
                        'published_at' => $publishedAt,
                    ]);
                    $created++;

                    continue;
                }

                $changed = $existing->rating !== $data['rating'] || $existing->text !== $data['text'];

                if ($changed) {
                    ReviewRevision::create([
                        'review_id' => $existing->id,
                        'parse_run_id' => $run->id,
                        'previous_rating' => $existing->rating,
                        'previous_text' => $existing->text,
                        'changed_at' => now(),
                    ]);

                    $existing->update([
                        'author_name' => $data['author_name'],
                        'rating' => $data['rating'],
                        'text' => $data['text'],
                        'published_at' => $publishedAt,
                    ]);
                    $updated++;
                }
            }
        });

        return [$created, $updated];
    }

    private function markFailed(ParseRun $run, string $reason, Throwable $e): void
    {
        $run->update([
            'status' => ParseRun::STATUS_FAILED,
            'failure_reason' => $reason,
            'error_message' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }

    public function failed(Throwable $e): void
    {
        $run = ParseRun::find($this->parseRunId);
        if ($run && ! $run->isFinished()) {
            $this->markFailed($run, $run->failure_reason ?? 'unavailable', $e);
        }
    }
}
