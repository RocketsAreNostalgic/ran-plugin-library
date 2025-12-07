<?php
/**
 * Tests for Select Normalizer - specifically the options normalization logic.
 *
 * These tests verify that:
 * 1. Simple key-value options format is supported
 * 2. Structured array options format is supported
 * 3. Selected value is properly applied
 * 4. Option groups work correctly
 *
 * Uses reflection to test the private _normalize_options_format method directly,
 * which is appropriate for testing complex internal logic per ADR-001.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\Select;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Components\Fields\Select\Normalizer;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\Normalize\ComponentNormalizationContext;
use Mockery;

final class NormalizerTest extends PluginLibTestCase {
	private Normalizer $normalizer;
	/** @var ComponentNormalizationContext|\Mockery\MockInterface */
	private $session;

	public function setUp(): void {
		parent::setUp();

		// Create mock ComponentLoader
		$views = Mockery::mock(ComponentLoader::class);

		// Create a mock session (ComponentNormalizationContext) that returns formatted attributes
		$this->session = Mockery::mock(ComponentNormalizationContext::class);
		$this->session->shouldReceive('format_attributes')->andReturnUsing(function (array $attrs): string {
			$parts = array();
			foreach ($attrs as $key => $value) {
				if ($value === true) {
					$parts[] = $key;
				} elseif ($value !== false && $value !== null) {
					$parts[] = sprintf('%s="%s"', $key, htmlspecialchars((string) $value, ENT_QUOTES));
				}
			}
			return implode(' ', $parts);
		});
		$this->session->shouldReceive('reset_state')->andReturnNull();
		$this->session->shouldReceive('reserve_id')->andReturnUsing(function (?string $id): string {
			return $id ?? 'generated-id';
		});
		$this->session->shouldReceive('get_logger')->andReturn($this->logger_mock);

		$this->normalizer = new Normalizer($views);

		// Set the session and logger on the normalizer via reflection so private methods can use it
		$this->_set_protected_property_value($this->normalizer, 'session', $this->session);
		$this->_set_protected_property_value($this->normalizer, 'logger', $this->logger_mock);
	}

	/**
	 * Helper to invoke the private _normalize_options_format method
	 */
	private function normalizeOptionsFormat(array $options): array {
		return $this->_invoke_protected_method($this->normalizer, '_normalize_options_format', array($options));
	}

	/**
	 * Helper to invoke the private _build_options method
	 */
	private function buildOptions(array $options, ?string $selectedValue = null): array {
		return $this->_invoke_protected_method($this->normalizer, '_build_options', array($options, $selectedValue));
	}

	// =========================================================================
	// Options Format Normalization Tests
	// =========================================================================

	public function test_simple_key_value_format_is_normalized(): void {
		$options = array(
			'value1' => 'Label 1',
			'value2' => 'Label 2',
			'value3' => 'Label 3',
		);

		$result = $this->normalizeOptionsFormat($options);

		self::assertCount(3, $result);
		self::assertSame('value1', $result[0]['value']);
		self::assertSame('Label 1', $result[0]['label']);
		self::assertSame('value2', $result[1]['value']);
		self::assertSame('Label 2', $result[1]['label']);
		self::assertSame('value3', $result[2]['value']);
		self::assertSame('Label 3', $result[2]['label']);
	}

	public function test_structured_array_format_is_preserved(): void {
		$options = array(
			array('value' => 'opt1', 'label' => 'Option 1'),
			array('value' => 'opt2', 'label' => 'Option 2'),
		);

		$result = $this->normalizeOptionsFormat($options);

		self::assertCount(2, $result);
		self::assertSame('opt1', $result[0]['value']);
		self::assertSame('Option 1', $result[0]['label']);
		self::assertSame('opt2', $result[1]['value']);
		self::assertSame('Option 2', $result[1]['label']);
	}

	public function test_mixed_format_is_handled(): void {
		$options = array(
			'simple' => 'Simple Option',
			array('value' => 'structured', 'label' => 'Structured Option'),
		);

		$result = $this->normalizeOptionsFormat($options);

		self::assertCount(2, $result);
		// First is simple format converted
		self::assertSame('simple', $result[0]['value']);
		self::assertSame('Simple Option', $result[0]['label']);
		// Second is structured format preserved
		self::assertSame('structured', $result[1]['value']);
		self::assertSame('Structured Option', $result[1]['label']);
	}

	public function test_numeric_keys_are_converted_to_string_values(): void {
		$options = array(
			0 => 'Zero',
			1 => 'One',
			2 => 'Two',
		);

		$result = $this->normalizeOptionsFormat($options);

		self::assertCount(3, $result);
		self::assertSame('0', $result[0]['value']);
		self::assertSame('Zero', $result[0]['label']);
		self::assertSame('1', $result[1]['value']);
		self::assertSame('One', $result[1]['label']);
	}

	public function test_empty_options_returns_empty_array(): void {
		$result = $this->normalizeOptionsFormat(array());

		self::assertSame(array(), $result);
	}

	// =========================================================================
	// Options HTML Building Tests
	// =========================================================================

	public function test_build_options_generates_option_markup(): void {
		$options = array(
			'opt1' => 'Option 1',
			'opt2' => 'Option 2',
		);

		$result = $this->buildOptions($options);

		self::assertCount(2, $result);
		self::assertStringContainsString('<option', $result[0]);
		self::assertStringContainsString('value="opt1"', $result[0]);
		self::assertStringContainsString('Option 1</option>', $result[0]);
	}

	public function test_build_options_marks_selected_value(): void {
		$options = array(
			'opt1' => 'Option 1',
			'opt2' => 'Option 2',
		);

		$result = $this->buildOptions($options, 'opt2');

		// opt1 should not be selected
		self::assertStringNotContainsString('selected', $result[0]);
		// opt2 should be selected
		self::assertStringContainsString('selected="selected"', $result[1]);
	}

	public function test_build_options_with_groups(): void {
		$options = array(
			array('value' => 'a1', 'label' => 'A1', 'group' => 'Group A'),
			array('value' => 'a2', 'label' => 'A2', 'group' => 'Group A'),
			array('value' => 'b1', 'label' => 'B1', 'group' => 'Group B'),
		);

		$result = $this->buildOptions($options);

		// Should have 2 optgroups
		self::assertCount(2, $result);
		self::assertStringContainsString('<optgroup label="Group A">', $result[0]);
		self::assertStringContainsString('<optgroup label="Group B">', $result[1]);
	}

	public function test_build_options_with_disabled_option(): void {
		$options = array(
			array('value' => 'enabled', 'label' => 'Enabled'),
			array('value' => 'disabled', 'label' => 'Disabled', 'disabled' => true),
		);

		$result = $this->buildOptions($options);

		self::assertStringNotContainsString('disabled', $result[0]);
		self::assertStringContainsString('disabled="disabled"', $result[1]);
	}

	public function test_build_options_with_non_array_returns_empty(): void {
		$result = $this->_invoke_protected_method($this->normalizer, '_build_options', array('not-an-array', null));

		self::assertSame(array(), $result);
	}
}
