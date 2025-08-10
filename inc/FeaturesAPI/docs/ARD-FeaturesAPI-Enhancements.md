### ARD: Features API Enhancements (Docs, Tests, DX, Robustness)

- Area: `plugin-lib/inc/FeaturesAPI/*`
- Related docs: `inc/FeaturesAPI/readme.md`
- Date: 2025-08-09

## Purpose

Refresh documentation, add comprehensive tests, and improve developer experience and robustness for the Features API (registration, dependency injection, Accessory wiring, lifecycle).

## Current State (brief)

- `FeaturesManagerAbstract` registers features (slug → `FeatureContainer`) into `FeatureRegistry` and loads them by instantiating controllers, injecting deps via reflection, discovering Accessory Managers, and calling `init()`.
- Docs in `inc/FeaturesAPI/readme.md` exist but are outdated in places (signature/order, typos, deps shape).
- Tests for Features API are minimal.

## Goals

- Accurate, ergonomic documentation with correct examples and lifecycle overview.
- Strong unit test coverage for registration, DI, Accessory wiring, lifecycle, and error conditions.
- Normalize dependency injection to a straightforward associative array (breaking change: legacy nested deps are no longer supported).
- Improve error messages and remove HTML-escaping in exceptions.
- Fix naming/typos and minor API polish to reduce friction.

## Non-goals

- Major architectural rewrite of Accessory discovery model (only incremental hardening).
- Changing public method signatures. Note: the dependency payload shape change is an intentional breaking change.

## Priority and Work Plan

### P0 — Documentation corrections and alignment (fast wins)

- Impact: High; unblocks correct usage and tests.

Tasks:

- Update `inc/FeaturesAPI/readme.md`:
  - Correct `register_feature` signature and parameter order to `register_feature(string $slug, string $fqcn, array $deps = [])`.
  - Replace outdated example with a minimal working snippet, including `load_all()`.
  - Clarify dependencies as associative array: `['propName' => $value]`. Show public vs protected+setter examples.
  - Lifecycle section: register → instantiate → inject deps → enable accessories → `init()`.
  - Add "Common exceptions & causes" section (missing interface inheritance, missing property, missing setter, absent Manager).
  - Fix typos: “registery” → “registry”, “Registerable/FeatuerContainer” → “Registrable/FeatureContainer”.
- Cross-link Accessory docs: one sample interface/manager relation and expected Fully Qualified Class Name (FQCN) adjacency.

Acceptance:

- Readme matches code signatures; examples run as-is in a simple stub feature.
- Typos removed, dependency shape clear.

### P0 — Unit test suite (coverage foundation)

- Impact: High; secures behavior during refactors.

Tasks:

- Create `Tests/Unit/FeaturesAPI/` with:
  - Registration:
    - Unique slug registers; duplicate throws.
    - Slug sanitation via `sanitize_title()`.
  - Instantiation guards:
    - Throws if class doesn’t implement `RegistrableFeatureInterface`.
    - Throws if class doesn’t extend `FeatureControllerAbstract`.
  - Dependency injection:
    - Public property set works.
    - Protected/private property requires `set_<prop>()`, works when present, throws otherwise.
    - Missing property throws.
  - Accessories:
    - Implemented Accessory → adjacent `...Manager::init($instance)` invoked.
    - Manager exists but not implementing `AccessoryManagerBaseInterface` → throws with clear message.
    - Missing Manager → throws with clear message.
  - Lifecycle & Registry:
    - `load_all()` calls `init()` and stores instance in container.
    - `load($slug)` loads only that feature.
    - `get_registry()` and `get_registered_feature()` return expected types.

Acceptance:

- New tests pass locally (requires WordPress stubs or `wp-phpunit` bootstrap).
- Failures precisely identify contract breaks.

### P1 — Normalize dependency shape (DX improvement; breaking change)

- Impact: Medium/High; simplifies consumer code and docs alignment.

Tasks:

- Update `_set_instance_dependencies()` to accept associative arrays like:
  - `['logger' => $logger, 'apiClient' => $client]`.
- Tighten error messages to name the property and class unambiguously.

Acceptance:

- New tests confirm associative array path; legacy nested form is rejected with clear errors.
- Docs reflect the normalized shape and explicitly state the breaking change.

### P1 — Exception message hygiene (remove HTML escaping)

- Impact: Medium; reduces WP coupling, clearer CLI/dev logs.

Tasks:

- Replace `esc_html()` usage in exceptions with raw values; ensure messages are safe and informative.
- Keep `sprintf()` with quoted identifiers for clarity; no HTML escaping in thrown exceptions.

Acceptance:

- Tests assert on exception messages without WP functions.
- No lints or static analysis objections.

### P1 — Naming and minor API polish

- Impact: Medium; clarity and consistency.

Tasks:

- Public naming consistency:
  - Prefer “registry” spelling across docs and public methods. Keep internal names stable, but surface “registry” wording.
- Typed returns favoring interfaces:
  - Update phpdoc return types to prefer interfaces:
    - `FeaturesManagerAbstract::get_registry(): array<string, FeatureContainerInterface>`
    - `FeatureRegistry::get_registery(): array<string, FeatureContainerInterface>`
- Iterable registry:
  - Make `FeatureRegistry` implement `IteratorAggregate` and return an `ArrayIterator` over the map.
  - Unit test `foreach ($registry as $slug => $container)` preserves keys.
- Helper:
  - Add `FeaturesManagerAbstract::has_feature(string $slug): bool`.

Acceptance:

- Iteration over `FeatureRegistry` works and preserves slug keys.
- phpdoc reflects `FeatureContainerInterface` in both methods.
- `has_feature()` present and covered by tests.
- Docs use “registry” consistently.

### P2 — Accessory discovery hardening (incremental)

- Impact: Medium/Low; better errors, optional extensibility.

Tasks:

- Improve error messages to include expected Manager FQCN and detected interface FQCN, e.g.:
  - “The AccessoryInterface 'X\Y\Z\FooAccessoryInterface' is missing its adjacent Manager 'X\Y\Z\FooAccessoryManager'.”
  - “The Manager class '...' for AccessoryInterface '...' must implement 'AccessoryManagerBaseInterface'.”
- Document naming convention explicitly and note potential future escape-hatch (attribute/metadata map).

Acceptance:

- Tests validate error messaging content.
- Docs clarify the convention and failure modes.

### P2 — Optional init timing toggle (future)

- Impact: Low; niche scenarios.

Tasks:

- Explore a flag to delay `init()` to caller if needed (documented and default stays eager).
- Defer unless an immediate need arises.

Acceptance:

- Not included unless requested; tracked as future enhancement.

## Risk & Mitigations

- Risk: Changing DI shape will break existing consumers using the nested form.
  - Mitigation: Provide a concise migration guide (update deps to an associative array), emit clear error messages, and bump version appropriately.
- Risk: Tests need WP functions for sanitization.
  - Mitigation: Use `wp-phpunit` and/or minimal shims; focus tests on our logic.

## Rollout

- Version bump in `plugin-lib/composer.json` (e.g., 0.0.11 → 0.1.0) to reflect the breaking change.
- Changelog entries: docs updates, tests added, DI normalization (breaking change), exception cleanup, minor API polish.
- Announce breaking change and migration: deps must be passed as an associative array.

## Acceptance Criteria (summary)

- Updated `readme.md` accurately reflects API and usage.
- New unit tests cover registration/DI/accessories/lifecycle/errors and pass.
- DI accepts associative arrays; exception messages no longer HTML-escape.
- Clearer Accessory errors; typo/naming consistency; iterable registry; interface-typed `get_registry()`; `has_feature()` available.
