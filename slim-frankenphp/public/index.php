<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Built once per worker rather than once per request: routing table,
// container, and the PDO connection App\Database holds all survive into
// every request this worker goes on to serve.
$app = AppFactory::create();
$app->getRouteCollector()->setCacheFile(sys_get_temp_dir() . '/slim-routes.cache.php');

(require __DIR__ . '/../src/routes.php')($app);

$handler = static function () use ($app): void {
    $app->run();
};

$running = true;

while ($running) {
    $running = frankenphp_handle_request($handler);
    gc_collect_cycles();
}
