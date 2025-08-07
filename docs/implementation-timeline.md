# Implementation Timeline - Branch Reconciliation

**Date:** January 15, 2025
**Task:** 17.5 - Create implementation timeline
**Purpose:** Detailed timeline for executing the reconciliation strategy

## Overview

This timeline outlines the step-by-step implementation of the branch reconciliation strategy, incorporating the compromise approach to minimize conflicts while preserving all functionality from both development streams.

## Pre-Implementation Checklist

### **Prerequisites Completed ✅**

- [x] Task 1: Created `feat/hooks-reconciliation` branch from `feat/hooks`
- [x] Task 2: Documented current state of both branches
- [x] Task 17: Analyzed parallel development streams
- [x] Compromise strategy defined and documented

### **Ready to Proceed**

- [x] All analysis documents created
- [x] Reconciliation strategy finalized
- [x] Implementation steps clearly defined

## Phase 1: Core Reconciliation Implementation

### **Step 1: Prepare Working Environment** (5 minutes)

**Current Branch:** `main`
**Target:** Set up for reconciliation work

```bash
# Switch to reconciliation branch
git checkout feat/hooks-reconciliation

# Verify starting point
git log --oneline -3
git status
```

**Expected Result:** Clean working directory on `feat/hooks-reconciliation` with all feat/hooks changes

### **Step 2: Implement File Location Compromise** (10 minutes)

**Current State:** WPWrappersTrait.php in `inc/Util/` (from feat/hooks)
**Target:** Move to compromise location in `inc/EnqueueAccessory/`

```bash
# Move file to compromise location
git mv inc/Util/WPWrappersTrait.php inc/EnqueueAccessory/WPWrappersTrait.php

# Update imports in EnqueueAccessory files
# Files to update:
# - inc/EnqueueAccessory/MediaEnqueueTrait.php
# - inc/EnqueueAccessory/AssetEnqueueBaseTrait.php
# - inc/EnqueueAccessory/EnqueuePublic.php
# - inc/EnqueueAccessory/ScriptsEnqueueTrait.php

# Change: use inc\Util\WPWrappersTrait;
# To:     use inc\EnqueueAccessory\WPWrappersTrait;
```

**Validation:**

- [ ] WPWrappersTrait.php in `inc/EnqueueAccessory/` location
- [ ] All EnqueueAccessory imports updated
- [ ] All 7 wrapper methods preserved in file

### **Step 3: Apply Stashed Changes** (15 minutes)

**Current State:** feat/hooks-reconciliation with compromise setup
**Target:** Apply main branch stash with HooksAccessory overhaul

```bash
# Switch to main to access stash
git checkout main

# Apply stash to reconciliation branch
git checkout feat/hooks-reconciliation
git stash apply stash@{0}

# Expected conflicts:
# - WPWrappersTrait.php location (already resolved by compromise)
# - Import paths (will need manual resolution)
```

**Manual Conflict Resolution:**

1. **WPWrappersTrait.php conflicts:** Keep the file in `inc/EnqueueAccessory/` with all 7 methods
2. **Import statement conflicts:** Ensure new HooksAccessory classes import from EnqueueAccessory location
3. **File removals:** Confirm old Manager classes are removed, new Registrar classes added

**Expected Changes Applied:**

- [ ] `inc/HooksAccessory/ActionHooksManager.php` removed
- [ ] `inc/HooksAccessory/FilterHooksManager.php` removed
- [ ] `inc/HooksAccessory/ActionHooksRegistrar.php` added (+148 lines)
- [ ] `inc/HooksAccessory/FilterHooksRegistrar.php` added (+153 lines)
- [ ] `inc/HooksAccessory/EnhancedHooksManager.php` added (+666 lines)
- [ ] Interface files enhanced (+160 lines total)
- [ ] Complete test suite added (+4,641 lines)

### **Step 4: Resolve Import Conflicts** (10 minutes)

**Target:** Ensure all new HooksAccessory classes can import WPWrappersTrait

**Files to Check/Update:**

1. `inc/HooksAccessory/ActionHooksRegistrar.php`

   - Ensure: `use inc\EnqueueAccessory\WPWrappersTrait;`

2. `inc/HooksAccessory/FilterHooksRegistrar.php`

   - Ensure: `use inc\EnqueueAccessory\WPWrappersTrait;`

3. Any other files that use WPWrappersTrait from stash

**Validation Commands:**

```bash
# Check for import statements
grep -r "use.*WPWrappersTrait" inc/HooksAccessory/

# Verify no broken imports
grep -r "use inc\\\\Util\\\\WPWrappersTrait" inc/
```

### **Step 5: Validate Integration** (10 minutes)

**Target:** Confirm all components can access wrapper methods

**Test Commands:**

```bash
# Check WPWrappersTrait methods are available
grep -n "function _do_" inc/EnqueueAccessory/WPWrappersTrait.php

# Verify HooksAccessory classes use the trait
grep -r "WPWrappersTrait" inc/HooksAccessory/

# Check for any remaining references to old location
grep -r "inc\\\\Util\\\\WPWrappersTrait" inc/
```

**Expected Results:**

- [ ] All 7 wrapper methods present in EnqueueAccessory location
- [ ] HooksAccessory Registrar classes import WPWrappersTrait successfully
- [ ] No broken import references

### **Step 6: Run Test Suite** (15 minutes)

**Target:** Validate all functionality works correctly

```bash
# Run existing tests (should pass - EnqueueAccessory functionality preserved)
composer test

# Check for any test failures related to:
# - WPWrappersTrait location changes
# - HooksAccessory integration
# - Import path issues
```

**Expected Results:**

- [ ] All existing tests pass
- [ ] New comprehensive test suite runs successfully
- [ ] No import or dependency errors

## Phase 2: Final Validation and Cleanup

### **Step 7: File Naming Reconciliation** (5 minutes)

**Target:** Ensure Manager→Registrar naming is complete

**Verification:**

```bash
# Confirm old Manager classes are gone
ls inc/HooksAccessory/*Manager.php 2>/dev/null || echo "Old managers removed ✓"

# Confirm new Registrar classes exist
ls inc/HooksAccessory/*Registrar.php

# Confirm EnhancedHooksManager exists
ls inc/HooksAccessory/EnhancedHooksManager.php
```

**Expected Results:**

- [ ] No old Manager classes remain
- [ ] ActionHooksRegistrar.php present
- [ ] FilterHooksRegistrar.php present
- [ ] EnhancedHooksManager.php present

### **Step 8: Documentation Update** (5 minutes)

**Target:** Update any documentation referring to old class names

```bash
# Search for references to old Manager classes
grep -r "ActionHooksManager\|FilterHooksManager" inc/ docs/ --exclude-dir=.git

# Update any found references to use new Registrar naming
```

### **Step 9: Final Integration Test** (10 minutes)

**Target:** Comprehensive validation of reconciliation success

**Test Scenarios:**

1. **EnqueueAccessory Integration:**

   ```bash
   # Verify wrapper methods work in EnqueueAccessory context
   grep -A 5 "_do_add_action" inc/EnqueueAccessory/AssetEnqueueBaseTrait.php
   ```

2. **HooksAccessory Integration:**

   ```bash
   # Verify new Registrar classes can use wrapper methods
   grep -A 5 "WPWrappersTrait" inc/HooksAccessory/ActionHooksRegistrar.php
   ```

3. **Cross-Component Validation:**
   ```bash
   # Ensure no circular dependencies
   composer test
   ```

**Success Criteria:**

- [ ] All wrapper methods accessible from both component groups
- [ ] New HooksAccessory architecture fully functional
- [ ] No import conflicts or missing dependencies
- [ ] Complete test suite passes

## Phase 3: Commit and Documentation

### **Step 10: Commit Reconciliation** (10 minutes)

**Target:** Create comprehensive commit of reconciliation work

```bash
# Stage all changes
git add .

# Create detailed commit message
git commit -m "feat: reconcile feat/hooks and main branch parallel development

MAJOR CHANGES:
- Apply complete HooksAccessory architectural overhaul from main stash
  - Remove: ActionHooksManager, FilterHooksManager
  - Add: ActionHooksRegistrar (+148 lines), FilterHooksRegistrar (+153 lines)
  - Add: EnhancedHooksManager (+666 lines advanced functionality)
  - Enhance: ActionHooksInterface, FilterHooksInterface (+160 lines)

- Preserve WPWrappersTrait functionality from feat/hooks
  - Keep all 7 wrapper methods: _do_add_action, _do_did_action, _do_add_filter,
    _do_remove_action, _do_remove_filter, _do_execute_action, _do_apply_filter
  - COMPROMISE: Keep in inc/EnqueueAccessory/ location (not optimal inc/Util/)
  - Enable cross-component usage (EnqueueAccessory + HooksAccessory)

- Add comprehensive test coverage (+4,641 lines)
- Resolve all import path conflicts through strategic file location compromise

BREAKING CHANGES:
- HooksAccessory Manager classes renamed to Registrar pattern
- WPWrappersTrait location compromise (temporary architectural debt)

FOLLOW-UP:
- Task 18: Move WPWrappersTrait to optimal inc/Util/ location
- Update all import references for clean architecture"
```

### **Step 11: Update Task Status** (2 minutes)

**Target:** Mark reconciliation tasks complete

```bash
# Mark current task complete
task-master set-status --id=17 --status=done

# Update next task dependencies
task-master show 4  # Verify next task is ready
```

## Timeline Summary

| Step | Duration | Cumulative | Task                               |
| ---- | -------- | ---------- | ---------------------------------- |
| 1    | 5 min    | 5 min      | Prepare working environment        |
| 2    | 10 min   | 15 min     | Implement file location compromise |
| 3    | 15 min   | 30 min     | Apply stashed changes              |
| 4    | 10 min   | 40 min     | Resolve import conflicts           |
| 5    | 10 min   | 50 min     | Validate integration               |
| 6    | 15 min   | 65 min     | Run test suite                     |
| 7    | 5 min    | 70 min     | File naming reconciliation         |
| 8    | 5 min    | 75 min     | Documentation update               |
| 9    | 10 min   | 85 min     | Final integration test             |
| 10   | 10 min   | 95 min     | Commit reconciliation              |
| 11   | 2 min    | 97 min     | Update task status                 |

**Total Estimated Time:** ~97 minutes (1 hour 37 minutes)

## Risk Mitigation

### **High-Risk Steps:**

1. **Step 3 (Apply Stash):** Complex merge with potential conflicts

   - **Mitigation:** Compromise strategy eliminates major conflicts
   - **Backup:** Can reset and retry if needed

2. **Step 6 (Test Suite):** May reveal integration issues
   - **Mitigation:** Comprehensive analysis already identified conflicts
   - **Backup:** Detailed troubleshooting steps prepared

### **Rollback Strategy:**

If critical issues arise:

```bash
# Reset to clean state
git checkout feat/hooks-reconciliation
git reset --hard HEAD~1  # Remove problematic commit
git clean -fd  # Clean working directory

# Restart from known good state
```

## Success Validation

### **Completion Criteria:**

- [ ] All 7 wrapper methods preserved and accessible
- [ ] Complete HooksAccessory architectural overhaul applied
- [ ] Manager→Registrar naming convention implemented
- [ ] EnhancedHooksManager (666 lines) fully integrated
- [ ] Comprehensive test suite (+4,641 lines) passes
- [ ] No import conflicts or broken dependencies
- [ ] Cross-component integration validated
- [ ] Clean commit with detailed change documentation

### **Next Steps After Completion:**

1. **Task 4:** Apply remaining stashed changes (if any)
2. **Task 16:** Handle any remaining file naming updates
3. **Task 18:** Move WPWrappersTrait to optimal Utils/ location
4. **Task 7:** Final validation and testing

This timeline provides a structured approach to executing the complex reconciliation while minimizing risk and ensuring all functionality is preserved.
