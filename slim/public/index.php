<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->getRouteCollector()->setCacheFile(sys_get_temp_dir() . '/slim-routes.cache.php');

(require __DIR__ . '/../src/routes.php')($app);

$app->run();
