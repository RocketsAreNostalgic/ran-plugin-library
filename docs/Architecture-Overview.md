# RAN PluginLib - Architecture Overview

This document provides an overview of the RAN PluginLib architecture after the branch reconciliation completed in January 2025.

## Component Architecture

### Core Utilities (`inc/Util/`)

**Purpose**: Shared utility components used throughout the library.

**Key Components**:

- `Logger.php` - PSR-3 compatible logging system
- `WPWrappersTrait.php` - WordPress function wrappers for testing and abstraction
- `CollectingLogger.php` - Test-specific logger implementation
- `ExpectLogTrait.php` - Testing utilities for log assertions

**Design Pattern**: Utility classes and traits that provide common functionality across different library components.

### Configuration System (`inc/Config/`)

**Purpose**: Plugin configuration management and metadata parsing.

**Key Components**:

- `ConfigAbstract.php` - Base configuration class with singleton pattern
- `ConfigInterface.php` - Configuration contract
- `Config.php` - Concrete implementation

**Integration**: Uses `Logger` from Util for debugging configuration loading.

### Asset Management (`inc/EnqueueAccessory/`)

**Purpose**: Structured WordPress asset (CSS/JS) enqueuing system.

**Key Components**:

- `AssetEnqueueBaseTrait.php` - Core asset management functionality
- `EnqueuePublic.php` / `EnqueueAdmin.php` - Context-specific enqueuers
- `ScriptsEnqueueTrait.php` / `StylesEnqueueTrait.php` - Asset type handlers
- `BlockAssetTrait.php` - WordPress Block Editor integration
- `BlockFactory.php` / `BlockRegistrar.php` - Block registration system

**Dependencies**:

- Uses `WPWrappersTrait` from `Util` for WordPress function abstraction
- Uses `Logger` from `Util` for debugging asset operations

### Hook Management (`inc/HooksAccessory/`)

**Purpose**: Comprehensive WordPress hook registration and management.

**Key Components**:

- `HooksManager.php` - Advanced hook management with deduplication and logging
- `ActionHooksRegistrar.php` / `FilterHooksRegistrar.php` - Specialized registrars
- `ActionHooksInterface.php` / `FilterHooksInterface.php` - Declarative hook contracts
- `HooksManagementTrait.php` - Shared hook functionality

**Dependencies**:

- Uses `WPWrappersTrait` from `Util` for WordPress function abstraction
- Uses `Logger` from `Util` for debugging hook operations
- Implements `AccessoryBaseInterface` from `AccessoryAPI`

### Feature Management (`inc/FeaturesAPI/`)

**Purpose**: Modular plugin feature organization and dependency injection.

**Key Components**:

- `FeaturesManager.php` - Central feature registration and management
- `FeatureControllerAbstract.php` - Base class for plugin features
- `FeatureContainer.php` - Dependency injection container
- `RegistrableFeatureInterface.php` - Feature contract

**Integration**: Integrates with `AccessoryAPI` for automatic accessory registration.

### Accessory System (`inc/AccessoryAPI/`)

**Purpose**: Simplified interface for complex WordPress functionality.

**Key Components**:

- `AccessoryBaseInterface.php` - Base accessory contract
- `AccessoryManagerBaseInterface.php` - Accessory manager contract

**Usage**: Implemented by `HooksAccessory` registrars to provide streamlined WordPress integration.

## Architectural Principles

### 1. Component Separation

- **Clean Boundaries**: No circular dependencies between major components
- **Utility Layer**: Shared functionality isolated in `Util` namespace
- **Interface Segregation**: Components implement specific, focused interfaces

### 2. WordPress Integration

- **Function Wrapping**: All WordPress functions accessed through `WPWrappersTrait`
- **Hook Abstraction**: WordPress hooks managed through dedicated accessory system
- **Testing Support**: Wrapper pattern enables comprehensive unit testing

### 3. Design Patterns

- **Trait Composition**: Shared functionality through traits rather than inheritance
- **Dependency Injection**: Logger and configuration injected where needed
- **Registration Pattern**: Specialized registrars for different WordPress systems
- **Singleton Pattern**: Configuration management through singleton

## Component Dependencies

```
┌─────────────────┐
│   Util/         │ ← Core utilities (Logger, WPWrappersTrait)
└─────────────────┘
         ↑
    ┌────┴────┬─────────────┬─────────────┐
    │         │             │             │
┌───▼───┐ ┌──▼──────┐ ┌────▼─────┐ ┌────▼─────┐
│Config │ │Enqueue  │ │Hooks     │ │Features  │
│       │ │Accessory│ │Accessory │ │API       │
└───────┘ └─────────┘ └──────────┘ └──────────┘
                           ↑             ↑
                      ┌────┴─────┐       │
                      │Accessory │←──────┘
                      │API       │
                      └──────────┘
```

_Last Updated: January 2025 - Post Branch Reconciliation_
