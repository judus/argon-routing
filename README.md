# Argon Routing

Argon Routing is the HTTP routing layer that powers the Argon ecosystem.  
It is designed around the Argon container, the shared middleware pipeline, and
the request-handler resolver. The library embraces those components to provide:

- auto-registration of routes in the container;
- seamless middleware stack compilation;
- request handler resolution that honours Argon’s logging and pipeline stages.

Because of those tight integrations, **Argon Routing is not intended for use
outside the Argon stack**. It has hard runtime dependencies on:

- `maduser/argon` (service container, result context, service providers);
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
use Maduser\Argon\Routing\ArgonRouter;
use Maduser\Argon\Routing\RouteManager;
use Maduser\Argon\Routing\RouteMatcher;
use Nyholm\Psr7\Factory\Psr17Factory;

$container = new ArgonContainer();
$routes = new RouteManager();                // defaults to the in-memory store
$router = new ArgonRouter($container, $routes);

// Define routes just like in a typical HTTP kernel
$router->get('/users/{id}', 'UsersController@show', middleware: ['auth']);
$router->post('/users', 'UsersController@store', middleware: ['auth', 'csrf']);

// Match an incoming request
$psr17 = new Psr17Factory();
$request = $psr17->createServerRequest('GET', '/users/42');

$matcher = new RouteMatcher($routes);
$matched = $matcher->match($request);

// Inspect the matched route (all PSR types)
$matched->getHandler();      // UsersController@show
$matched->getArguments();    // ['id' => '42']
$matched->getMiddlewares();  // ['auth']
```

Once you hand the `RouteManager` instance to Argon’s middleware pipeline and
request-handler resolver (see the main Argon documentation), requests will flow
through the registered middleware stacks and invoke your controllers through the
container.

## Model / DTO Bindings via Interceptors

Frameworks such as Laravel or Symfony implement “route model binding” inside the
router. In Argon the router keeps things agnostic: it forwards the raw route
arguments (`['user' => '42']`) into the container, and **interceptors** take care
of loading any richer objects you expect in your controllers.

```php
// Register the interceptors that should run before your handlers are invoked.
// Each can look at the current RouteContext, request payload, headers, etc.
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
- Shared interfaces (such as `ResultContextInterface`) live in the core Argon
  packages; make sure your project is aligned with the versions shipped there.
