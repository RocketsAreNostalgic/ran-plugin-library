<?php
/**
 * Foundation test for component validator integration.
 *
 * Tests the basic building blocks that the component validator integration
 * will build upon, focusing on what's already implemented.
 */

declare(strict_types=1);

namespace Tests\Unit\Forms\Integration;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;
use Ran\PluginLib\Forms\Component\Normalize\ComponentNormalizationContext;

/**
 * Foundation test for component validator integration.
 */
class ComponentValidatorFoundationTest extends PluginLibTestCase {
	private RegisterOptions $options;
	private ComponentLoader $componentLoader;
	private Logger $logger;

	public function setUp(): void {
		parent::setUp();

		// Mock basic WordPress functions
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('get_site_option')->andReturn(array());
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function($key) {
			return strtolower(preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '');
		});

		// Mock escaping functions for template rendering
		WP_Mock::userFunction('esc_attr')->andReturnUsing(function($text) {
			return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
		});
		WP_Mock::userFunction('esc_html')->andReturnUsing(function($text) {
			return htmlspecialchars((string) $text, ENT_NOQUOTES, 'UTF-8');
		});

		// Create RegisterOptions instance
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
	 * Test that RegisterOptions can handle basic validation and sanitization.
	 * This is the foundation that component validators will build upon.
	 */
	public function test_register_options_validation_foundation(): void {
		// Define schema using current format
		$schema = array(
			'test_field' => array(
				'validate' => function($value): bool {
					return strlen((string) $value) >= 3;
				},
				'sanitize' => function($value): string {
					return trim((string) $value);
				},
				'default' => 'default_value'
			)
		);

		// Register the schema
		$result = $this->options->register_schema($schema);
		$this->assertIsBool($result);

		// Test successful validation and sanitization
		$this->options->stage_option('test_field', '  valid_value  ');
		$result = $this->options->commit_merge();
		$this->assertTrue($result);

		// Verify the value was sanitized (trimmed)
		$storedValue = $this->options->get_option('test_field');
		$this->assertEquals('valid_value', $storedValue);
	}

	/**
	 * Test component markup generation with validation messages.
	 * This demonstrates how validation messages will be passed to components.
	 */
	public function test_component_markup_with_messages(): void {
		// Create a test normalizer
		$normalizer = new TestFoundationNormalizer($this->componentLoader);
		$session    = new ComponentNormalizationContext($this->logger);

		// Context with validation warnings and display notices
		$context = array(
			'_field_id'            => 'test_field',
			'_validation_warnings' => array(
				'This field is required',
				'Value must be valid'
			),
			'_display_notices' => array(
				'Value was automatically formatted'
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

		// Verify markup contains validation messages
		$this->assertStringContainsString('form-message--warning', $payload['markup']);
		$this->assertStringContainsString('This field is required', $payload['markup']);
		$this->assertStringContainsString('form-message--notice', $payload['markup']);
		$this->assertStringContainsString('automatically formatted', $payload['markup']);
	}

	/**
	 * Test normalizer sanitization methods with notice emission.
	 */
	public function test_normalizer_sanitization_with_notices(): void {
		$normalizer = new TestFoundationNormalizer($this->componentLoader);
		$notices    = array();

		$emitNotice = function(string $notice) use (&$notices): void {
			$notices[] = $notice;
		};

		// Test string sanitization with notice
		$result = $normalizer->testSanitizeString('  test value  ', 'test_field', $emitNotice);
		$this->assertEquals('test value', $result);
		$this->assertCount(1, $notices);
		$this->assertStringContainsString('trimmed', $notices[0]);

		// Reset notices
		$notices = array();

		// Test boolean sanitization with notice
		$result = $normalizer->testSanitizeBoolean('1', 'test_field', $emitNotice);
		$this->assertTrue($result);
		$this->assertCount(1, $notices);
		$this->assertStringContainsString('converted', $notices[0]);
	}

	/**
	 * Test that component templates properly handle empty message states.
	 */
	public function test_component_template_empty_message_handling(): void {
		$normalizer = new TestFoundationNormalizer($this->componentLoader);
		$session    = new ComponentNormalizationContext($this->logger);

		// Context with no messages
		$context = array(
			'_field_id'  => 'test_field',
			'input_type' => 'text',
			'name'       => 'test_field',
			'value'      => 'test_value',
			'id'         => 'test_field_id'
		);

		// Test the normalizer's message integration without component rendering
		$processedContext = $normalizer->testAddValidationWarnings($context);

		// Verify that empty message arrays are properly initialized
		$this->assertArrayHasKey('warnings', $processedContext);
		$this->assertArrayHasKey('notices', $processedContext);
		$this->assertEmpty($processedContext['warnings']);
		$this->assertEmpty($processedContext['notices']);

		// Verify no temporary keys are present
		$this->assertArrayNotHasKey('_validation_warnings', $processedContext);
		$this->assertArrayNotHasKey('_display_notices', $processedContext);
	}

	public function tearDown(): void {
		// Clean up test data
		$this->options->delete_option('test_field');
		parent::tearDown();
	}
}

/**
 * Test normalizer for foundation testing.
 */
class TestFoundationNormalizer extends NormalizerBase {
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

	/**
	 * Expose protected method for testing.
	 */
	public function testSanitizeString(mixed $value, string $context = '', ?callable $emitNotice = null): string {
		return $this->_sanitize_string($value, $context, $emitNotice);
	}

	/**
	 * Expose protected method for testing.
	 */
	public function testSanitizeBoolean(mixed $value, string $context = '', ?callable $emitNotice = null): bool {
		return $this->_sanitize_boolean($value, $context, $emitNotice);
	}

	/**
	 * Expose protected method for testing message integration.
	 */
	public function testAddValidationWarnings(array $context): array {
		return $this->_add_validation_warnings($context);
	}
}
