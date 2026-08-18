FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx bash curl icu-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql mysqli intl mbstring opcache \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=0"; \
        echo "opcache.validate_timestamps=0"; \
        echo "opcache.memory_consumption=192"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "opcache.jit=tracing"; \
        echo "opcache.jit_buffer_size=64M"; \
    } > "$PHP_INI_DIR/conf.d/zz-opcache-tuning.ini"
COPY php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool-tuning.conf

WORKDIR /var/www/html

RUN composer create-project codeigniter4/appstarter . --no-dev --no-interaction --no-progress --no-install
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress

COPY app-overrides/Config/Routes.php app/Config/Routes.php
COPY app-overrides/Config/Database.php app/Config/Database.php
COPY app-overrides/Controllers/Bench.php app/Controllers/Bench.php
COPY app-overrides/Views/fortunes.php app/Views/fortunes.php

RUN php spark optimize

RUN chown -R www-data:www-data writable \
    && chmod -R 775 writable

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY start.sh /start.sh
RUN chmod +x /start.sh

ENV CI_ENVIRONMENT=production

EXPOSE 8080

CMD ["/start.sh"]
