FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx bash curl icu-libs oniguruma \
    && apk add --no-cache --virtual .build-deps icu-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql intl mbstring opcache \
    && apk del .build-deps

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

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && mkdir -p runtime web/assets \
    && chmod -R 777 runtime web/assets

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY start.sh /start.sh
RUN chmod +x /start.sh

ENV YII_ENV=prod
ENV YII_DEBUG=false

EXPOSE 8080

CMD ["/start.sh"]
