# Concepts

Shared domain vocabulary for this project — entities, named processes, and status concepts with project-specific meaning. Seeded with core domain vocabulary, then accretes as ce-compound and ce-compound-refresh process learnings; direct edits are fine. Glossary only, not a spec or catch-all.

## Packaging

### Base Package
The standalone commerce package: everything that works on its own, with no notion of a vendor or shop owner. A separate multivendor layer extends it through declared seams rather than by modification.

The base package never references vendor concepts directly. Where a vendor would be involved, it exposes a configuration point that is empty by default and an extension seam the multivendor layer fills in. Code paths that need a vendor fail loudly when none is configured, rather than silently degrading.

## Admin access

### Panel Gate
The single package-level authorization check that decides whether a user may operate the SparkCommerce admin resources. Every admin resource consults the same gate, so access is all-or-nothing across the package rather than per-resource.

The gate is the panel-level floor, not the whole authorization model — finer per-record checks may sit on top of it. It composes with, and runs behind, whatever check the host application already applies to reach its admin panel; it can tighten that decision but never loosen it. A host may replace the gate's default logic wholesale with its own invokable class. The default is deny: an unconfigured Admin Role, an unauthenticated user, or a user model with no roles support all fail the gate.

### Admin Role
The role a user must hold to pass the Panel Gate under its default logic. The package publishes this role through a console command so a fresh install has one to assign.

The role is global, not scoped: holding it grants access to every SparkCommerce admin resource across the whole application. Its name is configurable, so treat "the admin role" as the concept and never assume a literal name.
