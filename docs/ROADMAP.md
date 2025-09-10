# Plugin Library Roadmap

## Overview

This roadmap outlines the development priorities for completing and enhancing the WordPress Plugin Library. The library provides a comprehensive foundation for WordPress plugin development with modular components for asset management, features organization, configuration, and more.

## Current Status

The library is **approximately 75% complete** with core infrastructure in place. The primary focus is on completing existing components, improving documentation, and ensuring comprehensive test coverage.

---

## 🎯 High Priority - Deep project linting

**Status**: 🟡 In Progress
**Priority**: High

We have been using `composer cs` for regular linting, however the more indepth and correct approach would be to apply `standards:fix` which will adjust the codebase to align with the WordPress coding standards.

This will be a major change that will touch all files in the reopo, so should be done at as a last step before next major release. All active issues should be completed before this step.

## 🎯 High Priority - Project rename to `orbital` or `kepler`?

**Status**: 🟡 In Progress
**Priority**: High

## 🎯 High Priority - EnqueueAccessory System

### ScriptModulesTrait Completion

**Status**: 🟡 Partially Complete
**Priority**: High

- ✅ `ScriptModulesEnqueueTrait` implemented
- ✅ Basic module registration and enqueuing working
- ✅ Module data passing via `script_module_data_{$module_id}` filter
- ✅ Validation and warnings for unsupported features
- ✅ Basic documentation and examples
- ✅ Comprehensive unit tests
- ✅ Integration tests with WordPress
- ✅ Advanced error handling scenarios
- ✅ Documentation and usage examples in TFS-008

### Review Config Class and Options class interaction with options

Config supplies a `get_wp_options()` method that retrieves an options:

_"The `get_wp_options()` method returns the value of the current plugin's primary WordPress option or `false` if none has been set."_

This needs to be reviewed agains our `RegisterOptions` class. Maybe we should provide an instantiated Options instance on demand from the Config class?

### Modify Config class to support both Plugins and Theme headers

**Status**: 🟢 Nearly Complete
**Priority**: High

**Current State:**

- ✅ Basic `ConfigAbstract` and `ConfigInterface` implemented to support plugin headers
- ✅ Standard WordPress headers parsed
- ✅ Custom headers prefixed with `@RAN:` parsed
- ✅ Basic documentation and examples
- ✅ Comprehensive unit tests
- ✅ Integration tests with WordPress
- ✅ Advanced error handling scenarios
- ✅ Documentation and usage examples in TFS-002

**Tasks:**

- [ ] Introduce dual-mode support in `ConfigAbstract` (plugin|theme) or create a `ThemeConfig` alongside existing `Config` (plugin-only)
- [ ] Implement theme data provider
  - [ ] Use `wp_get_theme()` for theme headers/metadata
  - [ ] Use `get_stylesheet_directory()` / `get_stylesheet_directory_uri()` for PATH/URL
- [ ] Unify key normalization and validation across modes
  - [ ] Accept either plugin headers or theme headers
  - [ ] Do not require `RANPluginOption` for themes (plugin-only concern)
  - [ ] Normalize `PluginURI`/`ThemeURI` to a shared key (e.g., `ProjectURI`)
- [ ] Keep `Config.php` (plugin) unchanged; add `ThemeConfig.php` or a small factory to select mode
- [ ] Update documentation to reflect theme support and header expectations
- [ ] Testing
  - [ ] Add unit tests for theme mode/provider
  - [ ] Add integration tests verifying `wp_get_theme()` and theme path helpers

### Update ModifyOptions with crud and validation features

**Status**: 🟢 Done
**Priority**: High

**Current State:**

- ✅ Advanced `ModifyOptions` implemented
- ✅ Comprehensive unit tests
- ✅ Advanced error handling scenarios
- ✅ README documentation
- ✅ Co-located TFS and usage examples

### MediaTrait Completion

**Status**: 🟢 Nearly Complete
**Priority**: High

**Current State:**

- ✅ Basic `MediaEnqueueTrait` structure exists
- ✅ Core `add()` and `stage_media()` methods implemented
- ❌ Missing comprehensive testing
- ❌ Missing integration with `MediaHandler` class
- ❌ Missing documentation and usage examples

**Tasks:**

- [ ] Complete `MediaHandler` class implementation
- [ ] Add comprehensive unit tests for `MediaEnqueueTrait`
- [ ] Create integration tests with WordPress media API
- [ ] Document media asset management patterns
- [ ] Add usage examples for media uploader integration
- [ ] Ensure compatibility with existing asset management architecture

### Block Asset Management

**Status**: 🟢 Nearly Complete
**Priority**: High

**Current State:**

- ✅ `BlockRegistrar` class implemented
- ✅ `BlockAssetTrait` functionality complete
- ✅ WordPress integration patterns established
- ✅ TFS-002 timing documentation complete
- ❌ Block Bindings
- ❌ Block Variations
- ❌ Unit tests
- ❌ Integration tests

**Tasks:**

- [ ] Update `BlockRegistrarTest.php` for composition-based architecture
- [ ] Create comprehensive integration tests
- [ ] Add performance benchmarking for block asset loading
- [ ] Complete usage documentation with real-world examples
- [ ] Validate WordPress 6.4+ compatibility

---

## 🔧 Medium Priority - Core Components

### FeaturesAPI Enhancement

**Status**: 🟡 Functional but Incomplete
**Priority**: Medium

**Current State:**

- ✅ Basic `FeaturesManager` and controller pattern implemented
- ✅ Feature registration and loading working
- ✅ Basic README documentation exists
- ❌ Comprehensive unit tests missing
- ❌ Advanced dependency injection patterns incomplete
- ❌ Integration with other library components needs work

**Tasks:**

- [ ] **Testing**: Create comprehensive unit test suite
  - [ ] Feature registration and loading
  - [ ] Dependency injection scenarios
  - [ ] Error handling and edge cases
- [ ] **Documentation**: Expand beyond basic README
  - [ ] Advanced usage patterns
  - [ ] Integration examples with EnqueueAccessory
  - [ ] Best practices guide
- [ ] **Enhancement**: Advanced dependency management
  - [ ] Circular dependency detection
  - [ ] Conditional feature loading
  - [ ] Feature lifecycle hooks

### Config System Documentation & Testing

**Status**: 🟡 Functional but Under-documented
**Priority**: Medium

**Current State:**

- ✅ `ConfigAbstract` and `ConfigInterface` implemented
- ✅ Basic functionality working
- ❌ Comprehensive documentation missing
- ❌ Unit tests incomplete
- ❌ Usage patterns not well documented

**Tasks:**

- [ ] **Documentation**: Complete API documentation
  - [ ] Configuration patterns and best practices
  - [ ] Integration with other library components
  - [ ] Environment-specific configuration examples
- [ ] **Testing**: Comprehensive unit test coverage
  - [ ] Configuration loading and validation
  - [ ] Logger integration testing
  - [ ] Error handling scenarios
- [ ] **Examples**: Real-world usage scenarios

---

## 🔍 Low Priority - Evaluation & Cleanup

### HooksAccessory Evaluation

**Status**: 🟢 Done
**Priority**: High

**Current State:**

- ✅ `ActionHooksRegistrar` and `FilterHooksRegistrar` implemented
- ✅ `HooksManager` and `HooksManagementTrait` implemented
- ✅ Extensive unit tests
- ✅ Integration tests with enqueue traits, block registrar

### Users Component Evaluation

**Status**: 🟡 Needs Evaluation
**Priority**: Low

**Current State:**

- ✅ `InsertUserDataAbstract` class exists
- ❓ Very specific use case (user insertion)
- ❓ May not belong in a general-purpose library
- ❓ Limited functionality compared to WordPress native functions

**Evaluation Criteria:**

- [ ] **Scope Assessment**: Does this fit library's purpose?
- [ ] **Usage Analysis**: How often is this needed?
- [ ] **Alternative Solutions**: Can this be handled by plugins directly?
- [ ] **Decision**: Keep, enhance, or remove

**Possible Outcomes:**

- **Keep**: If user management is a common plugin need
- **Enhance**: Add more user management utilities
- **Remove**: If it's too specific for a general library

### Dependency Injection as a First-Class Pattern

**Status**: 🟢 Adopted
**Priority**: Ongoing

**Current State:**

- ✅ DI-first approach across `Config`, Enqueue, and Options systems
- ✅ Factory methods for constructing configuration and related objects
- ✅ Documentation and examples updated to reflect DI usage

**Tasks:**

- [ ] Continue refining DI examples and guidance across all components
- [ ] Ensure new features expose clear injection points for configuration and logging

---

## 🎯 Success Metrics

### Code Quality

- [ ] **90%+ test coverage** across all components
- [ ] **Zero critical issues** in static analysis
- [ ] **Comprehensive documentation** for all public APIs

### Developer Experience

- [ ] **Complete usage examples** for all major features
- [ ] **Clear migration guides** for breaking changes
- [ ] **Responsive support** for developer questions

### Performance

- [ ] **Benchmarked asset loading** performance
- [ ] **Memory usage optimization** across components
- [ ] **WordPress compatibility** testing (6.0+)

---

## 📚 Documentation Priorities

1. **API Documentation**: Complete PHPDoc coverage
2. **Usage Guides**: Real-world implementation examples
3. **Architecture Decisions**: TFS documentation for major decisions
4. **Migration Guides**: Upgrade paths for breaking changes
5. **Performance Guidelines**: Best practices for optimization

---

## 🤝 Contributing

This roadmap is a living document. Priorities may shift based on:

- Community feedback and usage patterns
- WordPress core changes and new features
- Performance requirements and optimization opportunities
- Security considerations and best practices evolution

For questions or suggestions about this roadmap, please open an issue or discussion in the project repository.
