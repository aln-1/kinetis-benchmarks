<?php

use CodeIgniter\Router\RouteCollection;

$routes->get('/', 'Bench::index');
$routes->get('json', 'Bench::json');
$routes->get('plaintext', 'Bench::plaintext');
$routes->get('db', 'Bench::db');
$routes->get('queries', 'Bench::queries');
$routes->get('updates', 'Bench::updates');
$routes->get('fortunes', 'Bench::fortunes');
