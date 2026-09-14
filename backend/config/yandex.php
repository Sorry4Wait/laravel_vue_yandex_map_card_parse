<?php

return [
    // which fetcher ParseOrganizationReviewsJob actually uses - "playwright"
    // (default) drives a real browser via scripts/yandex_reviews and gets
    // every review; "http" is the page-1-only fallback that needs no
    // browser/python runtime at all. See ReviewsFetcherContract.
    'driver' => env('YANDEX_PARSER_DRIVER', 'playwright'),

    'python_bin' => env('YANDEX_PYTHON_BIN', 'python3'),
    'scraper_script' => base_path('scripts/yandex_reviews/scrape_reviews.py'),
    'scraper_timeout_seconds' => (int) env('YANDEX_SCRAPER_TIMEOUT_SECONDS', 180),

    // yandex serves ~50 reviews per internal api call, this mirrors that and
    // is also what we show per page on the frontend
    'reviews_per_page' => 50,

    // yandex only keeps roughly the last 600 reviews reachable through the
    // card anyway, so there's no point paging past this
    'max_reviews' => 600,

    // random delay between requests to the same org, in ms - keeps us from
    // hammering yandex and looking like a bot
    'min_delay_ms' => (int) env('YANDEX_PARSER_MIN_DELAY_MS', 800),
    'max_delay_ms' => (int) env('YANDEX_PARSER_MAX_DELAY_MS', 2200),

    'max_retries' => 3,
    'request_timeout_seconds' => 15,

    // comma separated list of proxy urls, e.g. http://user:pass@host:port
    // empty = go direct, fine for local dev / low volume
    'proxies' => array_filter(explode(',', (string) env('YANDEX_PARSER_PROXIES', ''))),

    'user_agents' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0',
    ],
];
