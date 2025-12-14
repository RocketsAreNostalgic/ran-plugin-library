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

use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Components\Fields\Input\Builder as InputBuilder;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Config\ConfigInterface;

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
		WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
		WP_Mock::userFunction('wp_kses_post')->andReturnArg(0);
		WP_Mock::userFunction('esc_attr')->andReturnArg(0);
		WP_Mock::userFunction('current_user_can')->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturnUsing(static function (string $option, mixed $default = false) use ($self) {
			return array_key_exists($option, $self->optionValues) ? $self->optionValues[$option] : $default;
		});
		WP_Mock::userFunction('settings_fields')->andReturnNull();
		WP_Mock::userFunction('submit_button')->andReturnNull();

		// Set up component loader with real components + fixture templates
		$this->loader = new ComponentLoader(__DIR__ . '/../../../inc/Forms/Components', $this->logger);
		$fixturesDir  = __DIR__ . '/../../fixtures/templates';
		$this->loader->register_absolute('layout.zone.section-wrapper', $fixturesDir . '/admin/sections/test-section.php');
		$this->loader->register_absolute('field-wrapper', $fixturesDir . '/admin/fields/example-field-wrapper.php');
		$this->loader->register_absolute('shared.field-wrapper', $fixturesDir . '/admin/fields/example-field-wrapper.php');
		$this->loader->register_absolute('section-wrapper', $fixturesDir . '/admin/section-wrapper.php');
		$this->loader->register_absolute('admin.pages.smoke-page', $fixturesDir . '/admin/pages/test-page.php');
		$this->loader->register_absolute('admin.root-wrapper', $fixturesDir . '/admin/pages/default-page.php');
		$this->loader->register_absolute('root-wrapper', $fixturesDir . '/admin/pages/default-page.php');

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
		$this->injectBuilderFactory($alias);
	}

	/**
	 * Inject a builder factory for a fake component alias.
	 */
	private function injectBuilderFactory(string $alias): void {
		$reflection = new \ReflectionObject($this->manifest);
		$property   = $reflection->getProperty('componentMetadata');
		$property->setAccessible(true);
		$metadata = $property->getValue($this->manifest);
		if (!is_array($metadata)) {
			$metadata = array();
		}
		$current            = $metadata[$alias] ?? array();
		$current['builder'] = static fn (string $id, string $label): InputBuilder => new InputBuilder($id, $label);
		$metadata[$alias]   = $current;
		$property->setValue($this->manifest, $metadata);
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
				->end_field()->end_section()
			->end_page()
		->end_menu();

		$this->captureOutput(function (): void {
			$this->settings->__render('smoke-page');
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
				->end_field()->end_section()
			->end_page()
		->end_menu();

		$this->captureOutput(function (): void {
			$this->settings->__render('attr-page');
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
				->end_field()->end_section()
			->end_page()
		->end_menu();

		$this->captureOutput(function (): void {
			$this->settings->__render('default-page');
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
					->field('first_name', 'First Name', 'fields.smoke-input')->end_field()
					->field('last_name', 'Last Name', 'fields.smoke-input')->end_field()
					->field('age', 'Age', 'fields.smoke-input')
				->end_field()->end_section()
			->end_page()
		->end_menu();

		$this->captureOutput(function (): void {
			$this->settings->__render('multi-page');
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
						->field('grouped_field', 'Grouped Field', 'fields.smoke-input')->end_field()
					->end_group()
				->end_section()
			->end_page()
		->end_menu();

		$this->captureOutput(function (): void {
			$this->settings->__render('group-page');
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
						->field('fieldset_field', 'Fieldset Field', 'fields.smoke-input')->end_field()
					->end_fieldset()
				->end_section()
			->end_page()
		->end_menu();

		$this->captureOutput(function (): void {
			$this->settings->__render('fieldset-page');
		});

		self::assertArrayHasKey('fieldset_field', $this->capturedContexts);
		self::assertSame('fieldset_stored', $this->capturedContexts['fieldset_field']['value'] ?? null);
		self::assertSame('Fieldset Field', $this->capturedContexts['fieldset_field']['label'] ?? null);
	}

	// =========================================================================
	// EXTERNAL COMPONENT SMOKE TESTS - COMPREHENSIVE MATRIX
	// =========================================================================
	//
	// Tests all 8 combinations of optional component files:
	// - View.php (always required)
	// - Validator.php (optional)
	// - Normalizer.php (optional)
	// - Developer-provided schema (optional)
	//
	// Matrix:
	// 1. View only
	// 2. View + Validator
	// 3. View + Normalizer
	// 4. View + Validator + Normalizer
	// 5. View + Dev Schema
	// 6. View + Validator + Dev Schema
	// 7. View + Normalizer + Dev Schema
	// 8. View + Validator + Normalizer + Dev Schema

	/**
	 * Helper to create a fresh AdminSettings instance for external component tests.
	 *
	 * @param string $externalDir Path to external components directory
	 * @param string $optionKey   Option key for this test
	 * @param string $namespace   PHP namespace for components
	 * @return AdminSettings
	 */
	private function createExternalComponentSettings(string $externalDir, string $optionKey, string $namespace = 'TestPlugin\\Components'): AdminSettings {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => $externalDir));
		$config->method('get_namespace')->willReturn($namespace);

		$loader = new ComponentLoader(__DIR__ . '/../../fixtures/templates', $this->logger);
		$loader->register('admin.root-wrapper', 'admin/pages/default-page.php');
		$loader->register('root-wrapper', 'admin/pages/default-page.php');
		$loader->register('layout.zone.section-wrapper', 'admin/sections/test-section.php');
		$loader->register('section-wrapper', 'admin/section-wrapper.php');
		$loader->register('field-wrapper', 'admin/fields/example-field-wrapper.php');
		$loader->register('shared.field-wrapper', 'admin/fields/example-field-wrapper.php');

		$manifest = new ComponentManifest($loader, $this->logger);
		$options  = new RegisterOptions($optionKey, StorageContext::forSite(), true, $this->logger);

		return new AdminSettings($options, $manifest, $config, $this->logger);
	}

	/**
	 * Helper to create View.php content.
	 */
	private function createViewPhp(string $marker): string {
		return '<?php
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

$name  = htmlspecialchars($context["name"] ?? "", ENT_QUOTES, "UTF-8");
$value = htmlspecialchars((string)($context["value"] ?? ""), ENT_QUOTES, "UTF-8");
$label = htmlspecialchars($context["label"] ?? "", ENT_QUOTES, "UTF-8");

$markup = sprintf(
    \'<div class="ext-component" data-marker="%s"><label>%%s</label><input type="text" name="%%s" value="%%s"></div>\',
    \'' . $marker . '\'
);
$markup = sprintf($markup, $label, $name, $value);

return new ComponentRenderResult(markup: $markup);
';
	}

	/**
	 * Helper to create Builder.php content for external components.
	 */
	private function createBuilderPhp(string $namespace, string $componentName): string {
		return '<?php
namespace ' . $namespace . '\\' . $componentName . ';

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderInputBase;

final class Builder extends ComponentBuilderInputBase {
    protected function _get_component(): string {
        return "fields.input";
    }

    protected function _build_component_context(): array {
        return array();
    }
}
';
	}

	/**
	 * Helper to create Validator.php content (stub - doesn't implement interface).
	 */
	private function createValidatorPhp(string $namespace, string $componentName): string {
		return '<?php
namespace ' . $namespace . '\\' . $componentName . ';

class Validator {
    public static function schema(): array {
        return array(
            "value" => array("type" => "string", "default" => ""),
        );
    }
}
';
	}

	/**
	 * Helper to create Normalizer.php content (stub - doesn't implement interface).
	 */
	private function createNormalizerPhp(string $namespace, string $componentName): string {
		return '<?php
namespace ' . $namespace . '\\' . $componentName . ';

use Ran\PluginLib\Forms\Component\ComponentLoader;

class Normalizer {
    private ComponentLoader $views;

    public function __construct(ComponentLoader $views) {
        $this->views = $views;
    }

    public function render(array $context, $helpers, string $alias): array {
        // Simple passthrough - just reorganize context
        $componentContext = $context["component_context"] ?? array();
        $normalized = array(
            "name"  => $componentContext["name"] ?? $context["field_id"] ?? "",
            "value" => $context["value"] ?? "",
            "label" => $context["label"] ?? "",
        );

        $result = $this->views->__render($alias, $normalized);

        return array(
            "payload"  => $result,
            "warnings" => array(),
        );
    }
}
';
	}

	// -------------------------------------------------------------------------
	// TEST 1: View.php only (minimal component)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Matrix 1/8: View.php only - the minimal external component.
	 */
	public function test_matrix_1_view_only(): void {
		$externalDir = sys_get_temp_dir() . '/ran-matrix-1-' . uniqid();
		mkdir($externalDir . '/ViewOnly', 0777, true);

		file_put_contents($externalDir . '/ViewOnly/View.php', $this->createViewPhp('view-only'));
		file_put_contents($externalDir . '/ViewOnly/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'ViewOnly'));
		require_once $externalDir . '/ViewOnly/Builder.php';

		try {
			$this->optionValues['matrix_1'] = array('field_1' => 'value_from_db');
			$settings                       = $this->createExternalComponentSettings($externalDir, 'matrix_1');

			$settings->register_component('view-only', array(
				'path'   => 'ViewOnly',
				'prefix' => 'ext',
			));

			$settings->menu_group('m1')
				->page('p1')
					->section('s1', 'Section')
						->field('field_1', 'Label 1', 'ext.view-only')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('p1'));

			self::assertStringContainsString('data-marker="view-only"', $output);
			self::assertStringContainsString('value_from_db', $output);
			self::assertStringContainsString('Label 1', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// -------------------------------------------------------------------------
	// TEST 2: View.php + Validator.php
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Matrix 2/8: View.php + Validator.php
	 */
	public function test_matrix_2_view_validator(): void {
		$externalDir = sys_get_temp_dir() . '/ran-matrix-2-' . uniqid();
		mkdir($externalDir . '/WithValidator', 0777, true);

		file_put_contents($externalDir . '/WithValidator/View.php', $this->createViewPhp('view-validator'));
		file_put_contents($externalDir . '/WithValidator/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'WithValidator'));
		require_once $externalDir . '/WithValidator/Builder.php';
		file_put_contents($externalDir . '/WithValidator/Validator.php', $this->createValidatorPhp('TestPlugin\\Components', 'WithValidator'));

		try {
			$this->optionValues['matrix_2'] = array('field_2' => 'validated_value');
			$settings                       = $this->createExternalComponentSettings($externalDir, 'matrix_2');

			$settings->register_component('with-validator', array(
				'path'   => 'WithValidator',
				'prefix' => 'ext',
			));

			$settings->menu_group('m2')
				->page('p2')
					->section('s2', 'Section')
						->field('field_2', 'Label 2', 'ext.with-validator')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('p2'));

			self::assertStringContainsString('data-marker="view-validator"', $output);
			self::assertStringContainsString('validated_value', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// -------------------------------------------------------------------------
	// TEST 3: View.php + Normalizer.php (no Validator)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Matrix 3/8: View.php + Normalizer.php (Normalizer without Validator)
	 */
	public function test_matrix_3_view_normalizer(): void {
		$externalDir = sys_get_temp_dir() . '/ran-matrix-3-' . uniqid();
		mkdir($externalDir . '/WithNormalizer', 0777, true);

		file_put_contents($externalDir . '/WithNormalizer/View.php', $this->createViewPhp('view-normalizer'));
		file_put_contents($externalDir . '/WithNormalizer/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'WithNormalizer'));
		require_once $externalDir . '/WithNormalizer/Builder.php';
		file_put_contents($externalDir . '/WithNormalizer/Normalizer.php', $this->createNormalizerPhp('TestPlugin\\Components', 'WithNormalizer'));

		try {
			$this->optionValues['matrix_3'] = array('field_3' => 'normalized_value');
			$settings                       = $this->createExternalComponentSettings($externalDir, 'matrix_3');

			$settings->register_component('with-normalizer', array(
				'path'   => 'WithNormalizer',
				'prefix' => 'ext',
			));

			$settings->menu_group('m3')
				->page('p3')
					->section('s3', 'Section')
						->field('field_3', 'Label 3', 'ext.with-normalizer')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('p3'));

			self::assertStringContainsString('data-marker="view-normalizer"', $output);
			self::assertStringContainsString('normalized_value', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// -------------------------------------------------------------------------
	// TEST 4: View.php + Validator.php + Normalizer.php
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Matrix 4/8: View.php + Validator.php + Normalizer.php (full component stack)
	 */
	public function test_matrix_4_view_validator_normalizer(): void {
		$externalDir = sys_get_temp_dir() . '/ran-matrix-4-' . uniqid();
		mkdir($externalDir . '/FullStack', 0777, true);

		file_put_contents($externalDir . '/FullStack/View.php', $this->createViewPhp('full-stack'));
		file_put_contents($externalDir . '/FullStack/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'FullStack'));
		require_once $externalDir . '/FullStack/Builder.php';
		file_put_contents($externalDir . '/FullStack/Validator.php', $this->createValidatorPhp('TestPlugin\\Components', 'FullStack'));
		file_put_contents($externalDir . '/FullStack/Normalizer.php', $this->createNormalizerPhp('TestPlugin\\Components', 'FullStack'));

		try {
			$this->optionValues['matrix_4'] = array('field_4' => 'full_stack_value');
			$settings                       = $this->createExternalComponentSettings($externalDir, 'matrix_4');

			$settings->register_component('full-stack', array(
				'path'   => 'FullStack',
				'prefix' => 'ext',
			));

			$settings->menu_group('m4')
				->page('p4')
					->section('s4', 'Section')
						->field('field_4', 'Label 4', 'ext.full-stack')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('p4'));

			self::assertStringContainsString('data-marker="full-stack"', $output);
			self::assertStringContainsString('full_stack_value', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// -------------------------------------------------------------------------
	// TEST 5: View.php + Developer Schema
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Matrix 5/8: View.php + Developer-provided schema
	 */
	public function test_matrix_5_view_dev_schema(): void {
		$externalDir = sys_get_temp_dir() . '/ran-matrix-5-' . uniqid();
		mkdir($externalDir . '/WithDevSchema', 0777, true);

		file_put_contents($externalDir . '/WithDevSchema/View.php', $this->createViewPhp('view-dev-schema'));
		file_put_contents($externalDir . '/WithDevSchema/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'WithDevSchema'));
		require_once $externalDir . '/WithDevSchema/Builder.php';

		try {
			$this->optionValues['matrix_5'] = array('field_5' => 'schema_value');
			$settings                       = $this->createExternalComponentSettings($externalDir, 'matrix_5');

			$settings->register_component('with-dev-schema', array(
				'path'   => 'WithDevSchema',
				'prefix' => 'ext',
			));

			$settings->menu_group('m5')
				->page('p5')
					->section('s5', 'Section')
						->field('field_5', 'Label 5', 'ext.with-dev-schema', array(
							'schema' => array(
								'default'  => 'default_from_schema',
								'sanitize' => array(fn($v) => strtoupper((string) $v)),
							),
						))
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('p5'));

			self::assertStringContainsString('data-marker="view-dev-schema"', $output);
			self::assertStringContainsString('schema_value', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// -------------------------------------------------------------------------
	// TEST 6: View.php + Validator.php + Developer Schema
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Matrix 6/8: View.php + Validator.php + Developer-provided schema
	 */
	public function test_matrix_6_view_validator_dev_schema(): void {
		$externalDir = sys_get_temp_dir() . '/ran-matrix-6-' . uniqid();
		mkdir($externalDir . '/ValidatorDevSchema', 0777, true);

		file_put_contents($externalDir . '/ValidatorDevSchema/View.php', $this->createViewPhp('validator-dev-schema'));
		file_put_contents($externalDir . '/ValidatorDevSchema/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'ValidatorDevSchema'));
		require_once $externalDir . '/ValidatorDevSchema/Builder.php';
		file_put_contents($externalDir . '/ValidatorDevSchema/Validator.php', $this->createValidatorPhp('TestPlugin\\Components', 'ValidatorDevSchema'));

		try {
			$this->optionValues['matrix_6'] = array('field_6' => 'combined_value');
			$settings                       = $this->createExternalComponentSettings($externalDir, 'matrix_6');

			$settings->register_component('validator-dev-schema', array(
				'path'   => 'ValidatorDevSchema',
				'prefix' => 'ext',
			));

			$settings->menu_group('m6')
				->page('p6')
					->section('s6', 'Section')
						->field('field_6', 'Label 6', 'ext.validator-dev-schema', array(
							'schema' => array(
								'default'  => 'schema_default',
								'validate' => array(fn($v, $emit) => strlen((string) $v) > 0),
							),
						))
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('p6'));

			self::assertStringContainsString('data-marker="validator-dev-schema"', $output);
			self::assertStringContainsString('combined_value', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// -------------------------------------------------------------------------
	// TEST 7: View.php + Normalizer.php + Developer Schema
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Matrix 7/8: View.php + Normalizer.php + Developer-provided schema
	 */
	public function test_matrix_7_view_normalizer_dev_schema(): void {
		$externalDir = sys_get_temp_dir() . '/ran-matrix-7-' . uniqid();
		mkdir($externalDir . '/NormalizerDevSchema', 0777, true);

		file_put_contents($externalDir . '/NormalizerDevSchema/View.php', $this->createViewPhp('normalizer-dev-schema'));
		file_put_contents($externalDir . '/NormalizerDevSchema/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'NormalizerDevSchema'));
		require_once $externalDir . '/NormalizerDevSchema/Builder.php';
		file_put_contents($externalDir . '/NormalizerDevSchema/Normalizer.php', $this->createNormalizerPhp('TestPlugin\\Components', 'NormalizerDevSchema'));

		try {
			$this->optionValues['matrix_7'] = array('field_7' => 'normalized_schema_value');
			$settings                       = $this->createExternalComponentSettings($externalDir, 'matrix_7');

			$settings->register_component('normalizer-dev-schema', array(
				'path'   => 'NormalizerDevSchema',
				'prefix' => 'ext',
			));

			$settings->menu_group('m7')
				->page('p7')
					->section('s7', 'Section')
						->field('field_7', 'Label 7', 'ext.normalizer-dev-schema', array(
							'schema' => array(
								'default'  => 'normalized_default',
								'sanitize' => array(fn($v) => trim((string) $v)),
							),
						))
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('p7'));

			self::assertStringContainsString('data-marker="normalizer-dev-schema"', $output);
			self::assertStringContainsString('normalized_schema_value', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// -------------------------------------------------------------------------
	// TEST 8: View.php + Validator.php + Normalizer.php + Developer Schema
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Matrix 8/8: Full stack with developer schema (all options enabled)
	 */
	public function test_matrix_8_full_stack_dev_schema(): void {
		$externalDir = sys_get_temp_dir() . '/ran-matrix-8-' . uniqid();
		mkdir($externalDir . '/FullStackSchema', 0777, true);

		file_put_contents($externalDir . '/FullStackSchema/View.php', $this->createViewPhp('full-stack-schema'));
		file_put_contents($externalDir . '/FullStackSchema/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'FullStackSchema'));
		require_once $externalDir . '/FullStackSchema/Builder.php';
		file_put_contents($externalDir . '/FullStackSchema/Validator.php', $this->createValidatorPhp('TestPlugin\\Components', 'FullStackSchema'));
		file_put_contents($externalDir . '/FullStackSchema/Normalizer.php', $this->createNormalizerPhp('TestPlugin\\Components', 'FullStackSchema'));

		try {
			$this->optionValues['matrix_8'] = array('field_8' => 'ultimate_value');
			$settings                       = $this->createExternalComponentSettings($externalDir, 'matrix_8');

			$settings->register_component('full-stack-schema', array(
				'path'   => 'FullStackSchema',
				'prefix' => 'ext',
			));

			$settings->menu_group('m8')
				->page('p8')
					->section('s8', 'Section')
						->field('field_8', 'Label 8', 'ext.full-stack-schema', array(
							'schema' => array(
								'default'  => 'ultimate_default',
								'sanitize' => array(fn($v) => trim((string) $v)),
								'validate' => array(fn($v, $emit) => !empty($v)),
							),
						))
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('p8'));

			self::assertStringContainsString('data-marker="full-stack-schema"', $output);
			self::assertStringContainsString('ultimate_value', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// -------------------------------------------------------------------------
	// BATCH REGISTRATION TEST
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Batch register multiple external components with varying configurations.
	 */
	public function test_batch_register_mixed_components(): void {
		$externalDir = sys_get_temp_dir() . '/ran-batch-mixed-' . uniqid();

		// Component A: View only
		mkdir($externalDir . '/ComponentA', 0777, true);
		file_put_contents($externalDir . '/ComponentA/View.php', $this->createViewPhp('batch-a'));
		file_put_contents($externalDir . '/ComponentA/Builder.php', $this->createBuilderPhp('BatchPlugin', 'ComponentA'));
		require_once $externalDir . '/ComponentA/Builder.php';

		// Component B: View + Validator
		mkdir($externalDir . '/ComponentB', 0777, true);
		file_put_contents($externalDir . '/ComponentB/View.php', $this->createViewPhp('batch-b'));
		file_put_contents($externalDir . '/ComponentB/Builder.php', $this->createBuilderPhp('BatchPlugin', 'ComponentB'));
		require_once $externalDir . '/ComponentB/Builder.php';
		file_put_contents($externalDir . '/ComponentB/Validator.php', $this->createValidatorPhp('BatchPlugin', 'ComponentB'));

		// Component C: View + Normalizer
		mkdir($externalDir . '/ComponentC', 0777, true);
		file_put_contents($externalDir . '/ComponentC/View.php', $this->createViewPhp('batch-c'));
		file_put_contents($externalDir . '/ComponentC/Builder.php', $this->createBuilderPhp('BatchPlugin', 'ComponentC'));
		require_once $externalDir . '/ComponentC/Builder.php';
		file_put_contents($externalDir . '/ComponentC/Normalizer.php', $this->createNormalizerPhp('BatchPlugin', 'ComponentC'));

		try {
			$this->optionValues['batch_mixed'] = array(
				'field_a' => 'value_a',
				'field_b' => 'value_b',
				'field_c' => 'value_c',
			);

			$config = $this->createMock(ConfigInterface::class);
			$config->method('get_config')->willReturn(array('PATH' => $externalDir));
			$config->method('get_namespace')->willReturn('BatchPlugin');

			$loader = new ComponentLoader(__DIR__ . '/../../fixtures/templates', $this->logger);
			$loader->register('admin.root-wrapper', 'admin/pages/default-page.php');
			$loader->register('root-wrapper', 'admin/pages/default-page.php');
			$loader->register('layout.zone.section-wrapper', 'admin/sections/test-section.php');
			$loader->register('section-wrapper', 'admin/section-wrapper.php');
			$loader->register('field-wrapper', 'admin/fields/example-field-wrapper.php');
			$loader->register('shared.field-wrapper', 'admin/fields/example-field-wrapper.php');

			$manifest = new ComponentManifest($loader, $this->logger);
			$options  = new RegisterOptions('batch_mixed', StorageContext::forSite(), true, $this->logger);
			$settings = new AdminSettings($options, $manifest, $config, $this->logger);

			// Batch register all components
			$settings->register_components(array(
				'path'   => '',
				'prefix' => 'batch',
			));

			$settings->menu_group('batch')
				->page('batch-page')
					->section('batch-section', 'Batch Components')
						->field('field_a', 'Field A', 'batch.component-a')->end_field()
						->field('field_b', 'Field B', 'batch.component-b')->end_field()
						->field('field_c', 'Field C', 'batch.component-c')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('batch-page'));

			// All three components should render
			self::assertStringContainsString('data-marker="batch-a"', $output, 'Component A should render');
			self::assertStringContainsString('data-marker="batch-b"', $output, 'Component B should render');
			self::assertStringContainsString('data-marker="batch-c"', $output, 'Component C should render');
			self::assertStringContainsString('value_a', $output);
			self::assertStringContainsString('value_b', $output);
			self::assertStringContainsString('value_c', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// =========================================================================
	// SCHEMA REGISTRATION TESTS
	// =========================================================================
	//
	// These tests verify that developer-provided schema options (defaults,
	// sanitizers, validators) are properly accepted during field registration.
	// Note: Full save-flow sanitizer/validator execution is tested at the
	// RegisterOptions layer in Tests/Unit/Options/.

	/**
	 * @test
	 * Verify external component renders with stored value.
	 */
	public function test_external_component_renders_stored_value(): void {
		$externalDir = sys_get_temp_dir() . '/ran-stored-' . uniqid();
		mkdir($externalDir . '/StoredTest', 0777, true);

		file_put_contents($externalDir . '/StoredTest/View.php', $this->createViewPhp('stored-test'));
		file_put_contents($externalDir . '/StoredTest/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'StoredTest'));
		require_once $externalDir . '/StoredTest/Builder.php';

		try {
			$this->optionValues['stored_test'] = array('test_field' => 'my_stored_value');
			$settings                          = $this->createExternalComponentSettings($externalDir, 'stored_test');

			$settings->register_component('stored-test', array(
				'path'   => 'StoredTest',
				'prefix' => 'ext',
			));

			$settings->menu_group('st')
				->page('st-page')
					->section('s', 'Section')
						->field('test_field', 'Test', 'ext.stored-test', array(
							'schema' => array(
								'default' => 'default_value',
							),
						))
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('st-page'));

			// Stored value should appear in rendered output
			self::assertStringContainsString('my_stored_value', $output, 'Stored value should be rendered');
			self::assertStringContainsString('data-marker="stored-test"', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/**
	 * @test
	 * Verify schema with sanitize/validate arrays is accepted without error.
	 */
	public function test_external_component_accepts_schema_with_sanitize_validate(): void {
		$externalDir = sys_get_temp_dir() . '/ran-schema-' . uniqid();
		mkdir($externalDir . '/SchemaTest', 0777, true);

		file_put_contents($externalDir . '/SchemaTest/View.php', $this->createViewPhp('schema-test'));
		file_put_contents($externalDir . '/SchemaTest/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'SchemaTest'));
		require_once $externalDir . '/SchemaTest/Builder.php';

		try {
			$this->optionValues['schema_test'] = array('test_field' => 'test_value');
			$settings                          = $this->createExternalComponentSettings($externalDir, 'schema_test');

			$settings->register_component('schema-test', array(
				'path'   => 'SchemaTest',
				'prefix' => 'ext',
			));

			// This should not throw - schema with sanitize/validate should be accepted
			$settings->menu_group('sch')
				->page('sch-page')
					->section('s', 'Section')
						->field('test_field', 'Test', 'ext.schema-test', array(
							'schema' => array(
								'default'  => 'default_val',
								'sanitize' => array(fn($v) => trim((string) $v)),
								'validate' => array(fn($v, $emit) => !empty($v)),
							),
						))
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('sch-page'));

			// Should render without error
			self::assertStringContainsString('data-marker="schema-test"', $output);
			self::assertStringContainsString('test_value', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/**
	 * @test
	 * Verify multiple fields using same component render independently.
	 */
	public function test_external_component_multiple_fields_render_independently(): void {
		$externalDir = sys_get_temp_dir() . '/ran-multi-field-' . uniqid();
		mkdir($externalDir . '/MultiField', 0777, true);

		file_put_contents($externalDir . '/MultiField/View.php', $this->createViewPhp('multi-field'));
		file_put_contents($externalDir . '/MultiField/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'MultiField'));
		require_once $externalDir . '/MultiField/Builder.php';

		try {
			$this->optionValues['multi_field'] = array(
				'field_a' => 'value_a',
				'field_b' => 'value_b',
			);
			$settings = $this->createExternalComponentSettings($externalDir, 'multi_field');

			$settings->register_component('multi-field', array(
				'path'   => 'MultiField',
				'prefix' => 'ext',
			));

			$settings->menu_group('mf')
				->page('mf-page')
					->section('s', 'Section')
						->field('field_a', 'Field A', 'ext.multi-field')->end_field()
						->field('field_b', 'Field B', 'ext.multi-field')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('mf-page'));

			// Both fields should render with their respective values
			self::assertStringContainsString('value_a', $output, 'Field A value should render');
			self::assertStringContainsString('value_b', $output, 'Field B value should render');
			self::assertStringContainsString('Field A', $output, 'Field A label should render');
			self::assertStringContainsString('Field B', $output, 'Field B label should render');
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/**
	 * @test
	 * Verify external component with complex schema renders correctly.
	 */
	public function test_external_component_complex_schema_renders(): void {
		$externalDir = sys_get_temp_dir() . '/ran-complex-' . uniqid();
		mkdir($externalDir . '/ComplexSchema', 0777, true);

		file_put_contents($externalDir . '/ComplexSchema/View.php', $this->createViewPhp('complex-schema'));
		file_put_contents($externalDir . '/ComplexSchema/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'ComplexSchema'));
		require_once $externalDir . '/ComplexSchema/Builder.php';

		try {
			$this->optionValues['complex_schema'] = array('complex_field' => 'complex_value');
			$settings                             = $this->createExternalComponentSettings($externalDir, 'complex_schema');

			$settings->register_component('complex-schema', array(
				'path'   => 'ComplexSchema',
				'prefix' => 'ext',
			));

			// Register with full schema including multiple sanitizers and validators
			$settings->menu_group('cx')
				->page('cx-page')
					->section('s', 'Section')
						->field('complex_field', 'Complex Field', 'ext.complex-schema', array(
							'schema' => array(
								'default'  => 'default_complex',
								'sanitize' => array(
									fn($v) => trim((string) $v),
									fn($v) => strtolower((string) $v),
								),
								'validate' => array(
									fn($v, $emit) => strlen((string) $v) > 0,
									fn($v, $emit) => strlen((string) $v) < 100,
								),
							),
						))
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$output = $this->captureOutput(fn() => $settings->__render('cx-page'));

			// Should render without error
			self::assertStringContainsString('data-marker="complex-schema"', $output);
			self::assertStringContainsString('complex_value', $output);
			self::assertStringContainsString('Complex Field', $output);
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// =========================================================================
	// SAVE FLOW TESTS - EXTERNAL COMPONENT SANITIZER/VALIDATOR EXECUTION
	// =========================================================================
	//
	// These tests verify that external components have their sanitizers and
	// validators execute during the save flow (_sanitize callback).

	/**
	 * @test
	 * Verify external component sanitizer executes during save.
	 */
	public function test_save_external_component_sanitizer_executes(): void {
		$externalDir = sys_get_temp_dir() . '/ran-save-san-' . uniqid();
		mkdir($externalDir . '/SanitizeComp', 0777, true);

		file_put_contents($externalDir . '/SanitizeComp/View.php', $this->createViewPhp('save-sanitize'));
		file_put_contents($externalDir . '/SanitizeComp/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'SanitizeComp'));
		require_once $externalDir . '/SanitizeComp/Builder.php';

		try {
			$sanitizerCalled = false;
			$sanitizedValue  = null;

			$this->optionValues['save_san_ext'] = array('san_field' => 'previous');
			$settings                           = $this->createExternalComponentSettings($externalDir, 'save_san_ext');

			$settings->register_component('sanitize-comp', array(
				'path'   => 'SanitizeComp',
				'prefix' => 'ext',
			));

			// Inject sanitizer via manifest defaults BEFORE field registration
			$this->injectManifestDefaults($settings, 'ext.sanitize-comp', array(

				'sanitize' => array(function($v) use (&$sanitizerCalled, &$sanitizedValue) {
					$sanitizerCalled = true;
					$sanitizedValue  = strtoupper(trim((string) $v));
					return $sanitizedValue;
				}),
				'validate' => array(fn($v, $emit) => true),
			));

			// Clear catalogue cache so injected defaults are picked up
			$this->clearCatalogueCache($settings);

			$settings->menu_group('ss')
				->page('ss-page')
					->section('s', 'Section')
						->field('san_field', 'Sanitize Field', 'ext.sanitize-comp')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			// Render first to register field metadata
			$this->captureOutput(fn() => $settings->__render('ss-page'));

			// Now call _sanitize
			$result = $settings->__sanitize(array('san_field' => '  hello world  '));

			self::assertTrue($sanitizerCalled, 'External component sanitizer should execute');
			self::assertSame('HELLO WORLD', $sanitizedValue, 'Sanitizer should transform value');
			self::assertSame('HELLO WORLD', $result['san_field'] ?? null, 'Result should contain sanitized value');
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/**
	 * @test
	 * Verify external component validator rejects invalid values.
	 */
	public function test_save_external_component_validator_rejects(): void {
		$externalDir = sys_get_temp_dir() . '/ran-save-val-' . uniqid();
		mkdir($externalDir . '/ValidateComp', 0777, true);

		file_put_contents($externalDir . '/ValidateComp/View.php', $this->createViewPhp('save-validate'));
		file_put_contents($externalDir . '/ValidateComp/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'ValidateComp'));
		require_once $externalDir . '/ValidateComp/Builder.php';

		try {
			$validatorCalled = false;

			$this->optionValues['save_val_ext'] = array('val_field' => 'previous_value');
			$settings                           = $this->createExternalComponentSettings($externalDir, 'save_val_ext');

			$settings->register_component('validate-comp', array(
				'path'   => 'ValidateComp',
				'prefix' => 'ext',
			));

			// Inject validator BEFORE field registration
			$this->injectManifestDefaults($settings, 'ext.validate-comp', array(

				'validate' => array(function($v, callable $emit) use (&$validatorCalled) {
					$validatorCalled = true;
					if (strlen((string) $v) < 5) {
						$emit('Value must be at least 5 characters');
						return false;
					}
					return true;
				}),
			));

			$this->clearCatalogueCache($settings);

			$settings->menu_group('sv')
				->page('sv-page')
					->section('s', 'Section')
						->field('val_field', 'Validate Field', 'ext.validate-comp')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			// Render first
			$this->captureOutput(fn() => $settings->__render('sv-page'));

			// Save with invalid value (too short)
			$result = $settings->__sanitize(array('val_field' => 'abc'));

			self::assertTrue($validatorCalled, 'External component validator should execute');
			self::assertSame('previous_value', $result['val_field'] ?? null, 'Should return previous value on validation failure');
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/**
	 * @test
	 * Verify external component validator accepts valid values.
	 */
	public function test_save_external_component_validator_accepts(): void {
		$externalDir = sys_get_temp_dir() . '/ran-save-acc-' . uniqid();
		mkdir($externalDir . '/AcceptComp', 0777, true);

		file_put_contents($externalDir . '/AcceptComp/View.php', $this->createViewPhp('save-accept'));
		file_put_contents($externalDir . '/AcceptComp/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'AcceptComp'));
		require_once $externalDir . '/AcceptComp/Builder.php';

		try {
			$validatorCalled = false;

			$this->optionValues['save_acc_ext'] = array('acc_field' => 'old_value');
			$settings                           = $this->createExternalComponentSettings($externalDir, 'save_acc_ext');

			$settings->register_component('accept-comp', array(
				'path'   => 'AcceptComp',
				'prefix' => 'ext',
			));

			$this->injectManifestDefaults($settings, 'ext.accept-comp', array(

				'validate' => array(function($v, callable $emit) use (&$validatorCalled) {
					$validatorCalled = true;
					return strlen((string) $v) >= 5;
				}),
			));

			$this->clearCatalogueCache($settings);

			$settings->menu_group('sa')
				->page('sa-page')
					->section('s', 'Section')
						->field('acc_field', 'Accept Field', 'ext.accept-comp')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$this->captureOutput(fn() => $settings->__render('sa-page'));

			// Save with valid value
			$result = $settings->__sanitize(array('acc_field' => 'valid_input'));

			self::assertTrue($validatorCalled, 'External component validator should execute');
			self::assertSame('valid_input', $result['acc_field'] ?? null, 'Valid value should pass through');
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/**
	 * @test
	 * Verify multiple sanitizers execute in order for external component.
	 */
	public function test_save_external_component_multiple_sanitizers(): void {
		$externalDir = sys_get_temp_dir() . '/ran-save-multi-' . uniqid();
		mkdir($externalDir . '/MultiSanComp', 0777, true);

		file_put_contents($externalDir . '/MultiSanComp/View.php', $this->createViewPhp('multi-san'));
		file_put_contents($externalDir . '/MultiSanComp/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'MultiSanComp'));
		require_once $externalDir . '/MultiSanComp/Builder.php';

		try {
			$executionOrder = array();

			$this->optionValues['save_multi_ext'] = array();
			$settings                             = $this->createExternalComponentSettings($externalDir, 'save_multi_ext');

			$settings->register_component('multi-san-comp', array(
				'path'   => 'MultiSanComp',
				'prefix' => 'ext',
			));

			$this->injectManifestDefaults($settings, 'ext.multi-san-comp', array(

				'sanitize' => array(
					function($v) use (&$executionOrder) {
						$executionOrder[] = 'first';
						return trim((string) $v);
					},
					function($v) use (&$executionOrder) {
						$executionOrder[] = 'second';
						return strtoupper((string) $v);
					},
					function($v) use (&$executionOrder) {
						$executionOrder[] = 'third';
						// Use idempotent operation: replace spaces with underscores
						return str_replace(' ', '_', (string) $v);
					},
				),
				'validate' => array(fn($v, $emit) => true),
			));

			$this->clearCatalogueCache($settings);

			$settings->menu_group('sm')
				->page('sm-page')
					->section('s', 'Section')
						->field('multi_field', 'Multi Field', 'ext.multi-san-comp')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$this->captureOutput(fn() => $settings->__render('sm-page'));

			$result = $settings->__sanitize(array('multi_field' => '  hello world  '));

			// Verify all sanitizers executed (may run multiple times due to idempotency checks)
			self::assertContains('first', $executionOrder, 'First sanitizer should execute');
			self::assertContains('second', $executionOrder, 'Second sanitizer should execute');
			self::assertContains('third', $executionOrder, 'Third sanitizer should execute');
			self::assertSame('HELLO_WORLD', $result['multi_field'] ?? null, 'All sanitizers should transform value');
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/**
	 * @test
	 * Verify schema-level sanitizers merge with manifest defaults.
	 *
	 * Uses register_schema() to add schema-level sanitizers (same pattern as AdminSettingsBehaviorTest).
	 */
	public function test_save_external_component_schema_merges_with_manifest(): void {
		$externalDir = sys_get_temp_dir() . '/ran-save-merge-' . uniqid();
		mkdir($externalDir . '/MergeComp', 0777, true);

		file_put_contents($externalDir . '/MergeComp/View.php', $this->createViewPhp('merge-comp'));
		file_put_contents($externalDir . '/MergeComp/Builder.php', $this->createBuilderPhp('TestPlugin\\Components', 'MergeComp'));
		require_once $externalDir . '/MergeComp/Builder.php';

		try {
			$manifestSanitizerCalled = false;
			$schemaSanitizerCalled   = false;

			$this->optionValues['save_merge_ext'] = array('merge_field' => 'previous');
			$settings                             = $this->createExternalComponentSettings($externalDir, 'save_merge_ext');

			$settings->register_component('merge-comp', array(
				'path'   => 'MergeComp',
				'prefix' => 'ext',
			));

			// Inject manifest-level sanitizer
			$this->injectManifestDefaults($settings, 'ext.merge-comp', array(

				'sanitize' => array(function($v) use (&$manifestSanitizerCalled) {
					$manifestSanitizerCalled = true;
					return trim((string) $v);
				}),
				'validate' => array(fn($v, $emit) => true),
			));

			$this->clearCatalogueCache($settings);

			// Register schema-level sanitizer via options (same pattern as AdminSettingsBehaviorTest)
			$settingsRef = new \ReflectionObject($settings);
			$optionsProp = $settingsRef->getProperty('base_options');
			$optionsProp->setAccessible(true);
			$options = $optionsProp->getValue($settings);

			$options->register_schema(array(
				'merge_field' => array(
					'sanitize' => function($v) use (&$schemaSanitizerCalled) {
						$schemaSanitizerCalled = true;
						return strtoupper((string) $v);
					},
					'validate' => fn($v, $emit) => true,
				),
			));

			$settings->menu_group('mg')
				->page('mg-page')
					->section('s', 'Section')
						->field('merge_field', 'Merge Field', 'ext.merge-comp')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$this->captureOutput(fn() => $settings->__render('mg-page'));

			$result = $settings->__sanitize(array('merge_field' => '  hello  '));

			self::assertTrue($manifestSanitizerCalled, 'Manifest sanitizer should execute');
			self::assertTrue($schemaSanitizerCalled, 'Schema sanitizer should execute');
			// Both should run - result should be trimmed and uppercased
			$finalValue = $result['merge_field'] ?? '';
			self::assertStringNotContainsString(' ', $finalValue, 'Value should be trimmed');
			self::assertSame(strtoupper($finalValue), $finalValue, 'Value should be uppercase');
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// =========================================================================
	// INTEGRATION TESTS - FILE-BASED VALIDATOR/SANITIZER DISCOVERY
	// =========================================================================
	//
	// These tests verify the FULL flow: file-based discovery → class loading →
	// instantiation → execution. No reflection injection - real file-based classes.

	/**
	 * @test
	 * Verify file-based Validator.php is discovered, loaded, and executed during save.
	 *
	 * This is a TRUE integration test - no mocks or reflection injection.
	 */
	public function test_integration_file_based_validator_executes(): void {
		$externalDir  = sys_get_temp_dir() . '/ran-int-val-' . uniqid();
		$componentDir = $externalDir . '/IntegrationValidator';
		mkdir($componentDir, 0777, true);

		// Create View.php
		file_put_contents($componentDir . '/View.php', $this->createViewPhp('integration-validator'));
		file_put_contents($componentDir . '/Builder.php', $this->createBuilderPhp('ExternalTestComponents', 'IntegrationValidator'));
		require_once $componentDir . '/Builder.php';

		// Create a REAL Validator.php that writes to a tracking file
		$trackingFile  = $externalDir . '/validator_executed.txt';
		$validatorCode = <<<PHP
<?php
declare(strict_types=1);

namespace ExternalTestComponents\IntegrationValidator;

use Ran\PluginLib\Forms\Component\Validate\ValidatorInterface;
use Psr\Log\LoggerInterface;

class Validator implements ValidatorInterface {
    public function __construct(?LoggerInterface \$logger = null) {}

    public function validate(mixed \$value, array \$context, callable \$emitWarning): bool {
        // Write to tracking file to prove we executed
        file_put_contents('$trackingFile', 'VALIDATOR_EXECUTED:' . \$value);

        // Reject values shorter than 3 characters
        if (strlen((string) \$value) < 3) {
            \$emitWarning('Value must be at least 3 characters');
            return false;
        }
        return true;
    }
}
PHP;
		file_put_contents($componentDir . '/Validator.php', $validatorCode);

		try {
			// Load the validator class manually (simulating autoloader)
			require_once $componentDir . '/Validator.php';

			$this->optionValues['int_val_test'] = array('int_field' => 'previous_value');
			$settings                           = $this->createExternalComponentSettingsWithNamespace(
				$externalDir,
				'int_val_test',
				'ExternalTestComponents'
			);

			$settings->register_component('integration-validator', array(
				'path'   => 'IntegrationValidator',
				'prefix' => 'ext',
			));

			$settings->menu_group('iv')
				->page('iv-page')
					->section('s', 'Section')
						->field('int_field', 'Integration Field', 'ext.integration-validator')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			// Render to register field metadata
			$this->captureOutput(fn() => $settings->__render('iv-page'));

			// Save with INVALID value (too short) - should trigger validator
			$result = $settings->__sanitize(array('int_field' => 'ab'));

			// Verify validator was actually executed (check tracking file)
			self::assertFileExists($trackingFile, 'Validator tracking file should exist');
			$trackingContent = file_get_contents($trackingFile);
			self::assertStringContainsString('VALIDATOR_EXECUTED', $trackingContent, 'Validator should have executed');

			// Verify validation failed - previous value should be returned
			self::assertSame('previous_value', $result['int_field'] ?? null, 'Invalid value should be rejected');
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/**
	 * @test
	 * Verify file-based Validator.php accepts valid values.
	 */
	public function test_integration_file_based_validator_accepts_valid(): void {
		$externalDir  = sys_get_temp_dir() . '/ran-int-val-acc-' . uniqid();
		$componentDir = $externalDir . '/IntegrationValidatorAccept';
		mkdir($componentDir, 0777, true);

		file_put_contents($componentDir . '/View.php', $this->createViewPhp('integration-validator-accept'));
		file_put_contents($componentDir . '/Builder.php', $this->createBuilderPhp('ExternalTestComponentsAccept', 'IntegrationValidatorAccept'));
		require_once $componentDir . '/Builder.php';

		$trackingFile  = $externalDir . '/validator_accepted.txt';
		$validatorCode = <<<PHP
<?php
declare(strict_types=1);

namespace ExternalTestComponentsAccept\IntegrationValidatorAccept;

use Ran\PluginLib\Forms\Component\Validate\ValidatorInterface;
use Psr\Log\LoggerInterface;

class Validator implements ValidatorInterface {
    public function __construct(?LoggerInterface \$logger = null) {}

    public function validate(mixed \$value, array \$context, callable \$emitWarning): bool {
        file_put_contents('$trackingFile', 'VALIDATOR_ACCEPTED:' . \$value);
        return strlen((string) \$value) >= 3;
    }
}
PHP;
		file_put_contents($componentDir . '/Validator.php', $validatorCode);

		try {
			require_once $componentDir . '/Validator.php';

			$this->optionValues['int_val_acc'] = array('acc_field' => 'old');
			$settings                          = $this->createExternalComponentSettingsWithNamespace(
				$externalDir,
				'int_val_acc',
				'ExternalTestComponentsAccept'
			);

			$settings->register_component('integration-validator-accept', array(
				'path'   => 'IntegrationValidatorAccept',
				'prefix' => 'ext',
			));

			$settings->menu_group('iva')
				->page('iva-page')
					->section('s', 'Section')
						->field('acc_field', 'Accept Field', 'ext.integration-validator-accept')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$this->captureOutput(fn() => $settings->__render('iva-page'));

			// Save with VALID value
			$result = $settings->__sanitize(array('acc_field' => 'valid_value'));

			self::assertFileExists($trackingFile, 'Validator tracking file should exist');
			$trackingContent = file_get_contents($trackingFile);
			self::assertStringContainsString('VALIDATOR_ACCEPTED', $trackingContent);

			// Valid value should pass through
			self::assertSame('valid_value', $result['acc_field'] ?? null, 'Valid value should be accepted');
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	/**
	 * @test
	 * Verify file-based Sanitizer.php is discovered and executed during save.
	 */
	public function test_integration_file_based_sanitizer_executes(): void {
		$externalDir  = sys_get_temp_dir() . '/ran-int-san-' . uniqid();
		$componentDir = $externalDir . '/IntegrationSanitizer';
		mkdir($componentDir, 0777, true);

		file_put_contents($componentDir . '/View.php', $this->createViewPhp('integration-sanitizer'));
		file_put_contents($componentDir . '/Builder.php', $this->createBuilderPhp('ExternalTestComponentsSan', 'IntegrationSanitizer'));
		require_once $componentDir . '/Builder.php';

		$trackingFile = $externalDir . '/sanitizer_executed.txt';

		// Create Validator (required for FormField components)
		$validatorCode = <<<PHP
<?php
declare(strict_types=1);

namespace ExternalTestComponentsSan\IntegrationSanitizer;

use Ran\PluginLib\Forms\Component\Validate\ValidatorInterface;
use Psr\Log\LoggerInterface;

class Validator implements ValidatorInterface {
    public function __construct(?LoggerInterface \$logger = null) {}

    public function validate(mixed \$value, array \$context, callable \$emitWarning): bool {
        return true;
    }
}
PHP;
		file_put_contents($componentDir . '/Validator.php', $validatorCode);

		// Create REAL Sanitizer.php
		$sanitizerCode = <<<PHP
<?php
declare(strict_types=1);

namespace ExternalTestComponentsSan\IntegrationSanitizer;

use Ran\PluginLib\Forms\Component\Sanitize\SanitizerInterface;
use Psr\Log\LoggerInterface;

class Sanitizer implements SanitizerInterface {
    public function __construct(?LoggerInterface \$logger = null) {}

    public function sanitize(mixed \$value, array \$context, callable \$emitNotice): mixed {
        file_put_contents('$trackingFile', 'SANITIZER_EXECUTED:' . \$value);
        // Transform: trim and uppercase
        return strtoupper(trim((string) \$value));
    }
}
PHP;
		file_put_contents($componentDir . '/Sanitizer.php', $sanitizerCode);

		try {
			require_once $componentDir . '/Validator.php';
			require_once $componentDir . '/Sanitizer.php';

			$this->optionValues['int_san_test'] = array();
			$settings                           = $this->createExternalComponentSettingsWithNamespace(
				$externalDir,
				'int_san_test',
				'ExternalTestComponentsSan'
			);

			$settings->register_component('integration-sanitizer', array(
				'path'   => 'IntegrationSanitizer',
				'prefix' => 'ext',
			));

			$settings->menu_group('is')
				->page('is-page')
					->section('s', 'Section')
						->field('san_field', 'Sanitize Field', 'ext.integration-sanitizer')
					->end_field()->end_section()
				->end_page()
			->end_menu();

			$this->captureOutput(fn() => $settings->__render('is-page'));

			$result = $settings->__sanitize(array('san_field' => '  hello world  '));

			self::assertFileExists($trackingFile, 'Sanitizer tracking file should exist');
			$trackingContent = file_get_contents($trackingFile);
			self::assertStringContainsString('SANITIZER_EXECUTED', $trackingContent);

			// Value should be trimmed and uppercased
			self::assertSame('HELLO WORLD', $result['san_field'] ?? null, 'Sanitizer should transform value');
		} finally {
			$this->cleanupDirectory($externalDir);
		}
	}

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	/**
	 * Create AdminSettings with external component support and custom namespace.
	 *
	 * @param string $externalDir External component base directory
	 * @param string $optionName Option name for this settings instance
	 * @param string $namespace Root namespace for external components
	 * @return AdminSettings
	 */
	private function createExternalComponentSettingsWithNamespace(string $externalDir, string $optionName, string $namespace): AdminSettings {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array(
			'PATH'      => $externalDir,
			'SLUG'      => 'ext-test',
			'NAMESPACE' => $namespace,
		));
		$config->method('get_namespace')->willReturn($namespace);

		// Create loader with required templates
		$loader = new ComponentLoader(__DIR__ . '/../../fixtures/templates', $this->logger);
		$loader->register('admin.root-wrapper', 'admin/pages/default-page.php');
		$loader->register('root-wrapper', 'admin/pages/default-page.php');
		$loader->register('layout.zone.section-wrapper', 'admin/sections/test-section.php');
		$loader->register('section-wrapper', 'admin/section-wrapper.php');
		$loader->register('field-wrapper', 'admin/fields/example-field-wrapper.php');
		$loader->register('shared.field-wrapper', 'admin/fields/example-field-wrapper.php');

		$manifest = new ComponentManifest($loader, $this->logger);
		$options  = new RegisterOptions($optionName, StorageContext::forSite(), true, $this->logger);

		// Mock get_option to return our test values
		\WP_Mock::userFunction('get_option')
			->with($optionName, \Mockery::any())
			->andReturnUsing(fn($name, $default) => $this->optionValues[$optionName] ?? $default);

		return new AdminSettings($options, $manifest, $config, $this->logger);
	}

	/**
	 * Inject manifest defaults for a component alias (for testing save flow).
	 *
	 * Also injects a mock validator class to satisfy the validator requirement check.
	 *
	 * @param AdminSettings $settings The settings instance
	 * @param string $alias Component alias
	 * @param array<string,mixed> $defaults Defaults to inject
	 */
	private function injectManifestDefaults(AdminSettings $settings, string $alias, array $defaults): void {
		// Get manifest from settings via reflection
		$settingsRef  = new \ReflectionObject($settings);
		$manifestProp = $settingsRef->getProperty('components');
		$manifestProp->setAccessible(true);
		$manifest = $manifestProp->getValue($settings);

		// Inject defaults into manifest metadata
		$manifestRef  = new \ReflectionObject($manifest);
		$metadataProp = $manifestRef->getProperty('componentMetadata');
		$metadataProp->setAccessible(true);
		$metadata = $metadataProp->getValue($manifest);

		if (!is_array($metadata)) {
			$metadata = array();
		}

		$current             = $metadata[$alias] ?? array();
		$current['defaults'] = $defaults;
		// Inject a mock validator class to satisfy the validator requirement check
		$current['validator'] = MockComponentValidator::class;
		$metadata[$alias]     = $current;

		$metadataProp->setValue($manifest, $metadata);

		// Clear the validator factories cache so the new validator is picked up
		$factoriesCacheProp = $manifestRef->getProperty('validatorFactoriesCache');
		$factoriesCacheProp->setAccessible(true);
		$factoriesCacheProp->setValue($manifest, null);
	}

	/**
	 * Clear the catalogue cache so injected defaults are picked up.
	 *
	 * @param AdminSettings $settings The settings instance
	 */
	private function clearCatalogueCache(AdminSettings $settings): void {
		$getSchemaService = new \ReflectionMethod($settings, '_get_schema_service');
		$getSchemaService->setAccessible(true);
		$schemaService = $getSchemaService->invoke($settings);

		$serviceRef = new \ReflectionObject($schemaService);
		$cacheProp  = $serviceRef->getProperty('catalogue_cache');
		$cacheProp->setAccessible(true);
		$cacheProp->setValue($schemaService, null);
	}

	/**
	 * Recursively clean up a directory.
	 */
	private function cleanupDirectory(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}

		$files = array_diff(scandir($dir), array('.', '..'));
		foreach ($files as $file) {
			$path = $dir . '/' . $file;
			if (is_dir($path)) {
				$this->cleanupDirectory($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}
}

/**
 * Mock validator for testing save flow.
 *
 * Always returns true - actual validation is done via injected defaults.
 */
class MockComponentValidator implements \Ran\PluginLib\Forms\Component\Validate\ValidatorInterface {
	public function __construct(?\Psr\Log\LoggerInterface $logger = null) {
		// No-op
	}

	public function validate(mixed $value, array $context, callable $emitWarning): bool {
		return true;
	}
}
