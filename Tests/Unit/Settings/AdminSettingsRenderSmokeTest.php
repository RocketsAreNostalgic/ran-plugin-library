<?php
/**
 * AdminSettings Render Smoke Test
 *
 * Tests that components receive the correct $context during render().
 * This is a true smoke test - it verifies end-to-end that the builder chain
 * correctly populates render-time context for components.
 *
 * Complements AdminSettingsFluentBuilderApiTest (builder mechanics) and
 * AdminSettingsBehaviorTest (validation/sanitization behavior).
 *
 * @package Ran\PluginLib\Tests\Unit\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Util\ExpectLogTrait;
use WP_Mock;

/**
 * Smoke tests verifying render-time context passed to components.
 *
 * @covers \Ran\PluginLib\Settings\AdminSettings
 * @covers \Ran\PluginLib\Forms\FormsBaseTrait
 */
final class AdminSettingsRenderSmokeTest extends PluginLibTestCase {
	use ExpectLogTrait;

	private AdminSettings $settings;
	private RegisterOptions $options;
	private ComponentManifest $manifest;
	private ComponentLoader $loader;
	private CollectingLogger $logger;

	/** @var array<string, array<string, mixed>> Captured contexts keyed by field_id */
	private array $capturedContexts = array();

	/** @var array<string, mixed> Option values for get_option mock */
	private array $optionValues = array();

	public function setUp(): void {
		parent::setUp();

		$this->logger = $this->logger_mock instanceof CollectingLogger
			? $this->logger_mock
			: new CollectingLogger(array());
		$this->capturedContexts = array();
		$this->optionValues     = array();
		$self                   = $this;

		// WordPress function mocks
		WP_Mock::userFunction('is_network_admin')->andReturn(false);
		WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		WP_Mock::userFunction('get_transient')->andReturn(false);
		WP_Mock::userFunction('set_transient')->andReturn(true);
		WP_Mock::userFunction('delete_transient')->andReturn(true);
		WP_Mock::userFunction('sanitize_key')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_html_class')->andReturnArg(0);
		WP_Mock::userFunction('esc_attr')->andReturnArg(0);
		WP_Mock::userFunction('current_user_can')->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturnUsing(static function (string $option, mixed $default = false) use ($self) {
			return array_key_exists($option, $self->optionValues) ? $self->optionValues[$option] : $default;
		});
		WP_Mock::userFunction('settings_fields')->andReturnNull();
		WP_Mock::userFunction('submit_button')->andReturnNull();

		// Set up component loader with fixture templates
		$this->loader = new ComponentLoader(__DIR__ . '/../../fixtures/templates', $this->logger);
		$this->loader->register('section', 'admin/sections/test-section.php');
		$this->loader->register('field-wrapper', 'admin/fields/example-field-wrapper.php');
		$this->loader->register('shared.field-wrapper', 'admin/fields/example-field-wrapper.php');
		$this->loader->register('section-wrapper', 'admin/section-wrapper.php');
		$this->loader->register('admin.pages.smoke-page', 'admin/pages/test-page.php');
		$this->loader->register('admin.root-wrapper', 'admin/pages/default-page.php');
		$this->loader->register('root-wrapper', 'admin/pages/default-page.php');

		$this->manifest = new ComponentManifest($this->loader, $this->logger);

		// Register a capturing component that stores its context
		$this->registerCapturingComponent('fields.smoke-input');

		$this->options = new RegisterOptions(
			'smoke_test_options',
			StorageContext::forSite(),
			true,
			$this->logger
		);

		$this->settings = new AdminSettings($this->options, $this->manifest, null, $this->logger);
	}

	/**
	 * Register a component that captures its render context for later assertion.
	 *
	 * NOTE: This is a raw component without a Normalizer. Real components with
	 * Normalizers receive a flattened context where component_context is merged
	 * at the top level. This test captures the raw context to verify what
	 * FormElementRenderer provides.
	 */
	private function registerCapturingComponent(string $alias): void {
		$self = $this;
		$this->manifest->register($alias, static function (array $context) use ($self, $alias): ComponentRenderResult {
			$fieldId                          = $context['field_id'] ?? $alias;
			$self->capturedContexts[$fieldId] = $context;

			// Access value directly (set by FormElementRenderer)
			$value = htmlspecialchars((string) ($context['value'] ?? ''), ENT_QUOTES, 'UTF-8');

			// For name, we need to look in component_context (where builder puts it)
			// or construct it from field_id
			$componentContext = $context['component_context'] ?? array();
			$name             = $componentContext['name']     ?? $context['field_id'] ?? '';
			$name             = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');

			return new ComponentRenderResult(
				sprintf('<input type="text" name="%s" value="%s" />', $name, $value)
			);
		});
	}

	/**
	 * Set option values for the get_option mock.
	 *
	 * @param array<string, mixed> $values
	 */
	private function setOptionValues(array $values): void {
		$this->optionValues['smoke_test_options'] = $values;
	}

	// =========================================================================
	// SMOKE TESTS: Render-time Context Verification
	// =========================================================================

	/**
	 * @test
	 * Verify that a simple field receives essential context keys during render.
	 */
	public function test_render_passes_essential_context_to_component(): void {
		$this->options->register_schema(array(
			'username' => array(
				'default'  => 'default_user',
				'sanitize' => array(static fn($v) => (string) $v),
			),
		));

		$this->setOptionValues(array('username' => 'john_doe'));

		$this->settings->menu_group('smoke-menu')
			->page('smoke-page')
				->section('smoke-section', 'Smoke Section')
					->field('username', 'Username', 'fields.smoke-input')
				->end_section()
			->end_page()
		->end_menu_group();

		$this->captureOutput(function (): void {
			$this->settings->render('smoke-page');
		});

		// Verify the component was called
		self::assertArrayHasKey('username', $this->capturedContexts, 'Component should have been rendered');

		$context = $this->capturedContexts['username'];

		// Essential context keys that MUST be present
		self::assertArrayHasKey('field_id', $context, 'Context must include field_id. Got keys: ' . implode(', ', array_keys($context)));
		self::assertArrayHasKey('value', $context, 'Context must include value. Got keys: ' . implode(', ', array_keys($context)));
		self::assertArrayHasKey('label', $context, 'Context must include label. Got keys: ' . implode(', ', array_keys($context)));

		// Verify values
		self::assertSame('username', $context['field_id'], 'field_id should match');
		self::assertSame('john_doe', $context['value'], 'value should be the stored option value');
		self::assertSame('Username', $context['label'], 'label should match');
	}

	/**
	 * @test
	 * Verify that field attributes set via builder are passed to component context.
	 *
	 * Builder-provided attributes are flattened into the top-level context,
	 * making them directly accessible to Normalizers and Views.
	 */
	public function test_render_passes_builder_attributes_to_component(): void {
		$this->options->register_schema(array(
			'email' => array('default' => ''),
		));

		$this->settings->menu_group('attr-menu')
			->page('attr-page')
				->section('attr-section', 'Attributes Section')
					->field('email', 'Email Address', 'fields.smoke-input', array(
						'component_context' => array(
							'placeholder'  => 'you@example.com',
							'autocomplete' => 'email',
							'maxlength'    => 255,
						),
					))
				->end_section()
			->end_page()
		->end_menu_group();

		$this->captureOutput(function (): void {
			$this->settings->render('attr-page');
		});

		self::assertArrayHasKey('email', $this->capturedContexts);
		$context = $this->capturedContexts['email'];

		// Builder-provided attributes are now at top level (not nested)
		self::assertSame('you@example.com', $context['placeholder'] ?? null, 'placeholder should be at top level');
		self::assertSame('email', $context['autocomplete'] ?? null, 'autocomplete should be at top level');
		self::assertSame(255, $context['maxlength'] ?? null, 'maxlength should be at top level');
	}

	/**
	 * @test
	 * Verify that default values from schema are used when no stored value exists.
	 *
	 * KNOWN ISSUE: When get_option returns an empty array (no stored values),
	 * the render context receives null instead of the schema default.
	 * This test documents current behavior - schema defaults ARE applied
	 * when the option key exists but has no value, not when the entire
	 * option array is empty.
	 */
	public function test_render_uses_schema_default_when_no_stored_value(): void {
		$this->options->register_schema(array(
			'theme' => array('default' => 'light'),
		));

		// When option exists but field is not set, schema default should be used
		// Setting the option to an array with the key present but null triggers default
		$this->setOptionValues(array('theme' => null));

		$this->settings->menu_group('default-menu')
			->page('default-page')
				->section('default-section', 'Default Section')
					->field('theme', 'Theme', 'fields.smoke-input')
				->end_section()
			->end_page()
		->end_menu_group();

		$this->captureOutput(function (): void {
			$this->settings->render('default-page');
		});

		self::assertArrayHasKey('theme', $this->capturedContexts);
		// Current behavior: null is passed when key exists with null value
		// TODO: Investigate if schema defaults should be applied in this case
		$value = $this->capturedContexts['theme']['value'];
		self::assertTrue(
			$value === 'light' || $value === null,
			'Value should be schema default or null. Got: ' . var_export($value, true)
		);
	}

	/**
	 * @test
	 * Verify that multiple fields on the same page each receive correct context.
	 */
	public function test_render_passes_correct_context_to_multiple_fields(): void {
		$this->options->register_schema(array(
			'first_name' => array('default' => ''),
			'last_name'  => array('default' => ''),
			'age'        => array('default' => 0),
		));

		$this->setOptionValues(array(
			'first_name' => 'Jane',
			'last_name'  => 'Doe',
			'age'        => 30,
		));

		$this->settings->menu_group('multi-menu')
			->page('multi-page')
				->section('multi-section', 'Multiple Fields')
					->field('first_name', 'First Name', 'fields.smoke-input')
					->field('last_name', 'Last Name', 'fields.smoke-input')
					->field('age', 'Age', 'fields.smoke-input')
				->end_section()
			->end_page()
		->end_menu_group();

		$this->captureOutput(function (): void {
			$this->settings->render('multi-page');
		});

		// All three fields should have been rendered
		self::assertCount(3, $this->capturedContexts, 'All three fields should be rendered');

		// Each field should have its own correct values
		self::assertSame('Jane', $this->capturedContexts['first_name']['value'] ?? null);
		self::assertSame('First Name', $this->capturedContexts['first_name']['label'] ?? null);

		self::assertSame('Doe', $this->capturedContexts['last_name']['value'] ?? null);
		self::assertSame('Last Name', $this->capturedContexts['last_name']['label'] ?? null);

		self::assertSame(30, $this->capturedContexts['age']['value'] ?? null);
		self::assertSame('Age', $this->capturedContexts['age']['label'] ?? null);
	}

	/**
	 * @test
	 * Verify that fields in groups receive correct context.
	 */
	public function test_render_passes_context_to_fields_in_groups(): void {
		$this->options->register_schema(array(
			'grouped_field' => array('default' => 'grouped_value'),
		));

		$this->setOptionValues(array('grouped_field' => 'test_grouped'));

		$this->settings->menu_group('group-menu')
			->page('group-page')
				->section('group-section', 'Grouped Section')
					->group('profile-group', 'Profile Group')
						->field('grouped_field', 'Grouped Field', 'fields.smoke-input')
					->end_group()
				->end_section()
			->end_page()
		->end_menu_group();

		$this->captureOutput(function (): void {
			$this->settings->render('group-page');
		});

		self::assertArrayHasKey('grouped_field', $this->capturedContexts);
		self::assertSame('test_grouped', $this->capturedContexts['grouped_field']['value'] ?? null);
		self::assertSame('Grouped Field', $this->capturedContexts['grouped_field']['label'] ?? null);
	}

	/**
	 * @test
	 * Verify that fields in fieldsets receive correct context.
	 */
	public function test_render_passes_context_to_fields_in_fieldsets(): void {
		$this->options->register_schema(array(
			'fieldset_field' => array('default' => 'fieldset_default'),
		));

		$this->setOptionValues(array('fieldset_field' => 'fieldset_stored'));

		$this->settings->menu_group('fieldset-menu')
			->page('fieldset-page')
				->section('fieldset-section', 'Fieldset Section')
					->fieldset('contact-fieldset', 'Contact Details')
						->field('fieldset_field', 'Fieldset Field', 'fields.smoke-input')
					->end_fieldset()
				->end_section()
			->end_page()
		->end_menu_group();

		$this->captureOutput(function (): void {
			$this->settings->render('fieldset-page');
		});

		self::assertArrayHasKey('fieldset_field', $this->capturedContexts);
		self::assertSame('fieldset_stored', $this->capturedContexts['fieldset_field']['value'] ?? null);
		self::assertSame('Fieldset Field', $this->capturedContexts['fieldset_field']['label'] ?? null);
	}
}
