FROM dunglas/frankenphp:latest

RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install sockets \
    && apt-get update && apt-get install -y --no-install-recommends libevent-dev libssl-dev pkg-config $PHPIZE_DEPS \
    && pecl install event \
    && docker-php-ext-enable --ini-name zzz-event.ini event \
    && docker-php-ext-install mysqli pdo_mysql \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV DB_MAX_CONNECTIONS=12 \
    DB_WARM_CONNECTIONS=12

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-progress --optimize-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize

RUN php vendor/bin/kinetis build

CMD frankenphp php-server --listen :8080 --root /app/public --worker "/app/public/index.php,${KINETIS_WORKER_THREADS:-128}"
