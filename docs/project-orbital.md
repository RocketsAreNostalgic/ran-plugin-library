# Project Orbital Migration Plan

## Overview

This document outlines the complete migration plan for renaming the `ran-plugin-library` project to `orbital`. This migration involves updating the GitHub repository, package identity, and all references while preserving git history and maintaining functionality.

## Current State Analysis

- **Current Package Name**: `ran/plugin-lib`
- **Current Repository**: `RocketsAreNostalgic/ran-plugin-library`
- **Current Description**: "RocketsAreNostalgic: A shared library of plugin classes to interact with WordPress"
- **Current Version**: 0.0.8
- **Git History**: Must be preserved during migration

## Migration Strategy

### Phase 1: Repository Preparation

**Goal**: Prepare the current repository for renaming

#### 1.1 Backup Current State

```bash
# Create a backup branch before any changes
git checkout -b backup-pre-orbital-migration
git push origin backup-pre-orbital-migration
```

#### 1.2 Document Current Dependencies

```bash
# List all projects that depend on this package
composer show --tree
# Document any projects using this as a dependency
```

### Phase 2: GitHub Repository Migration

**Goal**: Rename the GitHub repository and update settings

#### 2.1 Rename GitHub Repository

**Manual Steps** (GitHub Web Interface):

1. Go to repository Settings → General
2. Scroll to "Repository name" section
3. Change from `ran-plugin-library` to `orbital`
4. Confirm the rename

**Expected Result**: Repository URL changes from:

- `https://github.com/RocketsAreNostalgic/ran-plugin-library`
- To: `https://github.com/RocketsAreNostalgic/orbital`

#### 2.2 Update Repository Description

**Manual Steps** (GitHub Web Interface):

1. Go to repository main page
2. Click the gear icon next to "About"
3. Update description to: "Orbital: A comprehensive toolkit for WordPress themes and plugins"
4. Update topics/tags to include: `wordpress`, `toolkit`, `orbital`, `themes`, `plugins`

#### 2.3 Update Remote URLs

```bash
# Update the remote URL to match new repository name
git remote set-url origin git@github.com:RocketsAreNostalgic/orbital.git

# Verify the change
git remote -v
```

### Phase 3: Package Identity Migration

**Goal**: Update all package metadata and references

#### 3.1 Update composer.json

```bash
# Update package name and metadata
# This will be done via file editing - see detailed changes below
```

**Required Changes in composer.json**:

- `"name": "ran/plugin-lib"` → `"name": "ran/orbital"`
- `"description"` → Update to reflect new identity
- Repository URLs in `repositories` section
- Support URLs in `support` section

#### 3.2 Update README.md

```bash
# Update all references to old name
# Update installation instructions
# Update examples and documentation
```

#### 3.3 Update Documentation Files

```bash
# Update CONTRIBUTING.md
# Update any other docs/ files
# Update code examples
```

### Phase 4: Code and Configuration Updates

**Goal**: Update internal references and configurations

#### 4.1 Search and Replace Operations

```bash
# Find all references to old names
grep -r "plugin-lib" . --exclude-dir=vendor --exclude-dir=.git
grep -r "ran-plugin-library" . --exclude-dir=vendor --exclude-dir=.git

# Update namespace references if any
grep -r "PluginLib" . --exclude-dir=vendor --exclude-dir=.git
```

#### 4.2 Update Configuration Files

- `.phpcs.xml` - Update any project-specific references
- `phpunit.xml` - Update test suite names if needed
- `.vscode/` settings - Update workspace references
- `ran-php-generic.code-workspace` - Update workspace name and paths

### Phase 5: Version and Release Management

**Goal**: Create a clean release with the new identity

#### 5.1 Version Bump Strategy

```bash
# Option A: Minor version bump (recommended)
# 0.0.8 → 0.1.0 (signifies the identity change)

# Option B: Patch version bump
# 0.0.8 → 0.0.9 (minimal change approach)
```

#### 5.2 Create Migration Release

```bash
# Commit all changes
git add .
git commit -m "feat: migrate project identity to 'orbital'

- Rename package from ran/plugin-lib to ran/orbital
- Update repository references and documentation
- Preserve all functionality and git history
- Version bump to 0.1.0 to mark identity change"

# Tag the release
git tag -a v0.1.0 -m "Release 0.1.0: Project renamed to Orbital"

# Push changes and tags
git push origin main
git push origin v0.1.0
```

### Phase 6: Dependency Management

**Goal**: Handle existing installations and dependencies

#### 6.1 Backward Compatibility Considerations

- **Git history**: Fully preserved (no impact)
- **Existing installations**: Will continue to work until updated
- **Composer cache**: May need clearing on dependent projects

#### 6.2 Update Dependent Projects

```bash
# For each project using ran/plugin-lib:
# 1. Update composer.json to use ran/orbital
# 2. Run composer update
# 3. Update any import statements if needed
```

### Phase 7: Communication and Documentation

**Goal**: Inform users and update external references

#### 7.1 Create Migration Notice

- Add notice to old repository (if keeping redirect)
- Update any external documentation
- Notify team members and collaborators

#### 7.2 Update Package Registries

- Packagist will automatically detect the rename
- Update any private package registries if used

## Risk Assessment and Mitigation

### Low Risk Items

- **Git history**: Completely preserved during GitHub rename
- **Existing clones**: Will continue to work, remote URLs update automatically
- **Packagist**: Automatically handles repository renames

### Medium Risk Items

- **Dependent projects**: Need manual updates to use new package name
- **CI/CD pipelines**: May need updates if they reference old names
- **Documentation links**: External links to old repository name will break

### Mitigation Strategies

1. **Backup branch**: Created before any changes
2. **Version bump**: Signals the change to users
3. **Comprehensive testing**: Full test suite run after changes
4. **Staged rollout**: Update internal projects first, then external

## Validation Checklist

### Pre-Migration Validation

- [ ] All tests passing on current version
- [ ] No uncommitted changes
- [ ] Backup branch created
- [ ] Dependencies documented

### Post-Migration Validation

- [ ] Repository successfully renamed on GitHub
- [ ] All remote URLs updated
- [ ] composer.json validates successfully
- [ ] All tests still passing
- [ ] Package installable via Composer
- [ ] Documentation updated and accurate
- [ ] Version tagged and released

### Integration Testing

- [ ] Install package in test project using new name
- [ ] Verify all functionality works as expected
- [ ] Test autoloading and namespaces
- [ ] Verify no broken references

## Rollback Plan

If issues arise during migration:

```bash
# 1. Revert to backup branch
git checkout backup-pre-orbital-migration
git checkout -b rollback-orbital-migration

# 2. Rename repository back on GitHub (manual)
# 3. Update remote URLs back to original
git remote set-url origin git@github.com:RocketsAreNostalgic/ran-plugin-library.git

# 4. Force push if necessary (use with caution)
git push origin main --force
```

## Timeline Estimate

- **Phase 1-2**: 30 minutes (backup and GitHub rename)
- **Phase 3-4**: 1-2 hours (file updates and testing)
- **Phase 5**: 30 minutes (version and release)
- **Phase 6-7**: Variable (depends on number of dependent projects)

**Total estimated time**: 2-4 hours for core migration, plus time for dependent project updates.

## Notes

- This migration preserves all git history and functionality
- The package name change requires updates in dependent projects
- GitHub automatically redirects old repository URLs for a period
- Packagist will automatically track the renamed repository
- Consider this an opportunity to also update documentation and examples

## Next Steps

1. Review this plan with team
2. Schedule migration window
3. Execute phases in order
4. Update dependent projects
5. Monitor for any issues post-migration
