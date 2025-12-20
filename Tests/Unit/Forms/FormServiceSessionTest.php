<?php

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Forms\FormsTemplateOverrideResolver;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\EnqueueAccessory\StyleDefinition;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test FormsServiceSession integration with FormsTemplateOverrideResolver
 *
 * Verifies that FormsServiceSession properly orchestrates the complete pipeline:
 * template resolution → component rendering → asset collection
 *
 * @coversDefaultClass \Ran\PluginLib\Forms\FormsServiceSession
 * @covers \Ran\PluginLib\Forms\FormsService
 */
class FormsServiceSessionTest extends TestCase {
	private FormsServiceSession $session;
	/** @var ComponentManifest&MockObject */
	private ComponentManifest $manifest;
	/** @var Logger&MockObject */
	private Logger $logger;
	private FormsTemplateOverrideResolver $resolver;

	public function test_ingest_component_result_emits_single_session_log(): void {
		$logger = new CollectingLogger();
		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->createMock(ComponentManifest::class);
		$manifest->method('builder_classes')->willReturn(array());
		$resolver = new FormsTemplateOverrideResolver($logger);
		$session  = new FormsServiceSession($manifest, $resolver, $logger);

		$result = new ComponentRenderResult('<div>Logged</div>');

		$manifest->expects($this->once())
			->method('render')
			->with('fields.example', array('field_id' => 'example-field'))
			->willReturn($result);

		$session->render_component('fields.example', array('field_id' => 'example-field'));
		$this->assertSame(array('fields.example'), $session->get_used_component_aliases());
	}

	public function test_enqueue_assets_delegates_to_manifest(): void {
		$render_result = new ComponentRenderResult(markup: '<div>Test Field</div>');
		$this->manifest->expects($this->once())
			->method('render')
			->with('admin.field-wrapper', array('test' => 'context'))
			->willReturn($render_result);

		$this->manifest->expects($this->once())
			->method('enqueue_assets_for_aliases')
			->with(array('admin.field-wrapper'));

		$this->session->set_form_defaults(array('field-wrapper' => 'admin.field-wrapper'));
		$this->session->render_element('field-wrapper', array('test' => 'context'));
		$this->session->enqueue_assets();
	}

	protected function setUp(): void {
		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->createMock(ComponentManifest::class);
		$manifest->method('builder_classes')->willReturn(array());
		$this->manifest = $manifest;
		/** @var Logger&MockObject $logger */
		$logger         = $this->createMock(Logger::class);
		$this->logger   = $logger;
		$this->resolver = new FormsTemplateOverrideResolver($this->logger);

		$this->session = new FormsServiceSession($this->manifest, $this->resolver, $this->logger);
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

		$resolver = new FormsTemplateOverrideResolver($this->logger);
		$session  = new FormsServiceSession($this->manifest, $resolver, $this->logger, $form_defaults);

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
		);

		// Expect ComponentManifest to be called with resolved template key
		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->manifest;
		$manifest->expects($this->once())
			->method('render')
			->with('admin.field-wrapper', array('test' => 'context'))
			->willReturn($render_result);

		// Execute pipeline
		$result = $this->session->render_element('field-wrapper', array('test' => 'context'));
		$this->assertSame(array('admin.field-wrapper'), $this->session->get_used_component_aliases());

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
			markup: '<div>Special Field</div>'
		);

		// Expect ComponentManifest to be called with individual override template key
		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->manifest;
		$manifest->expects($this->once())
			->method('render')
			->with('special.field-wrapper', array('field_id' => 'special-field'))
			->willReturn($render_result);

		// Execute pipeline with field context
		$result = $this->session->render_element('field-wrapper', array(), array('field_id' => 'special-field'));
		$this->assertSame(array('special.field-wrapper'), $this->session->get_used_component_aliases());

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
	 * @covers ::manifest
	 */
	public function test_manifest_accessor_returns_manifest(): void {
		$this->assertSame($this->manifest, $this->session->manifest());
	}

	/**
	 * Test existing render_component() method still works
	 *
	 * @covers ::render_component
	 */
	public function test_existing_render_component_still_works(): void {
		// Mock ComponentRenderResult
		$render_result = new ComponentRenderResult(
			markup: '<div>Component</div>'
		);

		// Expect ComponentManifest to be called directly
		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->manifest;
		$manifest->expects($this->once())
			->method('render')
			->with('test-component', array('context' => 'data'))
			->willReturn($render_result);

		// Execute existing method
		$result = $this->session->render_component('test-component', array('context' => 'data'));

		$this->assertEquals('<div>Component</div>', $result);
		$this->assertSame(array('test-component'), $this->session->get_used_component_aliases());
	}

	/**
	 * @covers ::render_field_component
	 */
	public function test_render_field_component_adds_private_context_and_renders(): void {
		$component         = 'fields.input';
		$field_id          = 'example-field';
		$label             = 'Example Field';
		$additionalContext = array('data' => 'value');

		$expectedContext = array_merge($additionalContext, array(
		    'field_id' => $field_id,
		    'label'    => $label,
		));

		$render_result = new ComponentRenderResult(
			markup: '<div>Rendered</div>'
		);

		/** @var ComponentManifest&MockObject $manifest */
		$manifest = $this->manifest;
		$manifest->expects($this->once())
			->method('render')
			->with($component, $expectedContext)
			->willReturn($render_result);

		$result = $this->session->render_field_component($component, $field_id, $label, $additionalContext);

		$this->assertSame('<div>Rendered</div>', $result);
		$this->assertSame(array($component), $this->session->get_used_component_aliases());
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
			markup: '<div>Regular Field</div>'
		);

		$special_field_result = new ComponentRenderResult(
			markup: '<div>Special Field</div>'
		);

		$section_result = new ComponentRenderResult(
			markup: '<section>Custom Section</section>'
		);

		// Set up expectations using callback pattern (replaces deprecated withConsecutive)
		/** @var ComponentManifest&MockObject $manifest */
		$manifest        = $this->manifest;
		$invocationCount = 0;
		$expectedCalls   = array(
			array('admin.field-wrapper', array('field_id' => 'regular-field'), $regular_field_result),
			array('special.field-wrapper', array('field_id' => 'special-field'), $special_field_result),
			array('custom.section-wrapper', array('section_id' => 'any-section'), $section_result),
		);
		$manifest->expects($this->exactly(3))
			->method('render')
			->willReturnCallback(function ($component, $context) use (&$invocationCount, $expectedCalls) {
				$expected = $expectedCalls[$invocationCount];
				$this->assertSame($expected[0], $component, "Call {$invocationCount}: component mismatch");
				$this->assertSame($expected[1], $context, "Call {$invocationCount}: context mismatch");
				$result = $expected[2];
				$invocationCount++;
				return $result;
			});

		// Test regular field (uses form-wide default)
		$result = $this->session->render_element('field-wrapper', array(), array('field_id' => 'regular-field'));
		$this->assertEquals('<div>Regular Field</div>', $result);

		// Test special field (uses individual override)
		$result = $this->session->render_element('field-wrapper', array(), array('field_id' => 'special-field'));
		$this->assertEquals('<div>Special Field</div>', $result);

		// Test section (uses developer override of form-wide default)
		$result = $this->session->render_element('section-wrapper', array(), array('section_id' => 'any-section'));
		$this->assertEquals('<section>Custom Section</section>', $result);

		$this->assertSame(
			array('admin.field-wrapper', 'custom.section-wrapper', 'special.field-wrapper'),
			$this->session->get_used_component_aliases()
		);
	}

	/**
	 * @covers ::enqueue_assets
	 */
	public function test_enqueue_assets_returns_early_when_no_assets(): void {
		$this->manifest->expects($this->never())
			->method('enqueue_assets_for_aliases');
		$this->session->enqueue_assets();
	}

	/**
	 * @covers ::enqueue_assets
	 */
	public function test_enqueue_assets_registers_and_enqueues_assets(): void {
		$this->manifest->expects($this->once())
			->method('enqueue_assets_for_aliases')
			->with(array('sample.component'));
		$this->session->note_component_used('sample.component');
		$this->session->enqueue_assets();
	}
}
