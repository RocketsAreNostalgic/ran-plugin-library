# TFS-001: Public Interface Testing Patterns for WordPress Plugin Development

========

## Status

**Accepted** - 2025-01-28

## Context

This TFS establishes our testing philosophy and patterns for WordPress plugin development, emphasizing robust, maintainable testing practices that focus on behavior verification and real-world usage scenarios.

### Our Testing Goals

- **Maintainable Tests**: Create tests that survive refactoring and implementation changes
- **Behavior Verification**: Focus on what the code does rather than how it does it
- **Bug Discovery**: Design tests that catch real production issues
- **WordPress Integration**: Test realistic WordPress API usage patterns
- **Team Productivity**: Reduce time spent fixing broken tests during development

## Decision

We adopt **Public Interface Testing** as our primary testing philosophy for WordPress plugin development, with the following core principles:

### 1. Test Behavior, Not Implementation

- Test what the code does (behavior) rather than how it does it (implementation)
- Focus on observable outcomes and side effects
- Verify correct integration with WordPress APIs

### 2. Use Public Interfaces for Method Testing

- Test private/protected methods through public methods that call them
- Use integration testing patterns rather than pure unit testing
- Maintain realistic code execution paths

### 3. Strategic Use of Reflection

- Use reflection **only** for test setup (setting internal state)
- **Never** use reflection for method invocation in tests
- Create realistic edge case scenarios for comprehensive testing

### 4. Comprehensive WordPress Integration Testing

- Mock WordPress functions with realistic expectations
- Test actual WordPress API integration patterns
- Verify logging, error handling, and side effects

### 5. Judicious Use of Reflection

- Use reflection **only** for test setup (setting internal state) when necessary
- Avoid `_invoke_protected_method()` for testing private/protected methods
- If reflection-based testing is absolutely required, document the justification clearly
- Consider refactoring the code to make testing through public interfaces possible

## Testing Infrastructure

### Base Test Classes

Our testing framework provides a hierarchical structure of base test classes to support different testing scenarios:

#### PluginLibTestCase

**Purpose**: Base test case for plugin-lib unit tests requiring a ConfigAbstract environment.

**Key Features**:

- Handles WordPress function mocking setup
- Creates mock plugin file and directory structure
- Initializes and registers `ConcreteConfigForTesting` instance
- Provides `CollectingLogger` instance for log verification
- Manages singleton cleanup between tests

**Usage**: Extend this class when your tests need a configured WordPress environment with plugin context.

#### EnqueueTraitTestCase

**Purpose**: Specialized base class for testing enqueue-related traits (Scripts and Styles).

**Key Features**:

- Extends `PluginLibTestCase` with enqueue-specific setup
- Provides abstract methods for concrete class and asset type specification
- Sets up common WordPress enqueue function mocks
- Configures asset URL resolution mocking
- Handles config and logger mock injection

**Usage**: Extend this class when testing asset enqueueing functionality.

### CollectingLogger and Log Verification

#### CollectingLogger Pattern

Our `CollectingLogger` captures log messages during test execution for later verification:

```php
// Logger is automatically available in base test classes
protected ?CollectingLogger $logger_mock = null;
```

#### expectLog() Method

**Critical Pattern**: The `expectLog()` method must be called **after** the system under test has executed, not before:

```php
// ✅ CORRECT: Execute first, then verify logs
public function test_logs_warning_on_failure(): void {
    // Arrange
    $this->setupFailureCondition();

    // Act - Execute the system under test
    $result = $this->instance->performAction();

    // Assert - Verify behavior first
    $this->assertFalse($result);

    // Then verify logs (post-execution verification)
    $this->expectLog('warning', [
        'Action failed',
        'expected context'
    ]);
}

// ❌ INCORRECT: Don't set log expectations before execution
public function test_incorrect_log_pattern(): void {
    // This won't work - logs haven't been generated yet
    $this->expectLog('warning', ['some message']);

    // Act
    $this->instance->performAction();
}
```

**Key Points**:

- **Post-Execution Verification**: Logs are evaluated after the code has run
- **Flexible Matching**: Supports substring matching for robust assertions
- **Multiple Substrings**: Can verify multiple parts of a log message
- **Count Verification**: Can verify exact number of log occurrences

## Implementation Patterns

### ✅ **Recommended Pattern: Public Interface Testing**

```php
/**
 * @test
 * @covers \Namespace\Class::private_method (indirectly through public interface)
 */
public function test_behavior_through_public_interface(): void {
    // Arrange - Set up test state using reflection if needed
    $property = new \ReflectionProperty($this->instance, 'internal_state');
    $property->setAccessible(true);
    $property->setValue($this->instance, $test_data);

    // Mock WordPress functions with realistic expectations
    WP_Mock::userFunction('wp_enqueue_script')
        ->with('test-handle', 'test-src.js', [], '1.0', true)
        ->once()
        ->andReturn(true);

    // Act - Call public method that internally calls private method
    $result = $this->instance->public_method($input);

    // Assert - Verify behavior and side effects
    $this->assertTrue($result);
    $this->expectLog('debug', ['Expected log message']);
}
```

### ❌ **Avoid: Direct Private Method Testing**

```php
// DON'T DO THIS
public function test_private_method_directly(): void {
    $result = $this->_invoke_protected_method(
        $this->instance,
        'private_method',
        $args
    );
    $this->assertEquals($expected, $result);
}
```

### ⚠️ **Exceptions: When Reflection-Based Testing is Appropriate**

While public interface testing is our **preferred approach**, there are **legitimate exceptions** where reflection-based testing of private/protected methods is more appropriate:

#### 1. Pure Utility Methods

**When**: Testing utility methods that perform isolated operations without meaningful public interfaces.

**Examples**:

- `_resolve_url_to_path()` - URL to file path conversion utility
- `_md5_file()` - File hash generation utility
- `_file_exists()` - File existence checking utility
- `_build_attribute_string()` - HTML attribute building utility

**Rationale**: These methods are:

- **Self-contained** with clear inputs and outputs
- **Complex enough** to warrant direct testing
- **Not easily testable** through public interfaces without contrived setups
- **Critical for security/functionality** (e.g., HTML attribute building)

```php
// ✅ ACCEPTABLE: Testing URL resolution utility
// AssetEnqueueBaseTraitCachingTest.php
public function test_resolve_url_to_path_handles_various_url_formats(): void {
    $result = $this->_invoke_protected_method(
        $this->instance,
        '_resolve_url_to_path',
        array('http://example.com/wp-content/plugins/my-plugin/script.js')
    );
    $this->assertSame('/path/to/wp-content/plugins/my-plugin/script.js', $result);
}

// ✅ ACCEPTABLE: Testing cache-busting utility logic
// AssetEnqueueBaseTraitCachingTest.php
public function test_cache_busting_generates_hash_version_when_enabled_and_file_exists(): void {
    $asset_definition = array(
        'handle'     => 'my-script',
        'src'        => 'http://example.com/wp-content/plugins/my-plugin/js/my-script.js',
        'version'    => '1.0.0',
        'cache_bust' => true,
    );

    // Mock file system calls for successful cache-busting
    $this->instance->shouldReceive('_file_exists')->once()->andReturn(true);
    $this->instance->shouldReceive('_md5_file')->once()->andReturn(md5('file content'));

    $actual_version = $this->_invoke_protected_method(
        $this->instance,
        '_generate_asset_version',
        array($asset_definition)
    );

    $this->assertSame(substr(md5('file content'), 0, 10), $actual_version);
}
```

#### 2. Complex Internal Logic Without Public Interfaces

**When**: Testing complex algorithms or logic that don't have direct public method equivalents.

**Examples**:

- `_generate_asset_version()` - Cache busting logic with file system operations
- Complex parsing or transformation methods
- Internal state management methods

**Rationale**:

- **Direct testing is clearer** than forcing integration through complex code paths
- **Easier to test edge cases** without setting up entire execution contexts
- **More maintainable** than complex mock setups for integration testing

```php
// ✅ ACCEPTABLE: Testing cache busting logic
// AssetEnqueueBaseTraitCachingTest.php
public function test_cache_busting_generates_hash_version_when_enabled_and_file_exists(): void {
    // Direct testing of cache busting algorithm is more appropriate
    // than forcing integration through asset processing pipeline
    $actual_version = $this->_invoke_protected_method(
        $this->instance,
        '_generate_asset_version',
        array($asset_definition)
    );
    $this->assertSame($expected_version, $actual_version);
}
```

#### 3. Testing Setup and Internal State

**When**: Using reflection to **set up test conditions** rather than invoke methods.

**Examples**:

- Setting internal properties to create specific test scenarios
- Accessing internal state for assertions after public method calls

**Rationale**: This supports public interface testing by enabling realistic test scenarios.

```php
// ✅ ACCEPTABLE: Using reflection for test setup
public function test_public_behavior_with_internal_state(): void {
    // Arrange - Use reflection to set up test conditions
    $property = new \ReflectionProperty($this->instance, 'internal_state');
    $property->setAccessible(true);
    $property->setValue($this->instance, $test_data);

    // Act - Test through public interface
    $result = $this->instance->public_method();

    // Assert - Verify behavior
    $this->assertTrue($result);
}
```

#### Guidelines for Exception Cases

1. **Document the rationale** - Always include comments explaining why reflection is used
2. **Keep tests focused** - Test one specific aspect of the utility method
3. **Maintain coverage** - Ensure the same logic is also covered by integration tests where possible
4. **Review regularly** - Periodically assess if public interfaces have been added that make refactoring viable

#### Current Exception Examples in Codebase

- **AssetEnqueueBaseTraitCachingTest.php**: 25 reflection-based tests for utility methods and cache busting logic
- **AssetEnqueueBaseTraitCoreTest.php**: 1 utility method test kept for `_build_attribute_string` (security-critical HTML generation)

These exceptions represent **valid use cases** where reflection-based testing provides better clarity, maintainability, and test reliability than forced public interface testing.

### 🎯 **Test Organization Standards**

```php
class ExampleTest extends TestCase {
    // ------------------------------------------------------------------------
    // Method Group Tests (e.g., Asset Processing)
    // ------------------------------------------------------------------------

    /**
     * @test
     * @covers \Namespace\Class::public_method
     * @covers \Namespace\Class::private_method (indirectly)
     */
    public function test_descriptive_behavior_name(): void {
        // Clear AAA pattern: Arrange, Act, Assert
    }

    /**
     * @dataProvider provideTestScenarios
     */
    public function test_parameterized_behavior(
        string $input,
        string $expected
    ): void {
        // Data-driven testing for multiple scenarios
    }

    public function provideTestScenarios(): array {
        return [
            'Scenario 1' => ['input1', 'expected1'],
            'Scenario 2' => ['input2', 'expected2'],
        ];
    }
}
```

### 🔧 **WordPress Integration Patterns**

```php
// Mock WordPress functions realistically
WP_Mock::userFunction('wp_enqueue_script')
    ->with($handle, $src, $deps, $ver, $in_footer)
    ->once()
    ->andReturn(true);

// Test enqueue verification (our discovered pattern)
WP_Mock::userFunction('wp_script_is')
    ->with($handle, 'enqueued')
    ->andReturn(true); // Verify enqueue succeeded

// Test logging behavior
$this->expectLog('warning', [
    'context_info',
    'error_message',
    'handle'
]);
```

### 📊 **Coverage Strategy**

1. **Achieve 100% coverage through public interfaces**
2. **Test both success and failure paths**
3. **Cover edge cases and error conditions**
4. **Verify WordPress API integration**
5. **Test logging and error handling**

## Benefits

### Immediate Benefits

- **Maintainable Tests**: Tests survive refactoring and implementation changes
- **Bug Discovery**: Behavior-focused testing finds real production issues
- **Realistic Testing**: Tests mirror actual usage patterns
- **Reduced Duplication**: Clear ownership of test responsibilities

### Long-term Benefits

- **Confidence in Refactoring**: Tests provide safety net for code changes
- **Documentation**: Tests serve as behavior documentation
- **Onboarding**: New developers understand expected behavior
- **Quality Assurance**: Comprehensive error condition testing

## Consequences

### Positive

- **Higher Quality**: Better bug detection and prevention
- **Maintainability**: Tests remain stable during refactoring
- **Realistic Coverage**: Tests exercise real code paths
- **Team Productivity**: Less time fixing broken tests

### Trade-offs

- **Initial Setup**: May require more initial test setup
- **Learning Curve**: Team needs to understand new patterns
- **Mock Complexity**: WordPress integration requires sophisticated mocking

## Examples from Our Codebase

### Success Story: AssetEnqueueBaseTraitCoreTest.php

- **Before**: 25+ brittle reflection-based tests
- **After**: Robust public interface tests with 100% coverage
- **Result**: Discovered and fixed production bug in `_do_enqueue`
- **Outcome**: 296/296 tests passing, zero maintenance issues

### Pattern Application

```php
// Test enqueue failure handling through public interface
public function test_enqueue_handles_failure_gracefully(): void {
    // Arrange - Set up failing enqueue scenario
    WP_Mock::userFunction('wp_enqueue_script')->andReturn(true);
    WP_Mock::userFunction('wp_script_is')
        ->with('test-handle', 'enqueued')
        ->andReturn(false); // Simulate enqueue failure

    // Act - Use public interface
    $result = $this->instance->enqueue_immediate();

    // Assert - Verify failure handling
    $this->assertFalse($result);
    $this->expectLog('warning', [
        'wp_enqueue_script() failed',
        'test-handle',
        'Asset was registered but not enqueued'
    ]);
}
```

## Compliance

### Required for New Tests

- All new test methods must follow public interface testing patterns
- No direct private/protected method testing via reflection
- Comprehensive WordPress function mocking
- Clear test organization and documentation

### Migration Strategy

- Existing reflection-based tests should be refactored when touched
- Priority refactoring for tests that frequently break
- Document any exceptions with clear justification

### Review Criteria

- Code reviews must verify adherence to these patterns
- Tests must demonstrate behavior verification over implementation testing
- Mock setups must be realistic and maintainable

## References

- **Implementation Example**: `Tests/Unit/EnqueueAccessory/AssetEnqueueBaseTraitCoreTest.php`
- **Refactoring Documentation**: `TEST_REFACTOR_PLAN.md`
- **WordPress Testing Best Practices**: WP_Mock documentation
- **PHPUnit Best Practices**: PHPUnit documentation

---

**Decision Made By**: Development Team
**Date**: 2025-01-28
**Review Date**: 2025-07-28 (6 months)
**Status**: Active Implementation
