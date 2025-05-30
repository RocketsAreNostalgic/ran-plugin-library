# Contributing to Rockets Are Nostalgic projects

Thank you for your interest in contributing to a Rocket Are Nostalgic project! We welcome contributions from the community and are pleased to have you join us. This document will guide you through our contribution process.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Development Process](#development-process)
  - [Branching Strategy](#branching-strategy)
  - [Commit Guidelines](#commit-guidelines)
  - [Pull Request Process](#pull-request-process)
- [Coding Standards](#coding-standards)
  - [TypeScript Guidelines](#typescript-guidelines)
  - [Testing Requirements](#testing-requirements)
  - [Documentation Requirements](#documentation-requirements)
- [Guidelines](#guidelines)

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

### TypeScript Guidelines

#### Basic Principles

- Follow `.prettier.config.mjs`, `.stylelintrc`, `.eslintrc.config.mjs` settings
- Declare types for all variables and functions
- Avoid using `any`
- Use early returns for better readability
- Document public APIs with JSDoc/PHPDoc

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
