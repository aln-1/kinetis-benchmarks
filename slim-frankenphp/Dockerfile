FROM dunglas/frankenphp:latest

RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql

# The same production php.ini and opcache/JIT settings every other target
# builds with, so the comparison is between frameworks and runtimes
# rather than between engine configurations.
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

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-progress --optimize-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize

CMD frankenphp php-server --listen :8080 --root /app/public --worker "/app/public/index.php,${SLIM_WORKER_THREADS:-2}"
