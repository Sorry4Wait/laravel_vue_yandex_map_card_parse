<?php

namespace App\Services\YandexMaps;

use App\Services\YandexMaps\Exceptions\YandexSourceChangedException;

class OrgUrlResolver
{
    public function __construct(
        private readonly AntiBanHttpClient $http,
    ) {}

    /**
     * Short links (/maps/-/xxxx) carry no business id - only the redirect
     * target does. Shared by every fetcher; which one pulls the reviews
     * afterwards doesn't change how the id is resolved.
     */
    public function resolveBusinessId(string $orgUrl): string
    {
        $direct = $this->extractBusinessId($orgUrl);
        if ($direct !== null) {
            return $direct;
        }

        if (! $this->isShortLink($orgUrl)) {
            throw new YandexSourceChangedException("couldn't find a business id in url: {$orgUrl}");
        }

        $finalUrl = $this->http->resolveRedirect($orgUrl);
        $id = $this->extractBusinessId($finalUrl);

        if ($id === null) {
            throw new YandexSourceChangedException("short link {$orgUrl} redirected to {$finalUrl}, still no business id in it");
        }

        return $id;
    }

    /**
     * True if the url at least points at yandex maps. Doesn't guarantee it's
     * a real org card, just filters out obviously wrong input before we
     * bother hitting the network.
     */
    public function looksLikeOrgUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! $parts || empty($parts['host'])) {
            return false;
        }

        // yandex maps redirects to a country-specific domain depending on
        // where the request comes from (yandex.ru, yandex.uz, yandex.kz,
        // yandex.com.tr, ...) - match any of them rather than hardcoding one
        $host = strtolower($parts['host']);
        if (! preg_match('/(^|\.)yandex\.[a-z.]+$/', $host)) {
            return false;
        }

        $path = $parts['path'] ?? '';

        // regular org card: /maps/org/some-name/1234567890/
        // short link: /maps/-/CDxxxxxx (resolves via redirect, no id inline)
        return (bool) preg_match('#/maps/(org/|-/)#', $path);
    }

    /**
     * Pulls the numeric business id out of a full org url, e.g.
     * https://yandex.ru/maps/org/kofemaniya/1124490249/ -> "1124490249"
     * Returns null for short links - see resolveBusinessId().
     */
    public function extractBusinessId(string $url): ?string
    {
        if (preg_match('#/maps/org/[^/]+/(\d+)#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public function isShortLink(string $url): bool
    {
        return (bool) preg_match('#/maps/-/#', parse_url($url, PHP_URL_PATH) ?? '');
    }
}
