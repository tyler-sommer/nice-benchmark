<?php

use TylerSommer\Nice\Benchmark\Benchmark;

function getRandomParts()
{
    $rand = md5(uniqid(mt_rand(), true));
    return array(
        substr($rand, 0, 10),
        substr($rand, -10),
    );
}

function getRoutes($numRoutes, $argString)
{
    $routes = [];
    for ($i = 0; $i < $numRoutes; $i++) {
        list ($pre, $post) = getRandomParts();
        $route = '/' . $pre . '/' . $argString . '/' . $post;
        $routes[] = $route;
    }

    return $routes;
}
/**
 * Sets up FastRoute tests
 */
function setupFastRoute(Benchmark $benchmark, $numRoutes, $args)
{
    $argString = implode('/', array_map(function ($i) { return "{arg$i}"; }, range(1, $args)));

    $routes = getRoutes($numRoutes, $argString);
    $lastStr = $routes[$numRoutes - 1];

    $benchmark->register(sprintf('FastRoute - last route (%s routes)', $numRoutes), function () use ($routes, $lastStr) {
        $router = FastRoute\simpleDispatcher(function ($router) use ($routes) {
            foreach ($routes as $i => $route) {
                $router->addRoute('GET', $route, 'handler' . $i);
            }
        });

        $route = $router->dispatch('GET', $lastStr);
    });

    $benchmark->register(sprintf('FastRoute - unknown route (%s routes)', $numRoutes), function () use ($routes) {
        $router = FastRoute\simpleDispatcher(function ($router) use ($routes) {
            foreach ($routes as $i => $route) {
                $router->addRoute('GET', $route, 'handler' . $i);
            }
        });

        $route = $router->dispatch('GET', '/not-even-real');
    });
}

/**
 * Sets up Pux tests
 */
function setupPux(Benchmark $benchmark, $numRoutes, $args)
{
    $argString = implode('/', array_map(function ($i) { return ':arg' . $i; }, range(1, $args)));

    $routes = getRoutes($numRoutes, $argString);
    $lastStr = $routes[$numRoutes - 1];


    $benchmark->register(sprintf('Pux PHP - last route (%s routes)', $numRoutes), function () use ($routes, $lastStr) {
        $router = new Pux\Mux;
        foreach ($routes as $i => $route) {
            $router->add($route, 'handler' . $i);
        }
        $route = $router->match($lastStr);
    });

    $benchmark->register(sprintf('Pux PHP - unknown route (%s routes)', $numRoutes), function () use ($routes) {
        $router = new Pux\Mux;
        foreach ($routes as $i => $route) {
            $router->add($route, 'handler' . $i);
        }
        $route = $router->match('GET', '/not-even-real');
    });
}

/**
 * Sets up Symfony 2 tests
 */
function setupSymfony2(Benchmark $benchmark, $numRoutes, $args)
{
    $argString = implode('/', array_map(function ($i) { return "{arg$i}"; }, range(1, $args)));

    $routes = getRoutes($numRoutes, $argString);
    $lastStr = $routes[$numRoutes - 1];

    $benchmark->register(sprintf('Symfony2 - last route (%s routes)', $numRoutes), function () use ($routes, $lastStr) {
        $sfRoutes = new Symfony\Component\Routing\RouteCollection();
        $router = new Symfony\Component\Routing\Matcher\UrlMatcher($sfRoutes, new Symfony\Component\Routing\RequestContext());
        foreach ($routes as $i => $route) {
            $sfRoutes->add($route, new Symfony\Component\Routing\Route($route, array('controller' => 'handler' . $i)));
        }
        $route = $router->match($lastStr);
    });

    $benchmark->register(sprintf('Symfony2 - unknown route (%s routes)', $numRoutes), function () use ($routes) {
        try {
            $sfRoutes = new Symfony\Component\Routing\RouteCollection();
            $router = new Symfony\Component\Routing\Matcher\UrlMatcher($sfRoutes, new Symfony\Component\Routing\RequestContext());
            foreach ($routes as $i => $route) {
                $sfRoutes->add($route, new Symfony\Component\Routing\Route($route, array('controller' => 'handler' . $i)));
            }
            $route = $router->match('/not-even-real');
        } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e) { }
    });
}

/**
 * Sets up Symfony2 optimized tests
 */
function setupSymfony2Optimized(Benchmark $benchmark, $numRoutes, $args)
{
    $argString = implode('/', array_map(function ($i) { return "{arg$i}"; }, range(1, $args)));

    $routes = getRoutes($numRoutes, $argString);
    $lastStr = $routes[$numRoutes - 1];

    $sfRoutes = new Symfony\Component\Routing\RouteCollection();

    foreach ($routes as $i => $route) {
        $sfRoutes->add($route, new Symfony\Component\Routing\Route($route, array('controller' => 'handler' . $i)));
    }

    $dumper = new Symfony\Component\Routing\Matcher\Dumper\PhpMatcherDumper($sfRoutes);
    file_put_contents(__DIR__ . '/sf2router.php', $dumper->dump());

    require_once __DIR__ . '/sf2router.php';
    $benchmark->register(sprintf('Symfony2 Dumped - last route (%s routes)', $numRoutes), function () use ($lastStr) {
        $router = new ProjectUrlMatcher(new Symfony\Component\Routing\RequestContext());
        $route = $router->match($lastStr);
    });

    $benchmark->register(sprintf('Symfony2 Dumped - unknown route (%s routes)', $numRoutes), function () {
        try {
            $router = new ProjectUrlMatcher(new Symfony\Component\Routing\RequestContext());
            $route = $router->match('/not-even-real');
        } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e) { }
    });
}

/**
 * Sets up Aura v2 tests
 *
 * https://github.com/auraphp/Aura.Router
 */
function setupAura2(Benchmark $benchmark, $numRoutes, $args)
{
    $argString = implode('/', array_map(function ($i) { return "{arg$i}"; }, range(1, $args)));
    $routes = getRoutes($numRoutes, $argString);
    $lastStr = $routes[$numRoutes - 1];

    $benchmark->register(sprintf('Aura v2 - last route (%s routes)', $numRoutes), function () use ($routes, $lastStr) {
        $router = new Aura\Router\Router(
            new Aura\Router\RouteCollection(
                new Aura\Router\RouteFactory()
            )
        );

        foreach ($routes as $i => $route) {
            $router->add($route, $route)
                ->addValues(array(
                    'controller' => 'handler' . $i
                ));
        }

        $route = $router->match($lastStr, $_SERVER);
    });

    $benchmark->register(sprintf('Aura v2 - unknown route (%s routes)', $numRoutes), function () use ($routes) {
        $router = new Aura\Router\Router(
            new Aura\Router\RouteCollection(
                new Aura\Router\RouteFactory()
            )
        );

        foreach ($routes as $i => $route) {
            $router->add($route, $route)
                ->addValues(array(
                    'controller' => 'handler' . $i
                ));
        }

        $route = $router->match('/not-even-real', $_SERVER);
    });
}
