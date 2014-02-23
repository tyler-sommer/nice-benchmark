<?php

use TylerSommer\Nice\Benchmark\Benchmark;

error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/tests.php';

$iterations = 1000;
$routes = 1000;
$args = 9;

$benchmark = new Benchmark($iterations);

setupAura2($benchmark, $routes, $args);
setupFastRoute($benchmark, $routes, $args);
setupSymfony2($benchmark, $routes, $args);
setupPux($benchmark, $routes, $args);

$benchmark->execute();
