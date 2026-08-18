FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx bash curl icu-dev icu-data-full oniguruma-dev \
    && docker-php-ext-install pdo_mysql intl mbstring opcache

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
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool-tuning.conf

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && mkdir -p tmp/cache/models tmp/cache/persistent tmp/cache/views tmp/sessions logs \
    && chown -R www-data:www-data tmp logs

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8080

CMD ["/start.sh"]
