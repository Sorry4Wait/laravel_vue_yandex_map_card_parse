FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    default-mysql-client \
    python3 \
    python3-pip \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Playwright for scripts/yandex_reviews/scrape_reviews.py (see
# PlaywrightReviewsFetcher). --with-deps installs Chromium's OS libs too.
RUN pip3 install --no-cache-dir --break-system-packages playwright pandas \
    && python3 -m playwright install --with-deps chromium

WORKDIR /var/www/html

COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-scripts --no-autoloader --no-interaction

COPY backend/ .
RUN composer dump-autoload --optimize

EXPOSE 9000
CMD ["php-fpm"]
