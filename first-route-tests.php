<?php

namespace FirstRouteMatching;

use Dash\Router\Http\Parser\Segment;
use Dash\Router\Http\Route\Generic;
use Dash\Router\Http\Router;
use RouterBenchmarks\Dash\RouteCollection;
use TylerSommer\Nice\Benchmark\Benchmark;
use TylerSommer\Nice\Benchmark\ResultPrinter\MarkdownPrinter;
use Zend\Http\Request;

/**
 * Sets up the First-route matching benchmark.
 *
 * This benchmark tests how quickly each router can match the first route
 *
 * @param $numIterations
 * @param $numRoutes
 * @param $numArgs
 *
 * @return Benchmark
 */
function setupBenchmark($numIterations, $numRoutes, $numArgs)
{
    $benchmark = new Benchmark($numIterations, 'First route matching', new MarkdownPrinter());
    $benchmark->setDescription(sprintf(
            'This benchmark tests how quickly each router can match the first route. %s routes each with %s arguments.',
            number_format($numRoutes),
            $numArgs
        ));

    setupAura2($benchmark, $numRoutes, $numArgs);
    setupFastRoute($benchmark, $numRoutes, $numArgs);
    setupSymfony2($benchmark, $numRoutes, $numArgs);
    setupSymfony2Optimized($benchmark, $numRoutes, $numArgs);
    setupPux($benchmark, $numRoutes, $numArgs);
    setupDash($benchmark, $numRoutes, $numArgs);

    return $benchmark;
}

function getRandomParts()
{
    $rand = md5(uniqid(mt_rand(), true));

    return array(
        substr($rand, 0, 10),
        substr($rand, -10),
    );
}

/**
 * Sets up FastRoute tests
 */
function setupFastRoute(Benchmark $benchmark, $routes, $args)
{
    $argString = implode('/', array_map(function ($i) { return "{arg$i}"; }, range(1, $args)));
    $str = $firstStr = $lastStr = '';
    $router = \FastRoute\simpleDispatcher(function ($router) use ($routes, $argString, &$lastStr, &$firstStr) {
            for ($i = 0; $i < $routes; $i++) {
                list ($pre, $post) = getRandomParts();
                $str = '/' . $pre . '/' . $argString . '/' . $post;

                if (0 === $i) {
                    $firstStr = str_replace(array('{', '}'), '', $str);
                }
                $lastStr = str_replace(array('{', '}'), '', $str);

                $router->addRoute('GET', $str, 'handler' . $i);
            }
        });

    $benchmark->register(sprintf('FastRoute - first route', $routes), function () use ($router, $firstStr) {
            $route = $router->dispatch('GET', $firstStr);
        });
}

/**
 * Sets up Pux tests
 */
function setupPux(Benchmark $benchmark, $routes, $args)
{
    $name = extension_loaded('pux') ? 'Pux ext' : 'Pux PHP';
    $argString = implode('/', array_map(function ($i) { return ':arg' . $i; }, range(1, $args)));
    $str = $firstStr = $lastStr = '';
    $router = new \Pux\Mux;
    for ($i = 0; $i < $routes; $i++) {
        list ($pre, $post) = getRandomParts();
        $str = '/' . $pre . '/' . $argString . '/' . $post;

        if (0 === $i) {
            $firstStr = str_replace(':', '', $str);
        }
        $lastStr = str_replace(':', '', $str);

        $router->add($str, 'handler' . $i);
    }

    $benchmark->register(sprintf('%s - first route', $name), function () use ($router, $firstStr) {
            $route = $router->match($firstStr);
        });
}

/**
 * Sets up Symfony 2 tests
 */
function setupSymfony2(Benchmark $benchmark, $routes, $args)
{
    $argString = implode('/', array_map(function ($i) { return "{arg$i}"; }, range(1, $args)));
    $str = $firstStr = $lastStr = '';
    $sfRoutes = new \Symfony\Component\Routing\RouteCollection();
    $router = new \Symfony\Component\Routing\Matcher\UrlMatcher($sfRoutes, new \Symfony\Component\Routing\RequestContext());
    for ($i = 0, $str = 'a'; $i < $routes; $i++, $str++) {
        list ($pre, $post) = getRandomParts();
        $str = '/' . $pre . '/' . $argString . '/' . $post;

        if (0 === $i) {
            $firstStr = str_replace(array('{', '}'), '', $str);
        }
        $lastStr = str_replace(array('{', '}'), '', $str);

        $sfRoutes->add($str, new \Symfony\Component\Routing\Route($str, array('controller' => 'handler' . $i)));
    }

    $benchmark->register('Symfony2 - first route', function () use ($router, $firstStr) {
            $route = $router->match($firstStr);
        });
}

/**
 * Sets up Symfony2 optimized tests
 */
function setupSymfony2Optimized(Benchmark $benchmark, $routes, $args)
{
    $argString = implode('/', array_map(function ($i) { return "{arg$i}"; }, range(1, $args)));
    $str = $firstStr = $lastStr = '';
    $sfRoutes = new \Symfony\Component\Routing\RouteCollection();
    for ($i = 0, $str = 'a'; $i < $routes; $i++, $str++) {
        list ($pre, $post) = getRandomParts();
        $str = '/' . $pre . '/' . $argString . '/' . $post;

        if (0 === $i) {
            $firstStr = str_replace(array('{', '}'), '', $str);
        }
        $lastStr = str_replace(array('{', '}'), '', $str);

        $sfRoutes->add($str, new \Symfony\Component\Routing\Route($str, array('controller' => 'handler' . $i)));
    }
    $dumper = new \Symfony\Component\Routing\Matcher\Dumper\PhpMatcherDumper($sfRoutes);
    file_put_contents(__DIR__ . '/files/first-route-sf2.php', $dumper->dump(array(
                'class' => 'FirstRouteSf2UrlMatcher'
            )));
    require_once __DIR__ . '/files/first-route-sf2.php';

    $router = new \FirstRouteSf2UrlMatcher(new \Symfony\Component\Routing\RequestContext());

    $benchmark->register('Symfony2 Dumped - first route', function () use ($router, $firstStr) {
            $route = $router->match($firstStr);
        });
}

/**
 * Sets up Aura v2 tests
 *
 * https://github.com/auraphp/Aura.Router
 */
function setupAura2(Benchmark $benchmark, $routes, $args)
{
    $argString = implode('/', array_map(function ($i) { return "{arg$i}"; }, range(1, $args)));
    $lastStr = '';
    $router = new \Aura\Router\Router(
        new \Aura\Router\RouteCollection(
            new \Aura\Router\RouteFactory()
        )
    );
    for ($i = 0, $str = 'a'; $i < $routes; $i++, $str++) {
        list ($pre, $post) = getRandomParts();
        $str = '/' . $pre . '/' . $argString . '/' . $post;

        if (0 === $i) {
            $firstStr = str_replace(array('{', '}'), '', $str);
        }
        $lastStr = str_replace(array('{', '}'), '', $str);

        $router->add($str, $str)
            ->addValues(array(
                    'controller' => 'handler' . $i
                ));
    }

    $benchmark->register('Aura v2 - first route', function () use ($router, $firstStr) {
            $route = $router->match($firstStr);
        });
}

/**
 * Sets up Dash tests
 *
 * https://github.com/DASPRiD/Dash
 */
function setupDash(Benchmark $benchmark, $routes, $args)
{
    $argString = implode('/', array_map(function ($i) { return "{arg$i}"; }, range(1, $args)));
    $str = $firstStr = $lastStr = '';
    $routeCollection = new RouteCollection();
    for ($i = 0; $i < $routes; $i++) {
        list ($pre, $post) = getRandomParts();
        $str = '/' . $pre . '/' . $argString . '/' . $post;

        if (0 === $i) {
            $firstStr = str_replace(array('{', '}'), '', $str);
        }
        $lastStr = str_replace(array('{', '}'), '', $str);

        $route = new Generic();
        $route->setMethods(array('get'));
        $route->setPathParser(new Segment('/', $str, array()));

        $routeCollection->insert('handler' . $i, $route);
    }

    $router = new Router($routeCollection);

    $firstStrRequest = Request::fromString(sprintf('GET %s HTTP/1.1', $firstStr));

    $benchmark->register(sprintf('Dash - first route', $routes), function () use ($router, $firstStrRequest) {
            $route = $router->match($firstStrRequest);
        });
}