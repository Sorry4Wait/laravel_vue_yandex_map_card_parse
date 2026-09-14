<?php

namespace App\Services\YandexMaps;

use App\Services\YandexMaps\Exceptions\YandexBlockedException;
use App\Services\YandexMaps\Exceptions\YandexUnavailableException;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Http client wrapper: random UA per request, optional proxy rotation, a
 * small delay before every call, and a clear exception instead of silently
 * returning garbage when Yandex blocks us. No guarantee against bans - see
 * README - just makes the scrape look less like one script hammering an
 * endpoint from the same fingerprint every time.
 */
class AntiBanHttpClient
{
    private int $requestCount = 0;

    /**
     * One jar per instance so cookies set on one request (gdpr flags,
     * yandexuid, ...) carry over to later calls made through this same
     * instance during a parse run.
     */
    private CookieJar $cookies;

    public function __construct()
    {
        $this->cookies = new CookieJar;
    }

    public function get(string $url, array $query = []): array
    {
        $response = $this->send($url, $query);

        return $response->json() ?? [];
    }

    public function getHtml(string $url): string
    {
        return $this->send($url)->body();
    }

    /**
     * Follows a redirect and returns where it actually lands, without
     * caring about the response body - used to turn /maps/-/xxx into a real
     * /maps/org/.../<id>/ url.
     */
    public function resolveRedirect(string $url): string
    {
        $response = $this->send($url);

        return (string) ($response->effectiveUri() ?? $url);
    }

    private function send(string $url, array $query = []): Response
    {
        $this->throttle();

        $attempt = 0;
        $maxRetries = config('yandex.max_retries');

        while (true) {
            $attempt++;

            try {
                $response = $this->buildRequest()->get($url, $query);
            } catch (ConnectionException $e) {
                if ($attempt > $maxRetries) {
                    throw new YandexUnavailableException("connection failed after {$attempt} attempts: {$e->getMessage()}", 0, $e);
                }
                $this->backoff($attempt);

                continue;
            }

            $this->requestCount++;

            if ($this->looksBlocked($response)) {
                if ($attempt > $maxRetries) {
                    throw new YandexBlockedException("yandex is blocking us (status {$response->status()}) after {$attempt} attempts");
                }
                Log::warning('yandex parser: possible block, backing off', ['status' => $response->status(), 'attempt' => $attempt]);
                $this->backoff($attempt);

                continue;
            }

            if ($response->serverError()) {
                if ($attempt > $maxRetries) {
                    throw new YandexUnavailableException("yandex returned {$response->status()} after {$attempt} attempts");
                }
                $this->backoff($attempt);

                continue;
            }

            if (! $response->successful()) {
                throw new YandexUnavailableException("unexpected status {$response->status()} from yandex");
            }

            return $response;
        }
    }

    private function buildRequest(): PendingRequest
    {
        $request = Http::withHeaders([
            'User-Agent' => $this->randomUserAgent(),
            'Accept' => 'application/json, text/html, */*',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Dest' => 'empty',
        ])->withOptions(['cookies' => $this->cookies])
            ->timeout(config('yandex.request_timeout_seconds'));

        if ($proxy = $this->pickProxy()) {
            $request->withOptions(['proxy' => $proxy]);
        }

        return $request;
    }

    private function looksBlocked(Response $response): bool
    {
        if (in_array($response->status(), [403, 429], true)) {
            return true;
        }

        // yandex sometimes answers 200 with a captcha/"smart captcha" html
        // page instead of the real content when it's suspicious of us
        $body = $response->body();
        if ($response->status() === 200 && str_contains($body, 'SmartCaptcha')) {
            return true;
        }

        return false;
    }

    private function throttle(): void
    {
        $min = config('yandex.min_delay_ms');
        $max = config('yandex.max_delay_ms');
        usleep(random_int($min, $max) * 1000);
    }

    private function backoff(int $attempt): void
    {
        // exponential-ish backoff with jitter, capped so we're not stuck for
        // minutes on a job that's going to fail anyway
        $baseMs = min(1000 * (2 ** $attempt), 15000);
        $jitterMs = random_int(0, 500);
        usleep(($baseMs + $jitterMs) * 1000);
    }

    private function randomUserAgent(): string
    {
        $agents = config('yandex.user_agents');

        return $agents[array_rand($agents)];
    }

    private function pickProxy(): ?string
    {
        $proxies = config('yandex.proxies');
        if (empty($proxies)) {
            return null;
        }

        return $proxies[array_rand($proxies)];
    }

    public function requestCount(): int
    {
        return $this->requestCount;
    }
}
