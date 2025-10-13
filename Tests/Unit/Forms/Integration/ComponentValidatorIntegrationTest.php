<?php
/**
 * High-level integration test for component validator integration.
 *
 * Tests the complete flow from RegisterOptions schema definition through
 * validation, sanitization, and markup generation.
 */

declare(strict_types=1);

namespace Tests\Unit\Forms\Integration;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;
use Ran\PluginLib\Forms\Component\Normalize\ComponentNormalizationContext;

/**
 * Integration test for component validator system.
 */
class ComponentValidatorIntegrationTest extends PluginLibTestCase {
	private RegisterOptions $options;
	private ComponentLoader $componentLoader;
	private Logger $logger;

	public function setUp(): void {
		parent::setUp();

		// Mock basic WordPress functions that WPWrappersTrait calls
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('get_site_option')->andReturn(array());
		WP_Mock::userFunction('get_blog_option')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());

		// Mock sanitize_key to properly handle key normalization
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function($key) {
			$key = strtolower($key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});

		// Mock esc_attr and esc_html for template rendering
		WP_Mock::userFunction('esc_attr')->andReturnUsing(function($text) {
			return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
		});
		WP_Mock::userFunction('esc_html')->andReturnUsing(function($text) {
			return htmlspecialchars((string) $text, ENT_NOQUOTES, 'UTF-8');
		});
		WP_Mock::userFunction('esc_textarea')->andReturnUsing(function($text) {
			return htmlspecialchars((string) $text, ENT_NOQUOTES, 'UTF-8');
		});

		// Create a RegisterOptions instance using the site factory method
		$this->options = RegisterOptions::site('test_plugin_options');

		// Allow all writes for testing
		$policy = $this->getMockBuilder(\Ran\PluginLib\Options\Policy\WritePolicyInterface::class)->getMock();
		$policy->method('allow')->willReturn(true);
		$this->options->with_policy($policy);

		// Create mock component loader for testing
		$this->componentLoader = $this->createMock(ComponentLoader::class);
		$this->componentLoader->method('render_payload')->willReturn(array(
			'markup' => '<input type="text" name="test_field" value="test_value"><p class="form-message form-message--warning">This field is required</p><p class="form-message form-message--notice">Value was automatically formatted</p>',
		));

		$this->logger = new Logger();
	}

	/**
	 * Test complete flow: schema definition -> validation -> sanitization -> markup generation.
	 */
	public function test_complete_component_validator_flow(): void {
		// Define schema using current format (single validate/sanitize functions)
		$schema = array(
			'test_field' => array(
				'validate' => function($value, callable $emitWarning): bool {
					if (strlen((string) $value) < 3) {
						$emitWarning('Field must be at least 3 characters long');
						return false;
					}
					return true;
				},
				'sanitize' => function($value, callable $emitNotice): string {
					$original = (string) $value;
					$trimmed  = trim($original);
					if ($original !== $trimmed) {
						$emitNotice('Value was trimmed');
					}
					return $trimmed;
				},
				'default' => 'default_value'
			)
		);

		// Register the schema
		$this->options->register_schema($schema);

		// Test validation failure scenario
		$this->options->stage_option('test_field', '  ab  '); // Too short, needs trimming
		$result = $this->options->commit_merge();

		// Should fail validation and not persist
		$this->assertFalse($result);

		// Get messages to verify warning was emitted
		$messages = $this->options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertArrayHasKey('warnings', $messages['test_field']);
		$this->assertNotEmpty($messages['test_field']['warnings']);
		$this->assertStringContainsString('at least 3 characters', $messages['test_field']['warnings'][0]);

		// Test successful validation with sanitization
		$this->options->stage_option('test_field', '  valid_value  '); // Long enough, needs trimming
		$result = $this->options->commit_merge();

		// Should pass validation and persist
		$this->assertTrue($result);

		// Get messages to verify notice was emitted
		$messages = $this->options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertArrayHasKey('notices', $messages['test_field']);
		$this->assertNotEmpty($messages['test_field']['notices']);
		$this->assertStringContainsString('trimmed', $messages['test_field']['notices'][0]);

		// Verify the value was properly sanitized and stored
		$storedValue = $this->options->get_option('test_field');
		$this->assertEquals('valid_value', $storedValue); // Should be trimmed
	}

	/**
	 * Test component markup generation with validation messages.
	 */
	public function test_component_markup_with_validation_messages(): void {
		// Create a test normalizer
		$normalizer = new TestIntegrationNormalizer($this->componentLoader);
		$session    = new ComponentNormalizationContext($this->logger);

		// Context with validation warnings and display notices
		$context = array(
			'_field_id'            => 'test_field',
			'_validation_warnings' => array(
				'This field is required',
				'Value must be valid'
			),
			'_display_notices' => array(
				'Value was automatically formatted',
				'Whitespace was removed'
			),
			'input_type' => 'text',
			'name'       => 'test_field',
			'value'      => 'test_value',
			'id'         => 'test_field_id'
		);

		// Render the component
		$result = $normalizer->render($context, $session, 'fields.input');

		// Verify result structure
		$this->assertIsArray($result);
		$this->assertArrayHasKey('payload', $result);
		$this->assertArrayHasKey('warnings', $result);

		// Verify payload contains markup
		$payload = $result['payload'];
		$this->assertArrayHasKey('markup', $payload);
		$this->assertIsString($payload['markup']);
		$this->assertNotEmpty($payload['markup']);

		// Verify markup contains input element
		$this->assertStringContainsString('<input', $payload['markup']);
		$this->assertStringContainsString('type="text"', $payload['markup']);
		$this->assertStringContainsString('name="test_field"', $payload['markup']);
	}

	/**
	 * Test fluent validator/sanitizer management methods.
	 */
	public function test_fluent_validator_sanitizer_management(): void {
		// Start with basic schema
		$schema = array(
			'test_field' => array(
				'validate' => function($value): bool {
					return true;
				}, // Placeholder validator
				'sanitize' => function($value): string {
					return (string) $value;
				}, // Placeholder sanitizer
				'default' => ''
			)
		);

		$this->options->register_schema($schema);

		// Add validators using fluent methods
		$this->options->prepend_validator('test_field', function($value, callable $emitWarning): bool {
			if (empty($value)) {
				$emitWarning('Field cannot be empty');
				return false;
			}
			return true;
		});

		$this->options->append_validator('test_field', function($value, callable $emitWarning): bool {
			if (strlen((string) $value) > 100) {
				$emitWarning('Field cannot be longer than 100 characters');
				return false;
			}
			return true;
		});

		// Add sanitizers using fluent methods
		$this->options->prepend_sanitizer('test_field', function($value, callable $emitNotice): string {
			$trimmed = trim((string) $value);
			if ($trimmed !== (string) $value) {
				$emitNotice('Leading/trailing whitespace removed');
			}
			return $trimmed;
		});

		$this->options->append_sanitizer('test_field', function($value, callable $emitNotice): string {
			$lowercased = strtolower((string) $value);
			if ($lowercased !== (string) $value) {
				$emitNotice('Value converted to lowercase');
			}
			return $lowercased;
		});

		// Test with valid value that needs sanitization
		$this->options->stage_option('test_field', '  VALID_VALUE  ');
		$result = $this->options->commit_merge();
		$this->assertTrue($result);

		$messages = $this->options->take_messages();
		$this->assertCount(2, $messages['test_field']['notices']); // Should have both sanitization notices

		// Verify final value has both sanitizations applied
		$storedValue = $this->options->get_option('test_field');
		$this->assertEquals('valid_value', $storedValue); // Trimmed and lowercased
	}

	/**
	 * Test fluent validator failure scenarios.
	 */
	public function test_fluent_validator_failure(): void {
		// Start with basic schema
		$schema = array(
			'test_field' => array(
				'validate' => function($value): bool {
					return true;
				}, // Placeholder validator
				'sanitize' => function($value): string {
					return (string) $value;
				}, // Placeholder sanitizer
				'default' => ''
			)
		);

		$this->options->register_schema($schema);

		// Add validator that rejects empty values
		$this->options->prepend_validator('test_field', function($value, callable $emitWarning): bool {
			if (empty($value)) {
				$emitWarning('Field cannot be empty');
				return false;
			}
			return true;
		});

		// Test with empty value (should fail validator)
		$this->options->stage_option('test_field', '');
		$result = $this->options->commit_merge();

		// Should return false and not persist
		$this->assertFalse($result);

		// Should have validation warning
		$messages = $this->options->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertArrayHasKey('warnings', $messages['test_field']);
		$this->assertStringContainsString('cannot be empty', $messages['test_field']['warnings'][0]);
	}

	/**
	 * Test that component validators can be automatically discovered and integrated.
	 */
	public function test_component_validator_discovery(): void {
		// This test would require actual component validators to exist
		// For now, we'll test the structure that would support this

		$schema = array(
			'checkbox_field' => array(
				'component' => 'fields.checkbox',
				'validate'  => function($value): bool {
					return true;
				}, // Placeholder
				'sanitize' => function($value, callable $emitNotice): mixed {
					// Only sanitize valid boolean representations
					if (is_bool($value)) {
						return $value;
					}
					if (in_array($value, array('0', '1', 'on', 'off', 'true', 'false'), true)) {
						$sanitized = in_array($value, array('1', 'on', 'true'), true);
						$emitNotice('Checkbox value converted to boolean');
						return $sanitized;
					}
					// Return original value for invalid inputs - let validator handle it
					return $value;
				},
				'default' => false
			)
		);

		$this->options->register_schema($schema);

		// In a full implementation, this would automatically discover
		// and inject component validators for the checkbox component
		// For now, we'll manually add a checkbox-appropriate validator

		$this->options->prepend_validator('checkbox_field', function($value, callable $emitWarning): bool {
			// Checkbox validator logic
			if (!is_bool($value) && !in_array($value, array('0', '1', 'on', 'off', 'true', 'false'), true)) {
				$emitWarning('Checkbox value must be a valid boolean representation');
				return false;
			}
			return true;
		});

		// Test with invalid checkbox value
		$this->options->stage_option('checkbox_field', 'invalid');
		$result = $this->options->commit_merge();
		$this->assertFalse($result);

		$messages = $this->options->take_messages();
		$this->assertStringContainsString('valid boolean', $messages['checkbox_field']['warnings'][0]);

		// Test with valid checkbox value
		$this->options->stage_option('checkbox_field', 'on');
		$result = $this->options->commit_merge();
		$this->assertTrue($result);
	}

	public function tearDown(): void {
		// Clean up test data
		$this->options->delete_option('test_field');
		$this->options->delete_option('checkbox_field');
	}
}

/**
 * Test normalizer for integration testing.
 */
class TestIntegrationNormalizer extends NormalizerBase {
	protected function _normalize_component_specific(array $context): array {
		// Handle input-specific normalization
		if (isset($context['input_type'])) {
			$context['attributes']['type'] = $context['input_type'];
		}

		if (isset($context['name'])) {
			$context['attributes']['name'] = $context['name'];
		}

		if (isset($context['value'])) {
			$context['attributes']['value'] = $context['value'];
		}

		if (isset($context['id'])) {
			$context['attributes']['id'] = $context['id'];
		}

		// Format attributes for template
		$context['input_attributes'] = $this->_format_attributes($context['attributes']);

		return $context;
	}

	private function _format_attributes(array $attributes): string {
		$parts = array();
		foreach ($attributes as $key => $value) {
			if ($value === null || $value === '') {
				continue;
			}
			$parts[] = sprintf('%s="%s"', esc_attr((string) $key), esc_attr((string) $value));
		}
		return implode(' ', $parts);
	}
}
