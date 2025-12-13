# Contributing to Rockets Are Nostalgic projects

Thank you for your interest in contributing to a Rocket Are Nostalgic project! We welcome contributions from the community and are pleased to have you join us. This document will guide you through our contribution process.

## Table of Contents

- [Contributing to Rockets Are Nostalgic projects](#contributing-to-rockets-are-nostalgic-projects)
  - [Table of Contents](#table-of-contents)
  - [Code of Conduct](#code-of-conduct)
  - [Development Process](#development-process)
    - [Branching Strategy](#branching-strategy)
    - [Commit Guidelines](#commit-guidelines)
    - [Pull Request Process](#pull-request-process)
  - [Coding Standards](#coding-standards)
    - [PHP Guidelines](#php-guidelines)
      - [Basic Principles](#basic-principles)
      - [Naming Conventions](#naming-conventions)
      - [Method Visibility Prefixes](#method-visibility-prefixes)
      - [Static Method Conventions](#static-method-conventions)
      - [Function Guidelines](#function-guidelines)
    - [TypeScript Guidelines](#typescript-guidelines)
      - [Basic Principles](#basic-principles-1)
      - [Naming Conventions](#naming-conventions-1)
      - [Function Guidelines](#function-guidelines-1)
    - [Testing Requirements](#testing-requirements)
    - [Documentation Requirements](#documentation-requirements)
  - [Guidelines](#guidelines)
  - [Questions or Problems?](#questions-or-problems)
  - [License](#license)

## Code of Conduct

We are committed to providing a friendly, safe, and welcoming environment for all contributors. Please read and follow our [Code of Conduct](CODE_OF_CONDUCT.md).

## Development Process

### Branching Strategy

- `main` - Main development branch
- `feature/*` - New features
- `fix/*` - Bug fixes
- `docs/*` - Documentation updates
- `refactor/*` - Code refactoring

### Commit Guidelines

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

Types:

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

Example:

```
feat(logger): add file rotation support

Added automatic file rotation to FileTransport when log file exceeds size limit.
Implements #123
```

### Pull Request Process

1. Create a new branch from `main`
2. Make your changes
3. Update documentation and tests
4. Run the test suite
5. Submit a pull request
6. Address review feedback

## Coding Standards

### PHP Guidelines

#### Basic Principles

- Follow PSR-12 coding standards
- Use strict types (`declare(strict_types=1)`)
- Use early returns for better readability
- Document public APIs with PHPDoc
- Prefer composition over inheritance

#### Naming Conventions

- **PascalCase** for classes, interfaces, traits, and enums
- **snake_case** for public methods, properties, and variables
- **UPPERCASE** for constants
- Use complete words over abbreviations

#### Method Visibility Prefixes

| Visibility | Prefix | Example | Use Case |
|------------|--------|---------|----------|
| Public API | none | `add_field()` | Methods developers should use |
| Internal-but-public | `__` | `__render()` | Callbacks, cross-class access (not part of public API) |
| Protected | `_` | `_validate_input()` | Subclass helpers |
| Private | `_` | `_build_context()` | Internal implementation |

**Why double underscore for internal-but-public?**
Some methods must be `public` for technical reasons (WordPress hook callbacks, cross-class access) but are not intended for direct use by library consumers. The `__` prefix:

- Clearly signals "internal, do not call directly"
- Sorts these methods below the public API in IDE autocomplete (grouped with magic methods like `__construct`)

#### Static Method Conventions

- **Factory methods** (`for*`, `from*`, `create*`) → Keep `camelCase` for readability

  ```php
  StorageContext::forSite()
  StorageContext::forUserId(123)
  Config::fromPluginFile('/path/to/plugin.php')
  ```

- **All other static methods** → Use `snake_case`

  ```php
  Helpers::validate_required($value)
  Validate::infer_type_from_value($value)
  ```

#### Function Guidelines

- Keep functions small and focused
- Use descriptive names that indicate purpose
- Use type declarations for parameters and return values
- Prefer nullable types over default `null` parameters where appropriate

### TypeScript Guidelines

#### Basic Principles

- Follow `.prettier.config.mjs`, `.stylelintrc`, `.eslintrc.config.mjs` settings
- Declare types for all variables and functions
- Avoid using `any`
- Use early returns for better readability
- Document public APIs with JSDoc

#### Naming Conventions

- PascalCase for classes
- camelCase for variables, functions, and methods
- UPPERCASE for constants
- Use complete words over abbreviations

#### Function Guidelines

- Keep functions small and focused
- Use descriptive names that indicate purpose
- Maximum 20 instructions per function
- Use type-safe parameters and return values

### Testing Requirements

- Write unit tests for all public functions
- Use Vitest as the testing framework
- Follow Arrange-Act-Assert pattern
- Maintain 80% or higher code coverage
- Include integration tests for complex features

Example:

```typescript
describe("Logger", () => {
  it("should log messages at correct level", () => {
    // Arrange
    const logger = createDefaultLogger();

    // Act
    logger.info("test message");

    // Assert
    expect(/* ... */);
  });
});
```

### Documentation Requirements

- Add JSDoc/PHPDoc comments for public APIs
- Keep README.md files up to date
- Document breaking changes
- Include usage examples
- Document error cases and handling

## Guidelines

- Maintain backward compatibility for logging interfaces
- Document all supported log levels
- Test transport implementations thoroughly
- Include performance considerations
- Keep utilities focused and single-purpose
- Document type constraints
- Include usage examples
- Test edge cases thoroughly

## Questions or Problems?

- File an issue in the GitHub issue tracker
- Join our Discord community
- Check the documentation

## License

By contributing to Rockets Are Nostalgic, you agree that your contributions will also be licensed under the MIT License.
