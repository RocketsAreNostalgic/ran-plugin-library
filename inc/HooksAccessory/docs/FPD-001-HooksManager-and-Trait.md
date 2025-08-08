# HooksManager & HooksManagementTrait — Feature Planning Document (FPD)

## Document Information

- Status: Planning
- Technical Priority: High
- Dependencies: None

## 1. Technical Vision

- Core Concept: Unify WordPress hook registration via a single owner-scoped manager with deduplication, conditional registration, grouping, and introspection.
- Architectural Approach: Trait-based integration (`HooksManagementTrait`) that memoizes a per-owner `HooksManager`. Continue supporting declarative interfaces for actions/filters.
- Integration Strategy: Gradual migration from `_do_add_action/_do_add_filter` wrappers in places where a manager is present; wrappers remain valid for one-off uses.

## 2. Technical Design

- Key Components:
  - HooksManager: core engine (dynamic, conditional, grouping, stats, debug)
  - HooksManagementTrait: convenience layer + public getters (stats, keys)
  - ActionHooksRegistrar/FilterHooksRegistrar: declarative processors
  - HookDefinition/RegistrationResult: type-safety and results reporting
- Data Flow:
  - Owner class → Trait → Manager → WordPress hooks (via WPWrappersTrait)
- API Design:
  - Trait: `_register_action|filter`, `_register_*_method`, conditional, bulk, group, admin/frontend/universal helpers
  - Manager: `register_action|filter`, `register_method_hook`, `register_conditional_hooks`, `register_hook_group`, `remove_hook`, `get_stats`, `get_registered_hooks`

## 3. Implementation Strategy

- Core Classes: Already implemented (see files under `inc/HooksAccessory/`).
- Integration Points: Enqueue traits/classes, BlockRegistrar, and future features.
- WordPress Integration: Uses WP wrappers; maintains compatibility with WP_Mock in tests.

## 4. Technical Considerations

- Performance: Deduplication avoids repeated registrations; memoized manager limits overhead.
- Security: No privilege changes; normal WP hook constraints apply.
- Compatibility: PHP 8.1+, WordPress hooks; tests via PHPUnit + WP_Mock + Mockery.

## 5. Alternative Approaches

- Direct wrapper usage only: simpler but lacks deduplication, grouping, stats.
- Global singleton manager: simpler wiring but worse ownership semantics and testing ergonomics.
- Recommended: Owner-scoped manager via trait (current design).

## 6. Implementation Phases

- Phase 1: Introduce manager/trait and migrate simple hooks (done)
- Phase 2: Refactor enqueue traits and BlockRegistrar (done/ongoing)
- Phase 3: Integrate conditional/bulk patterns where appropriate (selective)

## 7. Questions & Uncertainties

- Where to keep wrapper usage? Answer: retained for one-off registrations where manager isn’t in place.
- Dev-mode logging: Gate via Config in owner code; manager remains config-agnostic.
