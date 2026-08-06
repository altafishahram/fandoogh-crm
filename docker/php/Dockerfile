FROM php:8.3.32-fpm-bookworm@sha256:2a397791f5ee422190bb673d79332be53ff545205f6df19e2664bd664ebbd739

ARG APP_USER=app
ARG APP_UID=1000
ARG APP_GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && groupadd --gid "${APP_GID}" "${APP_USER}" \
    && useradd --uid "${APP_UID}" --gid "${APP_GID}" --create-home --shell /bin/bash "${APP_USER}" \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.10.2@sha256:5946476338742b200bb9ff88f8be56275ddae4b3949c72305cb0dbf10cfcb760 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/99-fandoogh.ini
COPY docker/php/entrypoint.sh /usr/local/bin/fandoogh-entrypoint

RUN chmod +x /usr/local/bin/fandoogh-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["fandoogh-entrypoint"]
CMD ["php-fpm"]
