<?php

declare(strict_types=1);

use App\Application\Kernel;
use Spiral\Core\Container;
use Spiral\Core\Options;

\mb_internal_encoding('UTF-8');
\error_reporting(E_ALL ^ E_DEPRECATED);
\ini_set('display_errors', 'stderr');

require __DIR__ . '/vendor/autoload.php';

$options = new Options();
$options->allowSingletonsRebinding = false;
$container = new Container(options: $options);
$app = Kernel::create(
    directories: ['root' => __DIR__],
    container: $container,
)->run();

if ($app === null) {
    exit(255);
}

$code = (int) $app->serve();
exit($code);
