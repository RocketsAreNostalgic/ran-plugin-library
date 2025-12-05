<?php
/**
 * Rendered output tests for all form components.
 *
 * Tests the actual HTML output of components to verify they render correctly
 * with various configurations. Uses ComponentManifest::render_to_string().
 *
 * NOTE: This test class must be run in isolation due to WP_Mock state dependencies.
 * Run with: composer test -- --filter=ComponentRenderOutputTest
 *
 * @group isolated
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Components;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\Cache\ComponentCacheService;
use WP_Mock;

final class ComponentRenderOutputTest extends PluginLibTestCase {
	private ?ComponentManifest $manifest = null;

	public function setUp(): void {
		// Skip when running full suite - must run in isolation
		if (getenv('RUN_ISOLATED_TESTS') !== 'true') {
			$this->markTestSkipped('ComponentRenderOutputTest must run in isolation. Use: RUN_ISOLATED_TESTS=true composer test -- --filter=ComponentRenderOutputTest');
		}

		parent::setUp();

		// Stub WordPress functions needed by ComponentCacheService
		WP_Mock::userFunction('get_option')->andReturn(false);
		WP_Mock::userFunction('update_option')->andReturn(true);

		// Create fresh manifest for each test to avoid stale WP_Mock state
		$components_dir = dirname(__DIR__, 4) . '/inc/Forms/Components';
		$loader         = new ComponentLoader($components_dir, $this->logger_mock);
		$cache_service  = new ComponentCacheService($this->logger_mock);
		$this->manifest = new ComponentManifest($loader, $this->logger_mock, $cache_service);
	}

	public function tearDown(): void {
		$this->manifest = null;
		parent::tearDown();
	}

	// =========================================================================
	// Input Component
	// =========================================================================

	public function test_input_renders_basic(): void {
		$html = $this->manifest->render_to_string('fields.input', array(
			'name' => 'test_field',
		));

		self::assertStringContainsString('<input', $html);
		self::assertStringContainsString('name="test_field"', $html);
	}

	public function test_input_renders_with_value(): void {
		$html = $this->manifest->render_to_string('fields.input', array(
			'name'  => 'test_field',
			'value' => 'Hello World',
		));

		self::assertStringContainsString('value="Hello World"', $html);
	}

	public function test_input_renders_with_placeholder(): void {
		$html = $this->manifest->render_to_string('fields.input', array(
			'name'        => 'test_field',
			'placeholder' => 'Enter text...',
		));

		self::assertStringContainsString('placeholder="Enter text..."', $html);
	}

	public function test_input_renders_with_type(): void {
		$html = $this->manifest->render_to_string('fields.input', array(
			'name'       => 'email_field',
			'input_type' => 'email',
		));

		self::assertStringContainsString('type="email"', $html);
	}

	public function test_input_renders_disabled(): void {
		$html = $this->manifest->render_to_string('fields.input', array(
			'name'     => 'test_field',
			'disabled' => true,
		));

		self::assertStringContainsString('disabled', $html);
	}

	public function test_input_renders_readonly(): void {
		$html = $this->manifest->render_to_string('fields.input', array(
			'name'     => 'test_field',
			'readonly' => true,
		));

		self::assertStringContainsString('readonly', $html);
	}

	// =========================================================================
	// Number Component
	// =========================================================================

	public function test_number_renders_basic(): void {
		$html = $this->manifest->render_to_string('fields.number', array(
			'name' => 'quantity',
		));

		self::assertStringContainsString('<input', $html);
		self::assertStringContainsString('type="number"', $html);
		self::assertStringContainsString('name="quantity"', $html);
	}

	public function test_number_renders_with_min_max(): void {
		$html = $this->manifest->render_to_string('fields.number', array(
			'name' => 'quantity',
			'min'  => 0,
			'max'  => 100,
		));

		self::assertStringContainsString('min="0"', $html);
		self::assertStringContainsString('max="100"', $html);
	}

	public function test_number_renders_with_step(): void {
		$html = $this->manifest->render_to_string('fields.number', array(
			'name' => 'price',
			'step' => '0.01',
		));

		self::assertStringContainsString('step="0.01"', $html);
	}

	// =========================================================================
	// Textarea Component
	// =========================================================================

	public function test_textarea_renders_basic(): void {
		$html = $this->manifest->render_to_string('fields.textarea', array(
			'name' => 'description',
		));

		self::assertStringContainsString('<textarea', $html);
		self::assertStringContainsString('</textarea>', $html);
		self::assertStringContainsString('name="description"', $html);
	}

	public function test_textarea_renders_with_value(): void {
		$html = $this->manifest->render_to_string('fields.textarea', array(
			'name'  => 'description',
			'value' => 'Some text content',
		));

		self::assertStringContainsString('>Some text content</textarea>', $html);
	}

	public function test_textarea_renders_with_rows_cols(): void {
		$html = $this->manifest->render_to_string('fields.textarea', array(
			'name'       => 'description',
			'attributes' => array('rows' => 10, 'cols' => 50),
		));

		self::assertStringContainsString('rows="10"', $html);
		self::assertStringContainsString('cols="50"', $html);
	}

	// =========================================================================
	// Select Component
	// =========================================================================

	public function test_select_renders_basic(): void {
		$html = $this->manifest->render_to_string('fields.select', array(
			'name' => 'country',
		));

		self::assertStringContainsString('<select', $html);
		self::assertStringContainsString('</select>', $html);
		self::assertStringContainsString('name="country"', $html);
	}

	public function test_select_renders_simple_options(): void {
		$html = $this->manifest->render_to_string('fields.select', array(
			'name'    => 'country',
			'options' => array(
				'us' => 'United States',
				'uk' => 'United Kingdom',
				'ca' => 'Canada',
			),
		));

		self::assertStringContainsString('<option value="us">United States</option>', $html);
		self::assertStringContainsString('<option value="uk">United Kingdom</option>', $html);
		self::assertStringContainsString('<option value="ca">Canada</option>', $html);
	}

	public function test_select_marks_selected_option(): void {
		$html = $this->manifest->render_to_string('fields.select', array(
			'name'    => 'country',
			'value'   => 'uk',
			'options' => array(
				'us' => 'United States',
				'uk' => 'United Kingdom',
			),
		));

		self::assertStringNotContainsString('value="us" selected', $html);
		self::assertStringContainsString('value="uk" selected', $html);
	}

	public function test_select_renders_option_groups(): void {
		$html = $this->manifest->render_to_string('fields.select', array(
			'name'    => 'city',
			'options' => array(
				array('value' => 'nyc', 'label' => 'New York', 'group' => 'USA'),
				array('value' => 'la', 'label' => 'Los Angeles', 'group' => 'USA'),
				array('value' => 'london', 'label' => 'London', 'group' => 'UK'),
			),
		));

		self::assertStringContainsString('<optgroup label="USA">', $html);
		self::assertStringContainsString('<optgroup label="UK">', $html);
	}

	// =========================================================================
	// Checkbox Component
	// =========================================================================

	public function test_checkbox_renders_basic(): void {
		$html = $this->manifest->render_to_string('fields.checkbox', array(
			'name' => 'agree',
		));

		self::assertStringContainsString('type="checkbox"', $html);
		self::assertStringContainsString('name="agree"', $html);
	}

	public function test_checkbox_renders_hidden_input_for_unchecked(): void {
		$html = $this->manifest->render_to_string('fields.checkbox', array(
			'name'          => 'agree',
			'checked_value' => '1',
		));

		self::assertStringContainsString('type="hidden"', $html);
		self::assertStringContainsString('type="checkbox"', $html);
	}

	public function test_checkbox_renders_checked_when_value_matches(): void {
		$html = $this->manifest->render_to_string('fields.checkbox', array(
			'name'          => 'agree',
			'checked_value' => '1',
			'value'         => '1',
		));

		self::assertMatchesRegularExpression('/type="checkbox"[^>]*checked/', $html);
	}

	public function test_checkbox_renders_unchecked_when_value_empty(): void {
		$html = $this->manifest->render_to_string('fields.checkbox', array(
			'name'          => 'agree',
			'checked_value' => '1',
			'value'         => '',
		));

		self::assertDoesNotMatchRegularExpression('/type="checkbox"[^>]*checked/', $html);
	}

	public function test_checkbox_renders_with_label(): void {
		$html = $this->manifest->render_to_string('fields.checkbox', array(
			'name'  => 'agree',
			'label' => 'I agree to terms',
		));

		self::assertStringContainsString('I agree to terms', $html);
	}

	// =========================================================================
	// Radio Group Component
	// =========================================================================

	public function test_radio_group_renders_options(): void {
		$html = $this->manifest->render_to_string('fields.radio-group', array(
			'name'    => 'size',
			'options' => array(
				array('value' => 'sm', 'label' => 'Small'),
				array('value' => 'md', 'label' => 'Medium'),
				array('value' => 'lg', 'label' => 'Large'),
			),
		));

		self::assertStringContainsString('type="radio"', $html);
		self::assertStringContainsString('value="sm"', $html);
		self::assertStringContainsString('value="md"', $html);
		self::assertStringContainsString('value="lg"', $html);
		self::assertStringContainsString('Small', $html);
		self::assertStringContainsString('Medium', $html);
		self::assertStringContainsString('Large', $html);
	}

	public function test_radio_group_marks_default_checked(): void {
		$html = $this->manifest->render_to_string('fields.radio-group', array(
			'name'    => 'size',
			'default' => 'md',
			'options' => array(
				array('value' => 'sm', 'label' => 'Small'),
				array('value' => 'md', 'label' => 'Medium'),
			),
		));

		self::assertStringContainsString('value="md"', $html);
		self::assertStringContainsString('checked="checked"', $html);
	}

	// =========================================================================
	// Checkbox Group Component
	// =========================================================================

	public function test_checkbox_group_renders_options(): void {
		$html = $this->manifest->render_to_string('fields.checkbox-group', array(
			'name'    => 'colors',
			'options' => array(
				array('value' => 'red', 'label' => 'Red'),
				array('value' => 'green', 'label' => 'Green'),
				array('value' => 'blue', 'label' => 'Blue'),
			),
		));

		self::assertStringContainsString('type="checkbox"', $html);
		self::assertStringContainsString('Red', $html);
		self::assertStringContainsString('Green', $html);
		self::assertStringContainsString('Blue', $html);
	}

	// =========================================================================
	// MultiSelect Component
	// =========================================================================

	public function test_multiselect_renders_with_multiple_attribute(): void {
		$html = $this->manifest->render_to_string('fields.multi-select', array(
			'name'    => 'tags',
			'options' => array(
				array('value' => 'php', 'label' => 'PHP'),
				array('value' => 'js', 'label' => 'JavaScript'),
			),
		));

		self::assertStringContainsString('<select', $html);
		self::assertStringContainsString('multiple', $html);
	}

	// =========================================================================
	// Button Component
	// =========================================================================

	public function test_button_renders_basic(): void {
		$html = $this->manifest->render_to_string('elements.button', array(
			'label' => 'Click Me',
		));

		self::assertStringContainsString('<button', $html);
		self::assertStringContainsString('Click Me', $html);
	}

	public function test_button_renders_with_type(): void {
		$html = $this->manifest->render_to_string('elements.button', array(
			'label' => 'Submit',
			'type'  => 'submit',
		));

		self::assertStringContainsString('type="submit"', $html);
	}

	public function test_button_renders_disabled(): void {
		$html = $this->manifest->render_to_string('elements.button', array(
			'label'    => 'Disabled',
			'disabled' => true,
		));

		self::assertStringContainsString('disabled', $html);
	}

	// =========================================================================
	// Button Link Component
	// =========================================================================

	public function test_button_link_renders_as_anchor(): void {
		$html = $this->manifest->render_to_string('elements.button-link', array(
			'label' => 'Go Home',
			'url'   => '/home',
		));

		self::assertStringContainsString('<a', $html);
		self::assertStringContainsString('href="/home"', $html);
		self::assertStringContainsString('Go Home', $html);
	}

	// =========================================================================
	// Inline Link Component
	// =========================================================================

	public function test_inline_link_renders(): void {
		$html = $this->manifest->render_to_string('elements.inline-link', array(
			'label' => 'Learn more',
			'url'   => 'https://example.com',
		));

		self::assertStringContainsString('<a', $html);
		self::assertStringContainsString('href="https://example.com"', $html);
		self::assertStringContainsString('Learn more', $html);
	}

	// =========================================================================
	// File Upload Component
	// =========================================================================

	public function test_file_upload_renders_basic(): void {
		$html = $this->manifest->render_to_string('fields.file-upload', array(
			'name' => 'document',
		));

		self::assertStringContainsString('type="file"', $html);
		self::assertStringContainsString('name="document"', $html);
	}

	public function test_file_upload_renders_with_accept(): void {
		$html = $this->manifest->render_to_string('fields.file-upload', array(
			'name'   => 'image',
			'accept' => 'image/*',
		));

		self::assertStringContainsString('accept="image/*"', $html);
	}

	// =========================================================================
	// Media Picker Component
	// =========================================================================

	public function test_media_picker_renders_basic(): void {
		$html = $this->manifest->render_to_string('fields.media-picker', array(
			'name' => 'featured_image',
		));

		// Media picker should have a hidden input for the value
		self::assertStringContainsString('name="featured_image"', $html);
	}

	// =========================================================================
	// Text Component
	// =========================================================================

	public function test_text_renders_as_input(): void {
		$html = $this->manifest->render_to_string('fields.text', array(
			'name'  => 'username',
			'value' => 'john_doe',
		));

		self::assertStringContainsString('<input', $html);
		self::assertStringContainsString('type="text"', $html);
		self::assertStringContainsString('value="john_doe"', $html);
	}
}
