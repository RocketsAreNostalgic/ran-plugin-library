<?php
/**
 * Test normalizer message integration functionality.
 */

declare(strict_types=1);

namespace Tests\Unit\Forms\Component\Normalize;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Forms\Component\Normalize\NormalizerBase;
use Ran\PluginLib\Forms\Component\Normalize\ComponentNormalizationContext;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\TranslationService;

/**
 * Test normalizer message integration.
 */
class NormalizerMessageIntegrationTest extends TestCase {
	private ComponentLoader $mockViews;
	private Logger $mockLogger;
	private ComponentNormalizationContext $mockSession;

	protected function setUp(): void {
		$this->mockViews   = $this->createMock(ComponentLoader::class);
		$this->mockLogger  = $this->createMock(Logger::class);
		$this->mockSession = $this->createMock(ComponentNormalizationContext::class);
	}

	/**
	 * Test that validation warnings are properly integrated into component context.
	 */
	public function test_validation_warnings_integration(): void {
		$normalizer = new TestNormalizer($this->mockViews);

		// Mock the session to return empty warnings
		$this->mockSession->method('take_warnings')->willReturn(array());
		$this->mockSession->method('get_logger')->willReturn($this->mockLogger);

		// Mock the views to return a simple payload
		$this->mockViews->method('render_payload')->willReturn(array(
			'markup' => '<input type="text">',
		));

		// Context with validation warnings
		$context = array(
			'_field_id'            => 'test_field',
			'_validation_warnings' => array(
				'This field is required',
				'Value must be at least 5 characters'
			),
			'name'  => 'test_field',
			'value' => 'test'
		);

		$result = $normalizer->render($context, $this->mockSession, 'fields.input');

		// Verify the result structure
		$this->assertIsArray($result);
		$this->assertArrayHasKey('payload', $result);
		$this->assertArrayHasKey('warnings', $result);
	}

	/**
	 * Test that display notices are properly integrated into component context.
	 */
	public function test_display_notices_integration(): void {
		$normalizer = new TestNormalizer($this->mockViews);

		// Mock the session
		$this->mockSession->method('take_warnings')->willReturn(array());
		$this->mockSession->method('get_logger')->willReturn($this->mockLogger);

		// Mock the views to return a simple payload
		$this->mockViews->method('render_payload')->willReturn(array(
			'markup' => '<input type="text">',
		));

		// Context with display notices
		$context = array(
			'_field_id'        => 'test_field',
			'_display_notices' => array(
				'Value was automatically formatted',
				'Whitespace was trimmed'
			),
			'name'  => 'test_field',
			'value' => 'test'
		);

		$result = $normalizer->render($context, $this->mockSession, 'fields.input');

		// Verify the result structure
		$this->assertIsArray($result);
		$this->assertArrayHasKey('payload', $result);
		$this->assertArrayHasKey('warnings', $result);
	}

	/**
	 * Test sanitize_string with notice emission.
	 */
	public function test_sanitize_string_with_notice_emission(): void {
		$normalizer = new TestNormalizer($this->mockViews);
		$notices    = array();

		$emitNotice = function(string $notice) use (&$notices): void {
			$notices[] = $notice;
		};

		// Test string that needs trimming
		$result = $normalizer->testSanitizeString('  test value  ', 'test_field', $emitNotice);

		$this->assertEquals('test value', $result);
		$this->assertCount(1, $notices);
		$this->assertStringContainsString('trimmed', $notices[0]);
	}

	/**
	 * Test sanitize_boolean with notice emission.
	 */
	public function test_sanitize_boolean_with_notice_emission(): void {
		$normalizer = new TestNormalizer($this->mockViews);
		$notices    = array();

		$emitNotice = function(string $notice) use (&$notices): void {
			$notices[] = $notice;
		};

		// Test value that needs conversion
		$result = $normalizer->testSanitizeBoolean('1', 'test_field', $emitNotice);

		$this->assertTrue($result);
		$this->assertCount(1, $notices);
		$this->assertStringContainsString('converted', $notices[0]);
	}

	/**
	 * Test that empty field ID doesn't cause issues.
	 */
	public function test_empty_field_id_handling(): void {
		$normalizer = new TestNormalizer($this->mockViews);

		// Mock the session
		$this->mockSession->method('take_warnings')->willReturn(array());
		$this->mockSession->method('get_logger')->willReturn($this->mockLogger);

		// Mock the views to return a simple payload
		$this->mockViews->method('render_payload')->willReturn(array(
			'markup' => '<input type="text">',
		));

		// Context without field ID
		$context = array(
			'_validation_warnings' => array('Some warning'),
			'name'                 => 'test_field',
			'value'                => 'test'
		);

		$result = $normalizer->render($context, $this->mockSession, 'fields.input');

		// Should not throw an exception
		$this->assertIsArray($result);
		$this->assertArrayHasKey('payload', $result);
	}
}

/**
 * Test normalizer implementation for testing purposes.
 */
class TestNormalizer extends NormalizerBase {
	protected function _normalize_component_specific(array $context): array {
		// Add some test-specific normalization
		if (isset($context['name'])) {
			$context['attributes']['name'] = $context['name'];
		}
		if (isset($context['value'])) {
			$context['attributes']['value'] = $context['value'];
		}
		return $context;
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
}
