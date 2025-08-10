### PRD: Features API Enhancements (Docs, Tests, DX, Robustness)

- Area: `plugin-lib/inc/FeaturesAPI/*`
- Related docs: `inc/FeaturesAPI/readme.md`, `inc/FeaturesAPI/docs/ARD-FeaturesAPI-Enhancements.md`
- Date: 2025-08-09

## Overview

- **What**: Improve the Features API ergonomics, documentation accuracy, test coverage, and runtime robustness. Key areas: documentation alignment, unit tests, dependency injection normalization, exception hygiene, naming/API polish, and Accessory discovery hardening.
- **Who**: Plugin developers integrating features via `FeaturesManager`, `FeatureContainer`, and `FeatureController` classes.
- **Why**: Reduce onboarding friction, prevent subtle runtime errors, and provide predictable behavior under error conditions.

## Core Features

- **Documentation alignment**

  - Ensures `readme.md` reflects actual signatures and lifecycle.
  - Adds accurate dependency injection guidance and failure modes.
  - High-level lifecycle diagram and examples with FQCN usage.

- **Unit test suite**

  - Covers registration, slug handling, instantiation guards, dependency injection, Accessory wiring, lifecycle, and registry access.
  - Provides safety net for refactors and bug fixes.

- **Dependency injection (DI) normalization (breaking change)**

  - Accept associative deps only: `['propName' => $value]`.
  - Remove support for the legacy nested deps shape.

- **Exception message hygiene**

  - Remove `esc_html()` from exceptions; use clear, raw messages.
  - Improves CLI/dev logs and decouples from WP context.

- **Naming and minor API polish**

  - Prefer “registry” spelling in public docs.
  - Typed returns favoring `FeatureContainerInterface` in phpdoc.
  - Make `FeatureRegistry` iterable; add `has_feature($slug)` helper.

- **Accessory discovery hardening**

  - Clear, actionable errors including expected Manager FQCN and detected interface FQCN.
  - Document convention and note potential future attribute/metadata map.

- **Optional init timing toggle (future)**
  - Explore opt-in control to delay `init()`; default remains eager.

## User Experience

- **Developer DX**
  - Accurate docs and examples reduce confusion and time-to-first-feature.
  - Clear error messages shorten debugging cycles.
  - Iterable registry and helpers improve discoverability and ergonomics.

## Technical Architecture

- **Current components**

  - `FeaturesManagerAbstract`: registers features, loads them, injects deps, enables accessories, calls `init()`.
  - `FeatureRegistry`: stores `slug` → `FeatureContainer` map.
  - `FeatureContainer`/`FeatureContainerInterface`/`FeatureContainerAbstract`: hold `qualified_classname`, `slug`, `deps`, and instance.
  - `FeatureControllerAbstract` + `RegistrableFeatureInterface`: base and contract for features.

- **DI normalization**

  - New accepted deps shape: associative array `array<string, mixed>` (breaking change).
  - Update `_set_instance_dependencies()` to iterate property names directly.

- **Exception hygiene**

  - Replace `esc_html()` usage; keep `sprintf()` with quoted identifiers.

- **API polish**

  - Phpdoc for `get_registry()` methods returns `array<string, FeatureContainerInterface>`.
  - `FeatureRegistry` implements `IteratorAggregate` to yield `slug => FeatureContainerInterface`.
  - Add `FeaturesManagerAbstract::has_feature(string $slug): bool`.

- **Accessory discovery hardening**

  - Keep convention-based manager resolution (Interface → Manager).
  - Improve error messages with expected Manager FQCN and interface FQCN.
  - Document naming convention; note future attribute/metadata map as an option.

- **External dependencies / integrations**
  - WordPress functions used: `sanitize_title()` (slug), optional runtime availability considerations.
  - Unit test bootstrap via `wp-phpunit/wp-phpunit` where needed.

## Development Roadmap

- **Phase P0: Documentation + Tests**

  - Align `inc/FeaturesAPI/readme.md` with actual signatures and lifecycle.
  - Add comprehensive unit tests for registration, DI, accessories, lifecycle, and registry.

- **Phase P1: DI + Exceptions + API polish**

  - Implement associative DI (breaking change); remove legacy nested deps support.
  - Remove HTML escaping from exceptions; improve messages.
  - Phpdoc interface-typed returns; iterable registry; `has_feature()` helper.

- **Phase P2: Accessory hardening (+ optional init flag exploration)**
  - Improve error messages and document convention.
  - Consider (document-only) future attribute/metadata map.
  - Explore optional init timing flag (no change by default).

## Logical Dependency Chain

1. **Docs alignment (P0)** → required to prevent misuse and guide tests.
2. **Unit tests (P0)** → foundation to safely refactor DI and errors.
3. **DI normalization (P1)** → depends on tests; introduces a breaking change.
4. **Exception hygiene (P1)** → low coupling, but validated by tests.
5. **API polish (P1)** → doc + tests update; minimal code impact.
6. **Accessory hardening (P2)** → incremental improvements after baseline is stable.
7. **Optional init timing (P2, future)** → only if a concrete need arises.

## Risks and Mitigations

- **DI shape change will break consumers using the legacy nested shape**

  - Mitigation: Provide a concise migration guide (convert deps to associative array), emit clear error messages, and bump version appropriately.

- **WP function dependencies in tests**

  - Mitigation: Use `wp-phpunit` bootstrap or stub minimal functions; scope tests to library logic where possible.

- **Accessory naming convention brittleness**
  - Mitigation: Improve error messages now; document future attribute/metadata map as an escape hatch.

## Appendix

- **Acceptance criteria (summary)**

  - `readme.md` is accurate (signatures, lifecycle, DI examples, failure modes).
  - Unit tests cover registration/DI/accessories/lifecycle/errors and pass.
  - DI accepts associative arrays only; legacy nested shape is rejected with clear error messages.
  - Exceptions no longer HTML-escape; messages are clear and actionable.
  - `FeatureRegistry` is iterable; `get_registry()` phpdoc references `FeatureContainerInterface`.
  - `FeaturesManagerAbstract::has_feature()` exists with tests.
  - Accessory error messages include expected Manager FQCN and interface FQCN; convention documented.

- **References**
  - ADR: `inc/FeaturesAPI/docs/ARD-FeaturesAPI-Enhancements.md`
  - Template: `.taskmaster/templates/example_prd.txt`
