# Argon Routing

[![PHP](https://img.shields.io/badge/php-8.2+-blue)](https://www.php.net/)
[![Build](https://github.com/judus/argon-routing/actions/workflows/php.yml/badge.svg)](https://github.com/judus/argon-routing/actions)
[![codecov](https://codecov.io/gh/judus/argon-routing/branch/master/graph/badge.svg)](https://codecov.io/gh/judus/argon-routing)
[![Psalm Level](https://shepherd.dev/github/judus/argon-routing/coverage.svg)](https://shepherd.dev/github/judus/argon-routing)
[![Code Style](https://img.shields.io/badge/code%20style-PSR--12-brightgreen.svg)](https://www.php-fig.org/psr/psr-12/)
[![Latest Version](https://img.shields.io/packagist/v/maduser/argon-routing.svg)](https://packagist.org/packages/maduser/argon-routing)
[![Downloads](https://img.shields.io/packagist/dt/maduser/argon-routing.svg)](https://packagist.org/packages/maduser/argon-routing)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Argon Routing is the HTTP routing layer that powers the Argon ecosystem.  
It is designed around the Argon container, the shared middleware pipeline, and
the request-handler resolver. The library embraces those components to provide:

- auto-registration of routes in the container;
- seamless middleware stack compilation;
- request handler resolution that honours Argon’s logging and pipeline stages.

Because of those tight integrations, **Argon Routing is not intended for use
outside the Argon stack**. It has hard runtime dependencies on:

- `maduser/argon` (service container and service providers);
- `maduser/argon-middleware` (pipeline manager/store).

If you need a framework-agnostic router, you should look at an alternative.

## Installation

```bash
composer require maduser/argon-routing
```

Make sure your project already pulls in `maduser/argon` and
`maduser/argon-middleware`; they are required at runtime. When developing
locally inside the Argon monorepo, the path repositories configured there will
resolve the packages automatically.

## Quick Start

```php
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Routing\Router;
use Maduser\Argon\Routing\RouteManager;
use Maduser\Argon\Routing\RouteMatcher;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\CsrfMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;

$container = new ArgonContainer();
$routes = new RouteManager();                // defaults to the in-memory store
$router = new Router($container, $routes);

// Define routes just like in a typical HTTP kernel
$router->get('/users/{id}', 'UsersController@show', middleware: [AuthMiddleware::class]);
$router->post('/users', 'UsersController@store', middleware: [AuthMiddleware::class, CsrfMiddleware::class]);

// Match an incoming request
$psr17 = new Psr17Factory();
$request = $psr17->createServerRequest('GET', '/users/42');

$matcher = new RouteMatcher($routes);
$matched = $matcher->match($request);

// Inspect the matched route (all PSR types)
$matched->getHandler();      // UsersController@show
$matched->getArguments();    // ['id' => '42']
$matched->getMiddlewares();  // [AuthMiddleware::class]
```

Middleware passed to the router can be concrete middleware service IDs or group
aliases from the container's `middleware.http` tag metadata. Route metadata stores
the resolved container service IDs, not the original aliases, so the cached route
stack can be handed directly to `maduser/argon-middleware`. Those service IDs may
be concrete middleware classes or interfaces bound in the container.

Once you hand the `RouteManager` instance to Argon’s middleware pipeline and
request-handler resolver (see the main Argon documentation), requests will flow
through the registered middleware stacks and invoke your controllers through the
container.

## Handler Contract

Container-backed routes support these handler definitions:

```php
$router->get('/health', HealthController::class);
$router->get('/users/{id}', [UsersController::class, 'show']);
$router->get('/articles/{slug}', ArticlesController::class . '@show');
```

Route placeholders are forwarded to the container invocation as named arguments.
Controller methods should declare those placeholders directly:

```php
final class UsersController
{
    public function show(string $id): array
    {
        return ['id' => $id];
    }
}
```

Do not expect a single `array $args` parameter in container-backed handlers. The
container prepares controller invocations through reflection, so route arguments
are mapped by name.

## Not Found Contract

When no route matches the incoming request, `RouteMatcher` throws
`Maduser\Argon\Routing\Exception\RouteNotFoundException`. The exception carries
code `404`, which lets Argon runtime error handlers render the failure as an HTTP
not-found response instead of an internal-server-error.

## Model / DTO Bindings via Interceptors

Frameworks such as Laravel or Symfony implement “route model binding” inside the
router. In Argon the router keeps things agnostic: it forwards the raw route
arguments (`['user' => '42']`) into the container, and **interceptors** take care
of loading any richer objects you expect in your controllers.

```php
// Register the interceptors that should run before your handlers are invoked.
// Each can examine the matched route from the request attribute, inspect payloads, etc.
$container->registerInterceptor(EntityBindingInterceptor::class);
$container->registerInterceptor(JsonPayloadInterceptor::class);
$container->registerInterceptor(RequestValidationInterceptor::class);

$router->get('/users/{user}', [UsersController::class, 'show']);

final class UsersController
{
    public function show(User $userDto): ResponseInterface
    {
        // $userDto already passed through the interceptors
    }
}
```

Instead of hard-coding a single binding strategy in the router, this approach lets
you decide how arguments should be turned into rich objects: one interceptor may
hydrate entities from IDs, another may validate and map JSON payloads into DTOs,
others could parse XML or JSON-RPC requests. Because interceptors are just classes
registered with the container, you can combine and layer them however you like.
See the Argon container README for more patterns and advanced usage.

## Notes

- The `InMemoryStore` bundled with the package exists to keep tests simple and
  for early prototyping; production builds should rely on the container-backed
  store registered by the `ArgonRoutingServiceProvider`.
- The `FileSystemStore` is a placeholder for potential standalone usage. It is
  not required when running inside Argon.
- Shared interfaces (such as `ResultContextInterface`) now ship with `maduser/argon-middleware`
  packages; make sure your project is aligned with the versions shipped there.
