# Plan: Deep Project Linting (WordPress Standards)

- Area: `plugin-lib/` (cross-cutting, affects most files)
- Related: `docs/ROADMAP.md`, `scripts/`, `.phpcs.xml`, `composer.json`
- Status: Planned
- Owner: Core maintainers
- Last updated: 2025-09-10

## Objective

Bring the entire codebase into alignment with WordPress coding standards using a deep, auto-fixing pass (e.g., `standards:fix`). Execute safely with minimal disruption, clear batching, and strong verification.

## Key Risks & Mitigations

- **Massive diff/merge conflicts**
  - Mitigation: Use a dedicated branch and small, directory-scoped PRs.
- **Tooling instability (PHPCS parallel, paths with spaces, installed_paths)**
  - Mitigation: Disable PHPCS parallel, use absolute paths in `.phpcs.xml`, align custom runner scripts.
- **Behavioral regressions**
  - Mitigation: Run full PHPUnit suite after each batch; keep changes strictly mechanical (no refactors).
- **Inconsistent standards across sibling plugins**
  - Mitigation: Capture a shared baseline and optionally apply cross-repo after this library is stable.

## Baselines & Constraints

- Run tests for this library from its own root: `vendor/ran/plugin-lib/` using `composer test`.
- Known env quirks:
  - Disable PHPCS parallel to avoid fatal in `LocalFile.php`.
  - Project path contains spaces (`/Users/.../Local Sites/...`); use absolute paths in `.phpcs.xml` `<file>` and `<exclude-pattern>`.
  - Ensure `.phpcs.xml` `installed_paths` is correct; avoid mutation via custom runner logic; fix ruleset instead.

## Phased Execution Plan

### Phase 0 — Branching and Tool Pinning

- [ ] Create long‑lived branch `chore/standards-fix` for this effort.
- [ ] Pin versions in `composer.json` to known-good:
  - [ ] `squizlabs/php_codesniffer`
  - [ ] any fixer tool used (e.g., `php-cs-fixer` if applicable)
- [ ] Commit lockfile updates.

### Phase 1 — Tooling Alignment & Dry Runs

- [ ] Update `.phpcs.xml`:
  - [ ] Ensure PHPCS parallel is disabled.
  - [ ] Convert `<file>` and `<exclude-pattern>` directives to absolute paths.
  - [ ] Verify `installed_paths` correctness for WordPress standards.
- [ ] Verify custom runner scripts in `scripts/` do not rewrite `installed_paths`; prefer correctness in `.phpcs.xml`.
- [ ] Run non-fixing lint in library root:
  - [ ] `composer cs` (or equivalent) to collect baseline violations.
  - [ ] Capture report artifact in CI (optional).

### Phase 2 — Low-Risk Auto-Fixes (Batch 1)

Apply safe fixers first to minimize risk and reduce noise.

- [ ] Create PR: `batch-01-inc-core-formatting`
  - [ ] Scope: `inc/` (non-docblock, whitespace, punctuation, basic formatting)
  - [ ] Run auto-fixer (non-destructive rules only)
  - [ ] Run `composer test` (in `vendor/ran/plugin-lib/`)
  - [ ] Review & merge
- [ ] Create PR: `batch-02-tests-formatting`
  - [ ] Scope: `Tests/` (formatting, whitespace)
  - [ ] Run `composer test`
  - [ ] Review & merge
- [ ] Create PR: `batch-03-scripts-formatting`
  - [ ] Scope: `scripts/` and tooling configs, avoid behavior changes
  - [ ] Validate custom runners still work

### Phase 3 — Higher-Risk Auto-Fixes (Batch 2)

Docblocks, headers, strict types declarations where already used, and other changes that can affect reflection or test expectations.

- [ ] Create PR: `batch-04-inc-docblocks`
  - [ ] Scope: Docblock normalization, missing param/return descriptions
  - [ ] Validate tests that assert on log strings or reflection helpers
- [ ] Create PR: `batch-05-strict-types-pass`
  - [ ] Scope: Introduce/normalize `declare(strict_types=1);` only where already established pattern exists
  - [ ] Do NOT change signatures or visibility; keep changes mechanical
- [ ] After each PR:
  - [ ] Run `composer test` (full suite)
  - [ ] Ensure no behavior/log message changes without corresponding, justified test updates

### Phase 4 — CI & Pre-commit Enforcement

- [ ] Update `scripts/git-hooks/pre-commit` to run lint checks (non-fixing) for staged files.
- [ ] In CI:
  - [ ] Run `phpcs` and (if used) `php-cs-fixer --dry-run`.
  - [ ] Publish reports as artifacts.
- [ ] Document local commands in `README.md`.

### Phase 5 — Documentation

- [ ] Update `README.md` (library root) with a "How to lint" section:
  - [ ] Commands (`composer cs`, `composer standards:fix`), where to run them
  - [ ] Environment notes (no parallel, absolute paths)
- [ ] Update `docs/ROADMAP.md` high-priority item to link to this plan.

### Phase 6 — Optional Cross-Repo Alignment

- [ ] Capture a shared standards baseline for sibling plugins:
  - [ ] `venues-bookings`, `tr-site-functionality`, `tny-google-key`, `tny-signature`, `plugin-lib`, `ran-starter-plugin`
- [ ] Consider extracting shared configs or referencing `ran-wp-configs` where applicable.
- [ ] Apply the same phased approach repo-by-repo.

## Acceptance Criteria

- [ ] All batches merged to `main` without regressions.
- [ ] `composer test` passes in `vendor/ran/plugin-lib/` after each batch and on `main`.
- [ ] CI enforces standards and blocks non-conforming code.
- [ ] Documentation clearly describes lint/fix workflows and environment specifics.

## Operational Notes

- Keep each PR strictly mechanical; do not mix refactors.
- Expect logger string expectations in tests to be sensitive to punctuation/spacing—avoid changing log content.
- Prefer many small PRs over a single large PR to reduce review time and risk.

## Reference Commands (examples)

```sh
# From library root
cd "vendor/ran/plugin-lib"  # (Illustrative; run from IDE/terminal without cd in scripts)

# Lint (non-fixing)
composer cs

# Full test suite
composer test

# PHPCS directly (if needed) with explicit standard
./vendor/bin/phpcs --standard="/absolute/path/to/.phpcs.xml" -vvv
```
