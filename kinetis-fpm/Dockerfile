FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx bash curl unzip \
    && docker-php-ext-install pdo_mysql opcache

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
COPY kinetis-fpm/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool-tuning.conf

WORKDIR /var/www/html

COPY kinetis/composer.json kinetis/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-progress --optimize-autoloader

COPY kinetis/public ./public
COPY kinetis/src ./src
COPY kinetis/resources ./resources

RUN composer dump-autoload --no-dev --optimize

RUN php vendor/bin/kinetis build

COPY kinetis-fpm/nginx.conf /etc/nginx/http.d/default.conf
COPY kinetis-fpm/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8080

CMD ["/start.sh"]
