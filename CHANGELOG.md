# Changelog

## Unreleased

- Fixed the local quality gate so it no longer opens coverage reports or mutates files.
- Removed the runtime dependency leak from routing into Prophecy tag constants.
- Tightened route metadata contracts so stored middleware stacks contain resolved middleware classes.
- Added container-store support for string `Class@method` handlers.
- Restored the full local quality gate across PHPUnit, Psalm, PHPCS, and Composer validation.
- Fixed route group expansion for middleware service IDs registered as interfaces.
- Added stack integration coverage for container-backed routing and argon-middleware responders.
