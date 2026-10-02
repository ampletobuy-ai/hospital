FROM php:8.2-fpm-bookworm AS base

LABEL maintainer="AmpleToBuy Hospital"
LABEL description="Hospital HMS — PHP 8.2 FPM (CodeIgniter)"

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        mysqli \
        pdo_mysql \
        mbstring \
        zip \
        intl \
        gd \
        exif \
        opcache \
        bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/custom.ini /usr/local/etc/php/conf.d/99-hospital.ini

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && sed -i 's/clear_env = yes/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf

# Production — bake application into image
FROM base AS production

COPY . .
RUN mkdir -p \
        application/cache \
        application/logs \
        application/sessions \
        uploads \
        temp \
    && chown -R www-data:www-data \
        application/cache \
        application/logs \
        application/sessions \
        uploads \
        temp \
    && chmod -R ug+rwX \
        application/cache \
        application/logs \
        application/sessions \
        uploads \
        temp

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# Production nginx — CodeIgniter root is repo root (index.php)
FROM nginx:1.25-alpine AS web

COPY --from=production /var/www/html /var/www/html
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
