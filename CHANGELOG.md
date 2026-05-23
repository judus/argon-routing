# Changelog

## Unreleased

- Documented the route not-found exception contract.
- Fixed the local quality gate so it no longer opens coverage reports or mutates files.
- Removed the runtime dependency leak from routing into Prophecy tag constants.
- Tightened route metadata contracts so stored middleware stacks contain resolved middleware classes.
- Added container-store support for string `Class@method` handlers.
- Restored the full local quality gate across PHPUnit, Psalm, PHPCS, and Composer validation.
- Fixed route group expansion for middleware service IDs registered as interfaces.
- Added stack integration coverage for container-backed routing and argon-middleware responders.
- Documented the middleware service-id cache contract and made invalid middleware definitions fail with routing exceptions.
- Documented the container-backed handler argument contract and covered array/string method handlers in stack tests.
