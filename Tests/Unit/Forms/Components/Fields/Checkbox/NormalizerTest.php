<?php
/**
 * Tests for Checkbox Normalizer - specifically the checked state logic.
 *
 * These tests verify that:
 * 1. Stored values properly determine checked state
 * 2. Default checked is used when no stored value exists
 * 3. Hidden input is always generated for proper form submission
 *
 * Uses reflection to test the private _build_checkbox_attributes method directly,
 * which is appropriate for testing complex internal logic per ADR-001.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components\Fields\Checkbox;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Components\Fields\Checkbox\Normalizer;
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
		$this->session->shouldReceive('formatAttributes')->andReturnUsing(function (array $attrs): string {
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
		$this->session->shouldReceive('resetState')->andReturnNull();
		$this->session->shouldReceive('reserveId')->andReturnUsing(function (?string $id): string {
			return $id ?? 'generated-id';
		});
		$this->session->shouldReceive('get_logger')->andReturn($this->logger_mock);

		$this->normalizer = new Normalizer($views);

		// Set the session and logger on the normalizer via reflection so private methods can use it
		$this->_set_protected_property_value($this->normalizer, 'session', $this->session);
		$this->_set_protected_property_value($this->normalizer, 'logger', $this->logger_mock);
	}

	/**
	 * Helper to invoke the private _build_checkbox_attributes method
	 */
	private function buildCheckboxAttributes(array $context): string {
		return $this->_invoke_protected_method($this->normalizer, '_build_checkbox_attributes', array($context));
	}

	/**
	 * Helper to invoke the private _build_hidden_attributes method
	 */
	private function buildHiddenAttributes(array $context): string {
		return $this->_invoke_protected_method($this->normalizer, '_build_hidden_attributes', array($context));
	}

	// =========================================================================
	// Checked State Tests - Stored Value
	// =========================================================================

	public function test_checkbox_is_checked_when_stored_value_matches_checked_value(): void {
		$context = array(
			'name'          => 'test_checkbox',
			'id'            => 'test_checkbox',
			'checked_value' => 'on',
			'value'         => 'on',  // Stored value matches checked_value
		);

		$result = $this->buildCheckboxAttributes($context);

		self::assertStringContainsString('checked', $result);
	}

	public function test_checkbox_is_checked_when_stored_value_is_true(): void {
		$context = array(
			'name'          => 'test_checkbox',
			'id'            => 'test_checkbox',
			'checked_value' => 'on',
			'value'         => true,
		);

		$result = $this->buildCheckboxAttributes($context);

		self::assertStringContainsString('checked', $result);
	}

	public function test_checkbox_is_checked_when_stored_value_is_string_one(): void {
		$context = array(
			'name'          => 'test_checkbox',
			'id'            => 'test_checkbox',
			'checked_value' => 'yes',
			'value'         => '1',
		);

		$result = $this->buildCheckboxAttributes($context);

		self::assertStringContainsString('checked', $result);
	}

	public function test_checkbox_is_not_checked_when_stored_value_is_empty(): void {
		$context = array(
			'name'          => 'test_checkbox',
			'id'            => 'test_checkbox',
			'checked_value' => 'on',
			'value'         => '',  // Empty = unchecked
		);

		$result = $this->buildCheckboxAttributes($context);

		self::assertStringNotContainsString('checked', $result);
	}

	public function test_checkbox_is_not_checked_when_stored_value_does_not_match(): void {
		$context = array(
			'name'          => 'test_checkbox',
			'id'            => 'test_checkbox',
			'checked_value' => 'on',
			'value'         => 'off',  // Different value = unchecked
		);

		$result = $this->buildCheckboxAttributes($context);

		self::assertStringNotContainsString('checked', $result);
	}

	// =========================================================================
	// Checked State Tests - Default Checked (no stored value)
	// =========================================================================

	public function test_checkbox_uses_default_checked_when_no_stored_value(): void {
		$context = array(
			'name'            => 'test_checkbox',
			'id'              => 'test_checkbox',
			'checked_value'   => 'on',
			'default_checked' => true,
			// No 'value' key - no stored value
		);

		$result = $this->buildCheckboxAttributes($context);

		self::assertStringContainsString('checked', $result);
	}

	public function test_checkbox_not_checked_when_default_checked_false_and_no_stored_value(): void {
		$context = array(
			'name'            => 'test_checkbox',
			'id'              => 'test_checkbox',
			'checked_value'   => 'on',
			'default_checked' => false,
		);

		$result = $this->buildCheckboxAttributes($context);

		self::assertStringNotContainsString('checked', $result);
	}

	public function test_stored_value_takes_precedence_over_default_checked(): void {
		$context = array(
			'name'            => 'test_checkbox',
			'id'              => 'test_checkbox',
			'checked_value'   => 'on',
			'default_checked' => true,  // Default says checked
			'value'           => '',    // But stored value says unchecked
		);

		$result = $this->buildCheckboxAttributes($context);

		// Stored value (empty = unchecked) should win over default_checked
		self::assertStringNotContainsString('checked', $result);
	}

	// =========================================================================
	// Hidden Input Tests
	// =========================================================================

	public function test_hidden_input_has_correct_name(): void {
		$context = array(
			'name'            => 'my_option[my_checkbox]',
			'unchecked_value' => '',
		);

		$result = $this->buildHiddenAttributes($context);

		self::assertStringContainsString('name="my_option[my_checkbox]"', $result);
	}

	public function test_hidden_input_has_unchecked_value(): void {
		$context = array(
			'name'            => 'test_checkbox',
			'unchecked_value' => 'off',
		);

		$result = $this->buildHiddenAttributes($context);

		self::assertStringContainsString('value="off"', $result);
	}

	public function test_hidden_input_has_empty_value_when_unchecked_value_empty(): void {
		$context = array(
			'name'            => 'test_checkbox',
			'unchecked_value' => '',
		);

		$result = $this->buildHiddenAttributes($context);

		self::assertStringContainsString('value=""', $result);
	}

	// =========================================================================
	// Checkbox Attributes Tests
	// =========================================================================

	public function test_checkbox_attributes_include_name(): void {
		$context = array(
			'name'          => 'my_option[field]',
			'id'            => 'field',
			'checked_value' => 'on',
		);

		$result = $this->buildCheckboxAttributes($context);

		self::assertStringContainsString('name="my_option[field]"', $result);
	}

	public function test_checkbox_attributes_include_value(): void {
		$context = array(
			'name'          => 'test',
			'id'            => 'test',
			'checked_value' => 'yes',
		);

		$result = $this->buildCheckboxAttributes($context);

		self::assertStringContainsString('value="yes"', $result);
	}

	public function test_checkbox_attributes_include_id(): void {
		$context = array(
			'name'          => 'test',
			'id'            => 'my_unique_id',
			'checked_value' => 'on',
		);

		$result = $this->buildCheckboxAttributes($context);

		self::assertStringContainsString('id="my_unique_id"', $result);
	}
}
