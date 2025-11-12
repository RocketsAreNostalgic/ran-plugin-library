<?php

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;
use Ran\PluginLib\EnqueueAccessory\StyleDefinition;
use Ran\PluginLib\Forms\FormsTemplateOverrideResolver;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\FormsAssets;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test FormsServiceSession integration with FormsTemplateOverrideResolver
 *
 * Verifies that FormsServiceSession properly orchestrates the complete pipeline:
 * template resolution → component rendering → asset collection
 *
 * @coversDefaultClass \Ran\PluginLib\Forms\FormsServiceSession
 * @covers \Ran\PluginLib\Forms\FormsAssets
 * @covers \Ran\PluginLib\Forms\FormsService
 */
class FormsServiceSessionTest extends TestCase {
	private FormsServiceSession $session;
	/** @var ComponentManifest&MockObject */
	private ComponentManifest $manifest;
	/** @var FormsAssets&MockObject */
	private FormsAssets $assets;
	/** @var Logger&MockObject */
	private Logger $logger;

	protected function setUp(): void {
		/** @var ComponentManifest&MockObject $manifest */
		$manifest       = $this->createMock(ComponentManifest::class);
		$this->manifest = $manifest;
		/** @var FormsAssets&MockObject $assets */
		$assets       = $this->createMock(FormsAssets::class);
		$this->assets = $assets;
		/** @var Logger&MockObject $logger */
		$logger       = $this->createMock(Logger::class);
		$this->logger = $logger;

		$this->session = new FormsServiceSession($this->manifest, $this->assets, $this->logger);
	}

	/**
	 * Test FormsServiceSession constructor creates FormsTemplateOverrideResolver
	 *
	 * @covers ::__construct
	 * @covers ::template_resolver
	 */
	public function test_constructor_creates_template_resolver(): void {
		$resolver = $this->session->template_resolver();
		$this->assertInstanceOf(FormsTemplateOverrideResolver::class, $resolver);
	}

	/**
	 * Test constructor sets form-wide defaults when provided
	 *
	 * @covers ::__construct
	 * @covers ::get_form_defaults
	 */
	public function test_constructor_sets_form_defaults(): void {
		$form_defaults = array(
			'field-wrapper'   => 'admin.field-wrapper',
			'section-wrapper' => 'admin.section-wrapper'
		);

		$session = new FormsServiceSession($this->manifest, $this->assets, $this->logger, $form_defaults);

		$this->assertEquals($form_defaults, $session->get_form_defaults());
	}

	/**
	 * Test render_element() orchestrates the complete pipeline
	 *
	 * @covers ::set_form_defaults
	 * @covers ::render_element
	 */
	public function test_render_element_orchestrates_pipeline(): void {
		// Set up form defaults
		$this->session->set_form_defaults(array(
			'field-wrapper' => 'admin.field-wrapper'
		));

		// Mock ComponentRenderResult
		$render_result = new ComponentRenderResult(
			markup: '<div>Test Field</div>',
			submits_data: true,
			component_type: 'input'
		);

		// Expect ComponentManifest to be called with resolved template key
		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->manifest;
		$manifest->expects($this->once())
			->method('render')
			->with('admin.field-wrapper', array('test' => 'context'))
			->willReturn($render_result);

		/** @var FormsAssets&MockObject $assets */
		$assets = $this->assets;
		$assets->expects($this->once())
			->method('ingest')
			->with($render_result);

		// Execute pipeline
		$result = $this->session->render_element('field-wrapper', array('test' => 'context'));

		$this->assertEquals('<div>Test Field</div>', $result);
	}

	/**
	 * Test render_element() uses individual element overrides
	 *
	 * @covers ::set_form_defaults
	 * @covers ::set_individual_element_override
	 * @covers ::render_element
	 */
	public function test_render_element_uses_individual_overrides(): void {
		// Set up form defaults
		$this->session->set_form_defaults(array(
			'field-wrapper' => 'admin.field-wrapper'
		));

		// Set up individual field override (Tier 2)
		$this->session->set_individual_element_override('field', 'special-field', array(
			'field-wrapper' => 'special.field-wrapper'
		));

		// Mock ComponentRenderResult
		$render_result = new ComponentRenderResult(
			markup: '<div>Special Field</div>',
			submits_data: true,
			component_type: 'input'
		);

		// Expect ComponentManifest to be called with individual override template key
		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->manifest;
		$manifest->expects($this->once())
			->method('render')
			->with('special.field-wrapper', array('field_id' => 'special-field'))
			->willReturn($render_result);

		/** @var FormsAssets&MockObject $assets */
		$assets = $this->assets;
		$assets->expects($this->once())
			->method('ingest')
			->with($render_result);

		// Execute pipeline with field context
		$result = $this->session->render_element('field-wrapper', array(), array('field_id' => 'special-field'));

		$this->assertEquals('<div>Special Field</div>', $result);
	}

	/**
	 * Test two-tier precedence: individual overrides take precedence over form defaults
	 *
	 * @covers ::set_form_defaults
	 * @covers ::set_individual_element_override
	 * @covers ::resolve_template
	 */
	public function test_two_tier_precedence(): void {
		// Set up form defaults (Tier 1)
		$this->session->set_form_defaults(array(
			'field-wrapper' => 'form.default-field-wrapper'
		));

		// Set up individual override (Tier 2)
		$this->session->set_individual_element_override('field', 'test-field', array(
			'field-wrapper' => 'individual.override-field-wrapper'
		));

		// Test field with individual override uses Tier 2
		$resolved = $this->session->resolve_template('field-wrapper', array('field_id' => 'test-field'));
		$this->assertEquals('individual.override-field-wrapper', $resolved);

		// Test field without individual override uses Tier 1
		$resolved = $this->session->resolve_template('field-wrapper', array('field_id' => 'other-field'));
		$this->assertEquals('form.default-field-wrapper', $resolved);
	}

	/**
	 * Test form-wide defaults configuration methods
	 *
	 * @covers ::set_form_defaults
	 * @covers ::override_form_defaults
	 * @covers ::get_form_defaults
	 */
	public function test_form_defaults_configuration(): void {
		// Test set_form_defaults
		$defaults = array(
			'field-wrapper'   => 'admin.field',
			'section-wrapper' => 'admin.section'
		);
		$this->session->set_form_defaults($defaults);
		$this->assertEquals($defaults, $this->session->get_form_defaults());

		// Test override_form_defaults
		$overrides = array(
			'section-wrapper' => 'custom.section',
			'group-wrapper'   => 'custom.group'
		);
		$this->session->override_form_defaults($overrides);

		$expected = array(
			'field-wrapper'   => 'admin.field',      // Original
			'section-wrapper' => 'custom.section', // Overridden
			'group-wrapper'   => 'custom.group'      // Added
		);
		$this->assertEquals($expected, $this->session->get_form_defaults());
	}

	/**
	 * Test individual element override configuration methods
	 *
	 * @covers ::set_individual_element_override
	 * @covers ::get_individual_element_overrides
	 */
	public function test_individual_element_override_configuration(): void {
		// Test field overrides
		$field_overrides = array('field-wrapper' => 'special.field');
		$this->session->set_individual_element_override('field', 'test-field', $field_overrides);
		$this->assertEquals($field_overrides, $this->session->get_individual_element_overrides('field', 'test-field'));

		// Test section overrides
		$section_overrides = array('section-wrapper' => 'special.section');
		$this->session->set_individual_element_override('section', 'test-section', $section_overrides);
		$this->assertEquals($section_overrides, $this->session->get_individual_element_overrides('section', 'test-section'));

		// Test group overrides
		$group_overrides = array('group-wrapper' => 'special.group');
		$this->session->set_individual_element_override('group', 'test-group', $group_overrides);
		$this->assertEquals($group_overrides, $this->session->get_individual_element_overrides('group', 'test-group'));

		// Test root overrides
		$root_overrides = array('root-wrapper' => 'special.root');
		$this->session->set_individual_element_override('root', 'test-root', $root_overrides);
		$this->assertEquals($root_overrides, $this->session->get_individual_element_overrides('root', 'test-root'));
	}

	/**
	 * @covers ::get_individual_element_overrides
	 * @dataProvider provide_element_override_types
	 */
	public function test_get_individual_element_overrides_routes_to_template_resolver(
		string $elementType,
		string $setter,
		string $getter,
		string $key
	): void {
		$templateResolver = $this->session->template_resolver();
		$overrides        = array($key => 'custom.' . $elementType);

		$templateResolver->{$setter}('example-id', $overrides);

		$this->assertSame($overrides, $this->session->get_individual_element_overrides($elementType, 'example-id'));
		$this->assertSame($overrides, $templateResolver->{$getter}('example-id'));
	}

	/**
	 * @covers ::get_individual_element_overrides
	 */
	public function test_get_individual_element_overrides_throws_for_invalid_type(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid element type: 'invalid'. Must be one of: field, section, group, root");

		$this->session->get_individual_element_overrides('invalid', 'any-id');
	}

	/**
	 * @return array<string, array{string,string,string,string}>
	 */
	public function provide_element_override_types(): array {
		return array(
			'field'   => array('field', 'set_field_template_overrides', 'get_field_template_overrides', 'field-wrapper'),
			'section' => array('section', 'set_section_template_overrides', 'get_section_template_overrides', 'section-wrapper'),
			'group'   => array('group', 'set_group_template_overrides', 'get_group_template_overrides', 'group-wrapper'),
			'root'    => array('root', 'set_root_template_overrides', 'get_root_template_overrides', 'root-wrapper'),
		);
	}

	/**
	 * Test invalid element type throws exception
	 *
	 * @covers ::set_individual_element_override
	 */
	public function test_invalid_element_type_throws_exception(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Invalid element type: 'invalid'. Must be one of: field, section, group, root");

		$this->session->set_individual_element_override('invalid', 'test-id', array('template' => 'value'));
	}

	/**
	 * Test template_resolver() accessor method
	 *
	 * @covers ::template_resolver
	 */
	public function test_template_resolver_accessor(): void {
		$resolver = $this->session->template_resolver();
		$this->assertInstanceOf(FormsTemplateOverrideResolver::class, $resolver);

		// Test that it's the same instance
		$this->assertSame($resolver, $this->session->template_resolver());
	}

	/**
	 * Test existing manifest() and assets() accessors still work
	 *
	 * @covers ::manifest
	 * @covers ::assets
	 */
	public function test_existing_accessors_work(): void {
		$this->assertSame($this->manifest, $this->session->manifest());
		$this->assertSame($this->assets, $this->session->assets());
	}

	/**
	 * Test existing render_component() method still works
	 *
	 * @covers ::render_component
	 */
	public function test_existing_render_component_still_works(): void {
		// Mock ComponentRenderResult
		$render_result = new ComponentRenderResult(
			markup: '<div>Component</div>',
			submits_data: false,
			component_type: 'input'
		);

		// Expect ComponentManifest to be called directly
		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->manifest;
		$manifest->expects($this->once())
			->method('render')
			->with('test-component', array('context' => 'data'))
			->willReturn($render_result);

		/** @var FormsAssets&MockObject $assets */
		$assets = $this->assets;
		$assets->expects($this->once())
			->method('ingest')
			->with($render_result);

		// Execute existing method
		$result = $this->session->render_component('test-component', array('context' => 'data'));

		$this->assertEquals('<div>Component</div>', $result);
	}

	/**
	 * @covers ::render_field_component
	 */
	public function test_render_field_component_adds_private_context_and_renders(): void {
		$component         = 'fields.input';
		$field_id          = 'example-field';
		$label             = 'Example Field';
		$additionalContext = array('data' => 'value');
		$values            = array('foo' => 'bar');

		$expectedContext = array_merge($additionalContext, array(
		    '_field_id' => $field_id,
		    '_label'    => $label,
		    '_values'   => $values,
		));

		$render_result = new ComponentRenderResult(
			markup: '<div>Rendered</div>',
			submits_data: true,
			component_type: 'input'
		);

		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->manifest;
		$manifest->expects($this->once())
			->method('render')
			->with($component, $expectedContext)
			->willReturn($render_result);

		/** @var FormsAssets&MockObject $assets */
		$assets = $this->assets;
		$assets->expects($this->once())
			->method('ingest')
			->with($render_result);

		$result = $this->session->render_field_component($component, $field_id, $label, $additionalContext, $values);

		$this->assertSame('<div>Rendered</div>', $result);
	}

	/**
	 * Test complete pipeline integration scenario
	 *
	 * @covers ::set_form_defaults
	 * @covers ::override_form_defaults
	 * @covers ::set_individual_element_override
	 * @covers ::render_element
	 */
	public function test_complete_pipeline_integration(): void {
		// Scenario: Admin form with custom field template

		// Step 1: Set form-wide defaults (what AdminSettings would do)
		$this->session->set_form_defaults(array(
			'root-wrapper'  => 'admin.default-page',
			'field-wrapper' => 'admin.field-wrapper',
		));

		// Step 2: Developer overrides some form-wide defaults
		$this->session->override_form_defaults(array(
			'section-wrapper' => 'custom.section-wrapper'
		));

		// Step 3: Individual field gets special treatment
		$this->session->set_individual_element_override('field', 'special-field', array(
			'field-wrapper' => 'special.field-wrapper'
		));

		// Mock ComponentRenderResults
		$regular_field_result = new ComponentRenderResult(
			markup: '<div>Regular Field</div>',
			submits_data: true,
			component_type: 'input'
		);

		$special_field_result = new ComponentRenderResult(
			markup: '<div>Special Field</div>',
			submits_data: true,
			component_type: 'input'
		);

		$section_result = new ComponentRenderResult(
			markup: '<section>Custom Section</section>',
			submits_data: false,
			component_type: 'layout_wrapper'
		);

		// Set up expectations
		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->manifest;
		$manifest->expects($this->exactly(3))
			->method('render')
			->withConsecutive(
				array('admin.field-wrapper', array('field_id' => 'regular-field')),
				array('special.field-wrapper', array('field_id' => 'special-field')),
				array('custom.section-wrapper', array('section_id' => 'any-section'))
			)
			->willReturnOnConsecutiveCalls(
				$regular_field_result,
				$special_field_result,
				$section_result
			);

		/** @var FormsAssets&MockObject $assets */
		$assets = $this->assets;
		$assets->expects($this->exactly(3))
			->method('ingest');

		// Test regular field (uses form-wide default)
		$result = $this->session->render_element('field-wrapper', array(), array('field_id' => 'regular-field'));
		$this->assertEquals('<div>Regular Field</div>', $result);

		// Test special field (uses individual override)
		$result = $this->session->render_element('field-wrapper', array(), array('field_id' => 'special-field'));
		$this->assertEquals('<div>Special Field</div>', $result);

		// Test section (uses developer override of form-wide default)
		$result = $this->session->render_element('section-wrapper', array(), array('section_id' => 'any-section'));
		$this->assertEquals('<section>Custom Section</section>', $result);
	}

	/**
	 * @covers ::enqueue_assets
	 */
	public function test_enqueue_assets_returns_early_when_no_assets(): void {
		$assets = $this->assets;
		$assets->expects($this->once())
			->method('has_assets')
			->willReturn(false);
		$assets->expects($this->never())->method('styles');
		$assets->expects($this->never())->method('scripts');
		$assets->expects($this->never())->method('requires_media');

		$this->session->enqueue_assets();
	}

	/**
	 * @covers ::enqueue_assets
	 */
	public function test_enqueue_assets_registers_and_enqueues_assets(): void {
		$styleDefinition = StyleDefinition::from_array(array(
			'handle'  => 'sample-style',
			'src'     => 'https://example.com/style.css',
			'deps'    => array('wp-components'),
			'version' => '1.0.0',
		));

		$scriptDefinition = ScriptDefinition::from_array(array(
			'handle'  => 'sample-script',
			'src'     => 'https://example.com/script.js',
			'deps'    => array('wp-hooks'),
			'version' => '1.0.0',
			'data'    => array(
				'in_footer' => true,
				'localize'  => array(
					'Example' => array('foo' => 'bar'),
				),
			),
		));

		$assets = new class($styleDefinition, $scriptDefinition) extends FormsAssets {
			public function __construct(private StyleDefinition $style, private ScriptDefinition $script) {
			}

			public function has_assets(): bool {
				return true;
			}

			public function styles(): array {
				return array($this->style->handle => $this->style);
			}

			public function scripts(): array {
				return array($this->script->handle => $this->script);
			}

			public function requires_media(): bool {
				return true;
			}
		};

		$logger   = $this->logger;
		$manifest = $this->manifest;

		$session = new class($manifest, $assets, $logger) extends FormsServiceSession {
			public array $registered_styles  = array();
			public array $enqueued_styles    = array();
			public array $registered_scripts = array();
			public array $localized_scripts  = array();
			public array $enqueued_scripts   = array();
			public int $media_calls          = 0;

			public function _do_wp_register_style(): void {
				$args                      = func_get_args();
				$this->registered_styles[] = array(
					'handle' => $args[0] ?? null,
					'src'    => $args[1] ?? null,
					'deps'   => $args[2] ?? null,
					'ver'    => $args[3] ?? null,
					'media'  => $args[4] ?? null,
				);
			}

			public function _do_wp_enqueue_style(string $handle, string $src = '', array $deps = array(), string|bool|null $ver = false, string $media = 'all'): void {
				$this->enqueued_styles[] = compact('handle', 'src', 'deps', 'ver', 'media');
			}

			public function _do_wp_register_script(string $handle, string|false $src, string|array $deps = array(), string|bool|null $ver = false, array|bool $args = array()): bool {
				$this->registered_scripts[] = compact('handle', 'src', 'deps', 'ver', 'args');
				return true;
			}

			public function _do_wp_localize_script(string $handle, string $object_name, array $l10n): bool {
				$this->localized_scripts[] = compact('handle', 'object_name', 'l10n');
				return true;
			}

			public function _do_wp_enqueue_script(string $handle, string $src = '', string|array $deps = array(), string|bool|null $ver = false, array|bool $args = array()): void {
				$this->enqueued_scripts[] = compact('handle', 'src', 'deps', 'ver', 'args');
			}

			public function _do_wp_enqueue_media(array $args = array()): void {
				$this->media_calls++;
			}
		};

		$session->enqueue_assets();

		$registeredStyle = $session->registered_styles[0] ?? null;
		$this->assertSame(
			array('handle' => 'sample-style', 'src' => 'https://example.com/style.css', 'deps' => array('wp-components'), 'ver' => '1.0.0', 'media' => null),
			$registeredStyle
		);
		$this->assertSame('sample-style', $session->enqueued_styles[0]['handle'] ?? null);

		$registeredScript = $session->registered_scripts[0] ?? null;
		$this->assertSame(
			array('handle' => 'sample-script', 'src' => 'https://example.com/script.js', 'deps' => array('wp-hooks'), 'ver' => '1.0.0', 'args' => true),
			$registeredScript
		);
		$this->assertSame('sample-script', $session->enqueued_scripts[0]['handle'] ?? null);
		$this->assertSame(
			array('handle' => 'sample-script', 'object_name' => 'Example', 'l10n' => array('foo' => 'bar')),
			$session->localized_scripts[0] ?? null
		);
		$this->assertSame(1, $session->media_calls);
	}
}
