<?php

declare(strict_types=1);

// Same as kinetis/public/index.php, except the runtime adapter is
// constructed directly instead of via RuntimeDetector::detect(): the
// installed kinetis/framework here is the latest published release
// (v1.34.0), which predates RuntimeDetector's RR_MODE-based
// RoadRunnerAdapter selection (still unreleased at the time this
// benchmark was built). Constructing it explicitly sidesteps that
// entirely — everything else about the request lifecycle is identical.

use Kinetis\Cache\CacheStore;
use Kinetis\Cache\Compiler;
use Kinetis\Cache\RoutesFile;
use Kinetis\Config\Config;
use Kinetis\Config\EnvFile;
use Kinetis\Container\AppScope;
use Kinetis\Events\EventListenerDiscovery;
use Kinetis\Events\EventListenerRegistry;
use Kinetis\Http\Kernel;
use Kinetis\Http\Middleware\GlobalMiddlewareDiscovery;
use Kinetis\Instrumentation\Telemetry;
use Kinetis\Http\Routing\RouteDiscovery;
use Kinetis\Http\Routing\Router;
use Kinetis\Runtime\AppEnvironment;
use Kinetis\Runtime\ProjectRoot;
use Kinetis\RoadRunnerAdapter\RoadRunnerAdapter;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = ProjectRoot::detect(__DIR__);

$phases = [];
$phaseStart = microtime(true);
EnvFile::safeLoad($projectRoot);
$phases['bootstrap.env'] = [$phaseStart, microtime(true)];

$env = AppEnvironment::detect();
$store = new CacheStore($projectRoot . '/.kinetis-cache');

$app = new AppScope();
$config = Config::fromEnvironment();
$app->instance(Config::class, $config);

$httpCache = null;

if ($env->isProduction()) {
    $httpCache = $store->loadHttp();

    if ($httpCache === null) {
        $compiled = (new Compiler())->compileProject($projectRoot);
        $store->writeAll($compiled);
        $httpCache = $compiled->http;
        $eventCache = $compiled->events;
    } else {
        $eventCache = $store->loadEvents();
    }

    $router = Router::fromArray($httpCache->routes);
    $discoveredGlobalMiddleware = $httpCache->globalMiddleware;
    $discoveredOpenApiMiddleware = $httpCache->openApiMiddleware;
    $middlewareGroups = $httpCache->middlewareGroups;
    $listenerRegistry = EventListenerRegistry::fromArray($eventCache?->listeners ?? []); // @phpstan-ignore nullsafe.neverNull
    $packageBootstraps = $httpCache->packageBootstraps;
} else {
    $phaseStart = microtime(true);
    $router = RouteDiscovery::discover($projectRoot);
    $discoveredMiddleware = GlobalMiddlewareDiscovery::discoverAll($projectRoot);
    $discoveredGlobalMiddleware = $discoveredMiddleware['global'];
    $discoveredOpenApiMiddleware = $discoveredMiddleware['openApi'];
    $middlewareGroups = $discoveredMiddleware['groups'];
    $listenerRegistry = EventListenerDiscovery::discover($projectRoot);
    $packageBootstraps = null;
    $phases['bootstrap.discovery'] = [$phaseStart, microtime(true)];
}

$phaseStart = microtime(true);
RoutesFile::loadBootstrap($projectRoot, $packageBootstraps)($app, $config);
$phases['bootstrap.services'] = [$phaseStart, microtime(true)];

$app->instance(EventListenerRegistry::class, $listenerRegistry);
$app->boot();

$telemetry = Telemetry::global();

foreach ($phases as $phaseName => [$phaseStartedAt, $phaseEndedAt]) {
    $telemetry->phase($phaseName, $phaseStartedAt, $phaseEndedAt);
}

$adapter = new RoadRunnerAdapter();

$kernel = new Kernel(
    $app,
    $router,
    isPersistent: $adapter->isPersistent(),
    httpCache: $httpCache,
    discoveredGlobalMiddleware: $discoveredGlobalMiddleware,
    discoveredOpenApiMiddleware: $discoveredOpenApiMiddleware,
    middlewareGroups: $middlewareGroups,
);

$adapter->run($kernel->handle(...));
