"""
Scrapes all reviews for a Yandex Maps organization card via Playwright.

CLI usage:
    python3 scrape_reviews.py "https://yandex.uz/maps/org/kfc/234867085663/" --out reviews.csv --headless

Programmatic usage (e.g. from PHP via subprocess):
    python3 scrape_reviews.py "<url>" --json --headless

In --json mode, stdout carries ONLY one JSON object:
{"name": ..., "average_rating": ..., "ratings_count": ..., "reviews_count": ...,
 "reviews": [{"external_id": ..., "author_name": ..., "rating": ..., "text": ..., "published_at": ...}]}
All progress/diagnostics go to stderr so they don't corrupt the JSON.

Exit codes:
    0 - success (0 reviews is a valid result, not an error)
    1 - other error (network, timeout, unexpected markup)
    2 - looks like an antibot block (captcha / truncated response)

The reviews tab path is appended to the URL automatically if missing.
"""

import argparse
import asyncio
import hashlib
import json
import random
import re
import sys

from playwright.async_api import async_playwright

DESKTOP_USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)

# Hides navigator.webdriver and other automation tells - without this
# Yandex serves a "limited" stub page instead of real content.
STEALTH_INIT_SCRIPT = """
Object.defineProperty(navigator, 'webdriver', {get: () => undefined});
"""

EXTRACT_REVIEWS_JS = """
() => Array.from(document.querySelectorAll('.business-review-view')).map(el => {
    const authorEl = el.querySelector('.business-review-view__author-name [itemprop="name"]');
    const dateMeta = el.querySelector('.business-review-view__date meta[itemprop="datePublished"]');
    const stars = el.querySelectorAll('.business-rating-badge-view__star._full').length;

    let text = '';
    const bodyEl = el.querySelector('[itemprop="reviewBody"]');
    if (bodyEl) {
        const clone = bodyEl.cloneNode(true);
        clone.querySelectorAll('.spoiler-view__button').forEach(b => b.remove());
        text = clone.textContent.trim();
    }

    return {
        author_name: authorEl ? authorEl.textContent.trim() : null,
        published_at: dateMeta ? dateMeta.getAttribute('content') : null,
        rating: stars || null,
        text: text || null,
    };
})
"""

EXTRACT_CARD_JS = """
() => {
    const nameEl = document.querySelector('h1.orgpage-header-view__header[itemprop="name"]');

    let averageRating = null;
    const ratingContainer = document.querySelector('.business-summary-rating-badge-view__rating-and-stars');
    if (ratingContainer) {
        const parts = Array.from(
            ratingContainer.querySelectorAll('.business-summary-rating-badge-view__rating-text')
        ).map(e => e.textContent.trim());
        const joined = parts.join('').replace(',', '.');
        averageRating = joined ? parseFloat(joined) : null;
    }

    const digitsOnly = (str) => {
        if (!str) return null;
        const cleaned = str.replace(/[^\\d]/g, '');
        return cleaned ? parseInt(cleaned, 10) : null;
    };

    const ratingsCountEl = document.querySelector('.business-rating-amount-view._summary');
    const reviewsCountEl = document.querySelector('.card-section-header__title');

    return {
        name: nameEl ? nameEl.textContent.trim() : null,
        average_rating: averageRating,
        ratings_count: digitsOnly(ratingsCountEl ? ratingsCountEl.textContent : null),
        reviews_count: digitsOnly(reviewsCountEl ? reviewsCountEl.textContent : null),
    };
}
"""

SCROLL_JS = """
() => {
    const review = document.querySelector('.business-review-view');
    if (!review) return -1;
    const container = review.closest('.scroll__container');
    if (!container) return -1;
    container.scrollTop = container.scrollHeight;
    return container.scrollHeight;
}
"""


def log(message: str) -> None:
    print(message, file=sys.stderr, flush=True)


def normalize_reviews_url(url: str) -> str:
    url = url.rstrip("/")
    if not url.endswith("/reviews"):
        url += "/reviews"
    return url + "/"


def looks_blocked(html: str) -> bool:
    # Yandex sometimes answers 200 with a captcha/stub page - much shorter
    # than the real one (which is >1MB of markup).
    return "SmartCaptcha" in html or len(html) < 2000


def make_external_id(author_name: str | None, text: str | None, published_at: str | None) -> str:
    # No stable review id in the DOM (unlike Yandex's internal api), so a
    # content hash is the only reliable dedup key. Same format as the PHP
    # fallback (ReviewsFetcher::normalizeReview) for cross-source compatibility.
    raw = f"{author_name or ''}|{text or ''}|{published_at or ''}"
    return "hash:" + hashlib.sha1(raw.encode("utf-8")).hexdigest()


async def get_expected_total(page) -> int | None:
    header = await page.query_selector(".card-section-header__title")
    if not header:
        return None
    text = await header.inner_text()
    match = re.search(r"\d+", text.replace("\xa0", ""))
    return int(match.group()) if match else None


class BlockedError(RuntimeError):
    pass


async def scrape(url: str, headless: bool, max_idle_rounds: int = 4, max_scrolls: int = 400) -> dict:
    url = normalize_reviews_url(url)

    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=headless,
            args=["--disable-blink-features=AutomationControlled"],
        )
        context = await browser.new_context(
            locale="ru-RU",
            user_agent=DESKTOP_USER_AGENT,
            viewport={"width": 1366, "height": 900},
        )
        await context.add_init_script(STEALTH_INIT_SCRIPT)
        page = await context.new_page()

        log(f"Opening {url}")
        await page.goto(url, timeout=60000, wait_until="domcontentloaded")

        html = await page.content()
        if looks_blocked(html):
            await browser.close()
            raise BlockedError("Yandex served an antibot stub instead of the reviews page")

        try:
            await page.wait_for_selector(".business-review-view", timeout=20000)
        except Exception:
            # real page, just no reviews on this card
            card = await page.evaluate(EXTRACT_CARD_JS)
            await browser.close()
            log("No reviews found on the card - treating as 0.")
            return {**card, "reviews": []}

        expected_total = await get_expected_total(page)
        if expected_total:
            log(f"Reviews reported on page: {expected_total}")

        last_count = 0
        idle_rounds = 0

        for i in range(max_scrolls):
            new_height = await page.evaluate(SCROLL_JS)
            if new_height == -1:
                break

            # small random delay - lowers the chance of an antibot block
            await page.wait_for_timeout(1500 + random.randint(0, 800))

            count = await page.evaluate("document.querySelectorAll('.business-review-view').length")
            log(f"  scroll #{i + 1}: {count} reviews loaded")

            if expected_total and count >= expected_total:
                break

            if count == last_count:
                idle_rounds += 1
                if idle_rounds >= max_idle_rounds:
                    log("List stopped growing - assuming we reached the end.")
                    break
            else:
                idle_rounds = 0
                last_count = count

        card = await page.evaluate(EXTRACT_CARD_JS)
        raw_reviews = await page.evaluate(EXTRACT_REVIEWS_JS)
        await browser.close()

    reviews = []
    seen_ids = set()
    for r in raw_reviews:
        external_id = make_external_id(r["author_name"], r["text"], r["published_at"])
        if external_id in seen_ids:
            continue
        seen_ids.add(external_id)
        reviews.append({"external_id": external_id, **r})

    return {**card, "reviews": reviews}


def main():
    parser = argparse.ArgumentParser(description="Scrape reviews for a Yandex Maps organization")
    parser.add_argument("url", help="Organization page URL, with or without /reviews/")
    parser.add_argument("--out", default="yandex_reviews.csv", help="Output CSV path (ignored with --json)")
    parser.add_argument("--headless", action="store_true", help="Run the browser without a visible window")
    parser.add_argument("--json", action="store_true", help="Print result as JSON to stdout instead of CSV")
    args = parser.parse_args()

    try:
        result = asyncio.run(scrape(args.url, headless=args.headless))
    except BlockedError as e:
        # exit code 2 alone isn't a safe signal - python itself uses it for
        # unrelated things (e.g. "can't open file"), so callers should match
        # on this marker in stderr, not just the exit code
        log(f"YANDEX_BLOCKED: {e}")
        sys.exit(2)

    if args.json:
        print(json.dumps(result, ensure_ascii=False))
        sys.exit(0)

    if not result["reviews"]:
        log("No reviews found - file not written.")
        sys.exit(1)

    import pandas as pd

    df = pd.DataFrame(result["reviews"])
    df.to_csv(args.out, index=False, encoding="utf-8-sig")
    log(f"Saved {len(df)} reviews to {args.out}")


if __name__ == "__main__":
    main()
