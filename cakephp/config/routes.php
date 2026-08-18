<?php
/**
 * Routes configuration.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * It's loaded within the context of `Application::routes()` method which
 * receives a `RouteBuilder` instance `$routes` as method argument.
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/json', ['controller' => 'Bench', 'action' => 'json']);
        $builder->connect('/plaintext', ['controller' => 'Bench', 'action' => 'plaintext']);
        $builder->connect('/db', ['controller' => 'Bench', 'action' => 'db']);
        $builder->connect('/queries', ['controller' => 'Bench', 'action' => 'queries']);
        $builder->connect('/updates', ['controller' => 'Bench', 'action' => 'updates']);
        $builder->connect('/fortunes', ['controller' => 'Bench', 'action' => 'fortunes']);
    });

    $routes->setRouteClass(DashedRoute::class);
};
