---
description: AdminSettings security + post handler integration plan
---

# AdminSettings Security/Post Handler Integration Plan

## Objective

Document the rationale behind the (now archived) `AdminSettingsSecurityHandler` and `AdminSettingsPostHandler` and contrast it with the current production pipeline so future contributors understand what functionality remains after their removal. As of today:

- **Primary pipeline:** `AdminSettings` registers its grouped option via `register_setting()` and delegates persistence to WordPress’ `options.php` save flow. Capability checks, nonce enforcement, and sanitize callbacks all run through the native Settings API before the payload reaches `_sanitize()`.
- **Validation + messaging:** `_sanitize()` stages submitted values into a temporary `RegisterOptions` instance, captures validation notices via `FormMessageHandler`, and returns either the sanitized payload (success) or the previous stored values (failure). The render path calls `get_effective_values()`/`get_all_messages()` so the UI surfaces warnings/errors on the next page load without bespoke redirect handling.
- **Security surface today:** WordPress core guards the submission (capability + nonce), and our schema sanitizers strip or normalize data before persistence. No custom `admin-post.php` handler is required for correctness or security under the current architecture.
- **Archived handlers:** The security/post handlers—and the multisite wrapper that instantiated them—were intended to replicate a CMB2-style hardened admin-post flow. With those files moved to `/inc/Settings/hold/`, AdminSettings continues to function exactly as before, relying solely on the built-in Settings API.

This section preserves the context for why those classes existed, what gaps they were meant to solve (custom redirects, origin checks, multisite toggles), and why the present implementation remains fully functional and secure without them.

## Current State Summary

- Handlers exist but are only instantiated by the shelved `AdminSettingsMultisiteHandler` (`@codeCoverageIgnore`).
- AdminSettings boots menu/pages and renders forms but still relies on core `options.php` flow without custom admin-post endpoints.
- Templates output generic `<form>` tags, so the nonce/action from the security handler are never exposed.
- UserSettings continues to persist via `commit_merge()` and does not need changes.

## Deliverables

1. AdminSettings wires up security + post handlers during construction/boot.
2. Admin pages emit handler-provided form action + nonce markup.
3. RegisterOptions submission is routed through `handle_form_submission()`.
4. Integration tests cover happy path and failure (nonce/capability/validation) flows with WP_Mock stubs.
5. Documentation/TFS entry updated to reflect new submission pipeline (if required).

## Task Breakdown

| Status | Task                                                                                                                                                                   | Notes                                                                             |
| ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| ☐      | Expose optional constructor params for injecting `AdminSettingsSecurityHandler` and `AdminSettingsPostHandler`; default to new instances sharing AdminSettings logger. | Avoid breaking existing API by making parameters optional with sensible defaults. |
| ☐      | Persist menu metadata (capability + page slug) needed to register admin-post handlers during `boot()`.                                                                 | Ensure we register handlers exactly once per page slug.                           |
| ☐      | Call `post_handler->register_handlers()` for each page slug with resolved capability.                                                                                  | Hooked inside the existing `admin_menu` registration closure.                     |
| ☐      | Provide helper(s) for templates to output `get_form_open_tag()` or equivalent (action URL + nonce field).                                                              | Likely via AdminSettings form session defaults or dedicated renderer helper.      |
| ☐      | Update admin root wrapper template to use the new helper so every form includes the nonce.                                                                             | Keep HTML structure unchanged aside from injected attributes/fields.              |
| ☐      | Ensure save pipeline short-circuits when security handler rejects (nonce/capability/origin) before RegisterOptions staging.                                            | Validated via integration tests.                                                  |
| ☐      | Add integration-style PHPUnit tests covering success, bad nonce, insufficient capability, and validation failure.                                                      | Use WP_Mock to stub WP functions (nonce, redirects, current user, etc.).          |
| ☐      | Update/change docs or ADRs if the submission path changes materially.                                                                                                  | Coordinate with existing documentation owners.                                    |

## Risks & Mitigations

- **Exit during redirects** could break tests: add guard (e.g., injectable callback) or expect WP_Mock to intercept `wp_safe_redirect` to avoid `exit`.
- **Form template churn** may impact existing themes: keep helper output backwards-compatible (hidden fields only).
- **Hook registration duplication**: ensure handlers register per page slug only once and clean up on repeated boots (WP admin init order).

## Open Questions

- Should we support injecting custom redirect logic (for unit tests) or rely purely on mocks?
- Do we need to persist success/error messages in `FormMessageHandler` so they render after redirect? (If so, document expected storage mechanism.)

## Next Steps

1. Review AdminSettings templates to identify minimal injection point for the nonce helper.
2. Draft implementation for handler wiring + helper exposure.
3. Pair on test strategy before touching runtime code to keep regression risk low.
