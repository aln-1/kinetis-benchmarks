FROM ghcr.io/roadrunner-server/roadrunner:latest AS roadrunner

FROM php:8.4-cli-alpine

# ext-sockets compiles fine under Alpine — $PHPIZE_DEPS alone is not
# enough (a missing linux/sock_diag.h), but linux-headers closes the gap.
RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && docker-php-ext-install sockets mysqli pdo_mysql opcache \
    && apk del $PHPIZE_DEPS linux-headers

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=1"; \
        echo "opcache.validate_timestamps=0"; \
        echo "opcache.memory_consumption=192"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "opcache.jit=tracing"; \
        echo "opcache.jit_buffer_size=64M"; \
    } > "$PHP_INI_DIR/conf.d/zz-opcache-tuning.ini"

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./
RUN composer install --no-dev --no-scripts --no-interaction --no-progress --optimize-autoloader

COPY . .

RUN composer dump-autoload --no-dev --optimize

EXPOSE 8080

CMD ["rr", "serve", "-c", ".rr.yaml"]
