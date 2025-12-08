<?php
/**
 * Tests for html(), hr(), and element() builder methods.
 *
 * These methods add non-input elements to forms: raw HTML, horizontal rules,
 * and generic element components like buttons.
 *
 * @package Ran\PluginLib\Tests\Unit\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use PHPUnit\Framework\TestCase;
use Ran\PluginLib\Forms\Builders\BuilderContextInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\GroupBuilder;
use Ran\PluginLib\Forms\Builders\FieldsetBuilder;
use Ran\PluginLib\Forms\Builders\HrBuilder;
use Ran\PluginLib\Forms\Builders\GenericElementBuilder;
use Ran\PluginLib\Forms\FormsInterface;

/**
 * @covers \Ran\PluginLib\Forms\Builders\SectionBuilder
 * @covers \Ran\PluginLib\Forms\Builders\GroupBuilder
 * @covers \Ran\PluginLib\Forms\Builders\FieldsetBuilder
 * @covers \Ran\PluginLib\Forms\Builders\HrBuilder
 * @covers \Ran\PluginLib\Forms\Builders\GenericElementBuilder
 * @covers \Ran\PluginLib\Forms\Builders\Traits\SectionBuilderTrait
 */
final class ElementBuilderMethodsTest extends TestCase {
	/**
	 * @var list<array{type:string,payload:array<string,mixed>}> captured builder updates
	 */
	private array $updates;

	protected function setUp(): void {
		parent::setUp();
		$this->updates = array();
	}

	// =========================================================================
	// html() method tests
	// =========================================================================

	public function test_html_with_string_emits_raw_html_field(): void {
		$builder = $this->createSectionBuilder();

		$result = $builder->html('<p>Test content</p>');

		// html() returns the section builder for chaining
		self::assertSame($builder, $result);

		$fieldUpdates = $this->getFieldUpdates();
		self::assertNotEmpty($fieldUpdates, 'Expected field update for html().');

		$lastPayload = $fieldUpdates[count($fieldUpdates) - 1]['payload'];
		self::assertSame('_raw_html', $lastPayload['field_data']['component']);
		self::assertSame('<p>Test content</p>', $lastPayload['field_data']['component_context']['content']);
	}

	public function test_html_with_callable_emits_raw_html_field(): void {
		$builder = $this->createSectionBuilder();

		$builder->html(fn(): string => '<div>Dynamic content</div>');

		$fieldUpdates = $this->getFieldUpdates();
		self::assertNotEmpty($fieldUpdates);

		$content = $fieldUpdates[0]['payload']['field_data']['component_context']['content'];
		self::assertIsCallable($content);
		self::assertSame('<div>Dynamic content</div>', $content());
	}

	public function test_html_in_group_emits_group_field_update(): void {
		$builder = $this->createSectionBuilder();

		$group = $builder->group('test-group', 'Test Group');
		$group->html('<span>Group HTML</span>');

		$groupFieldUpdates = $this->getGroupFieldUpdates();
		self::assertNotEmpty($groupFieldUpdates, 'Expected group_field update for html() in group.');

		$payload = $groupFieldUpdates[0]['payload'];
		self::assertSame('test-group', $payload['group_id']);
		self::assertSame('_raw_html', $payload['field_data']['component']);
	}

	public function test_html_in_fieldset_emits_group_field_update(): void {
		$builder = $this->createSectionBuilder();

		$fieldset = $builder->fieldset('test-fieldset', 'Test Fieldset');
		$fieldset->html('<em>Fieldset HTML</em>');

		$groupFieldUpdates = $this->getGroupFieldUpdates();
		self::assertNotEmpty($groupFieldUpdates, 'Expected group_field update for html() in fieldset.');

		$payload = $groupFieldUpdates[0]['payload'];
		self::assertSame('test-fieldset', $payload['group_id']);
		self::assertSame('_raw_html', $payload['field_data']['component']);
	}

	// =========================================================================
	// hr() method tests
	// =========================================================================

	public function test_hr_returns_hr_builder(): void {
		$builder = $this->createSectionBuilder();

		$result = $builder->hr();

		self::assertInstanceOf(HrBuilder::class, $result);
	}

	public function test_hr_emits_hr_component_field(): void {
		$builder = $this->createSectionBuilder();

		$builder->hr()->end_hr();

		$fieldUpdates = $this->getFieldUpdates();
		self::assertNotEmpty($fieldUpdates, 'Expected field update for hr().');

		$payload = $fieldUpdates[0]['payload'];
		self::assertSame('_hr', $payload['field_data']['component']);
	}

	public function test_hr_style_sets_css_classes(): void {
		$builder = $this->createSectionBuilder();

		$builder->hr()
			->style('kplr-hr--accent')
			->end_hr();

		$fieldUpdates = $this->getFieldUpdates();
		$lastPayload  = $fieldUpdates[count($fieldUpdates) - 1]['payload'];

		self::assertSame('kplr-hr--accent', $lastPayload['field_data']['component_context']['style']);
	}

	public function test_hr_before_callback_is_stored(): void {
		$builder  = $this->createSectionBuilder();
		$callback = fn(array $ctx): string => '<p>Before</p>';

		$builder->hr()
			->before($callback)
			->end_hr();

		$fieldUpdates = $this->getFieldUpdates();
		$lastPayload  = $fieldUpdates[count($fieldUpdates) - 1]['payload'];

		self::assertSame($callback, $lastPayload['field_data']['before']);
	}

	public function test_hr_after_callback_is_stored(): void {
		$builder  = $this->createSectionBuilder();
		$callback = fn(array $ctx): string => '<p>After</p>';

		$builder->hr()
			->after($callback)
			->end_hr();

		$fieldUpdates = $this->getFieldUpdates();
		$lastPayload  = $fieldUpdates[count($fieldUpdates) - 1]['payload'];

		self::assertSame($callback, $lastPayload['field_data']['after']);
	}

	public function test_hr_end_hr_returns_parent_builder(): void {
		$builder = $this->createSectionBuilder();

		$result = $builder->hr()->end_hr();

		self::assertSame($builder, $result);
	}

	public function test_hr_in_group_emits_group_field_update(): void {
		$builder = $this->createSectionBuilder();

		$group = $builder->group('test-group', 'Test Group');
		$group->hr()->end_hr();

		$groupFieldUpdates = $this->getGroupFieldUpdates();
		self::assertNotEmpty($groupFieldUpdates, 'Expected group_field update for hr() in group.');

		$payload = $groupFieldUpdates[0]['payload'];
		self::assertSame('test-group', $payload['group_id']);
		self::assertSame('_hr', $payload['field_data']['component']);
	}

	// =========================================================================
	// element() method tests
	// =========================================================================

	public function test_element_returns_generic_element_builder(): void {
		$builder = $this->createSectionBuilder(array(
			'elements.button' => fn(string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'elements.button'),
		));

		$result = $builder->element('test-button', 'Click Me', 'elements.button');

		self::assertInstanceOf(GenericElementBuilder::class, $result);
	}

	public function test_element_emits_field_update_with_component(): void {
		$builder = $this->createSectionBuilder(array(
			'elements.button' => fn(string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'elements.button'),
		));

		$builder->element('test-button', 'Click Me', 'elements.button')->end_element();

		$fieldUpdates = $this->getFieldUpdates();
		self::assertNotEmpty($fieldUpdates, 'Expected field update for element().');

		$payload = $fieldUpdates[0]['payload'];
		self::assertSame('test-button', $payload['field_data']['id']);
		self::assertSame('elements.button', $payload['field_data']['component']);
	}

	public function test_element_style_sets_css_classes(): void {
		$builder = $this->createSectionBuilder(array(
			'elements.button' => fn(string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'elements.button'),
		));

		$builder->element('test-button', 'Click Me', 'elements.button')
			->style('my-custom-class')
			->end_element();

		$fieldUpdates = $this->getFieldUpdates();
		$lastPayload  = $fieldUpdates[count($fieldUpdates) - 1]['payload'];

		self::assertSame('my-custom-class', $lastPayload['field_data']['style']);
	}

	public function test_element_description_delegates_to_builder(): void {
		$builder = $this->createSectionBuilder(array(
			'elements.button' => fn(string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'elements.button'),
		));

		$builder->element('test-button', 'Click Me', 'elements.button')
			->description('Help text')
			->end_element();

		$fieldUpdates = $this->getFieldUpdates();
		$lastPayload  = $fieldUpdates[count($fieldUpdates) - 1]['payload'];

		self::assertSame('Help text', $lastPayload['field_data']['component_context']['description'] ?? null);
	}

	public function test_element_before_callback_is_stored(): void {
		$builder  = $this->createSectionBuilder(array(
			'elements.button' => fn(string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'elements.button'),
		));
		$callback = fn(array $ctx): string => '<p>Before element</p>';

		$builder->element('test-button', 'Click Me', 'elements.button')
			->before($callback)
			->end_element();

		$fieldUpdates = $this->getFieldUpdates();
		$lastPayload  = $fieldUpdates[count($fieldUpdates) - 1]['payload'];

		self::assertSame($callback, $lastPayload['field_data']['before']);
	}

	public function test_element_after_callback_is_stored(): void {
		$builder  = $this->createSectionBuilder(array(
			'elements.button' => fn(string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'elements.button'),
		));
		$callback = fn(array $ctx): string => '<p>After element</p>';

		$builder->element('test-button', 'Click Me', 'elements.button')
			->after($callback)
			->end_element();

		$fieldUpdates = $this->getFieldUpdates();
		$lastPayload  = $fieldUpdates[count($fieldUpdates) - 1]['payload'];

		self::assertSame($callback, $lastPayload['field_data']['after']);
	}

	public function test_element_end_element_returns_parent_builder(): void {
		$builder = $this->createSectionBuilder(array(
			'elements.button' => fn(string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'elements.button'),
		));

		$result = $builder->element('test-button', 'Click Me', 'elements.button')->end_element();

		self::assertSame($builder, $result);
	}

	public function test_element_throws_when_no_builder_factory(): void {
		$builder = $this->createSectionBuilder();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('has no registered builder factory');

		$builder->element('test-button', 'Click Me', 'elements.unknown');
	}

	public function test_element_in_group_emits_group_field_update(): void {
		$builder = $this->createSectionBuilder(array(
			'elements.button' => fn(string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'elements.button'),
		));

		$group = $builder->group('test-group', 'Test Group');
		$group->element('group-button', 'Group Button', 'elements.button')->end_element();

		$groupFieldUpdates = $this->getGroupFieldUpdates();
		self::assertNotEmpty($groupFieldUpdates, 'Expected group_field update for element() in group.');

		$payload = $groupFieldUpdates[0]['payload'];
		self::assertSame('test-group', $payload['group_id']);
		self::assertSame('elements.button', $payload['field_data']['component']);
	}

	public function test_element_in_fieldset_emits_group_field_update(): void {
		$builder = $this->createSectionBuilder(array(
			'elements.button' => fn(string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'elements.button'),
		));

		$fieldset = $builder->fieldset('test-fieldset', 'Test Fieldset');
		$fieldset->element('fieldset-button', 'Fieldset Button', 'elements.button')->end_element();

		$groupFieldUpdates = $this->getGroupFieldUpdates();
		self::assertNotEmpty($groupFieldUpdates, 'Expected group_field update for element() in fieldset.');

		$payload = $groupFieldUpdates[0]['payload'];
		self::assertSame('test-fieldset', $payload['group_id']);
		self::assertSame('elements.button', $payload['field_data']['component']);
	}

	public function test_element_delegates_unknown_methods_to_builder(): void {
		$builder = $this->createSectionBuilder(array(
			'elements.button' => fn(string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'elements.button'),
		));

		// StubComponentBuilder has a customOption() method
		$builder->element('test-button', 'Click Me', 'elements.button')
			->customOption('test-value')
			->end_element();

		// If we got here without exception, delegation worked
		self::assertTrue(true);
	}

	public function test_element_throws_for_unknown_method_not_on_builder(): void {
		$builder = $this->createSectionBuilder(array(
			'elements.button' => fn(string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'elements.button'),
		));

		$this->expectException(\BadMethodCallException::class);

		$builder->element('test-button', 'Click Me', 'elements.button')
			->nonExistentMethod();
	}

	// =========================================================================
	// Helper methods
	// =========================================================================

	/**
	 * Get all field updates from captured updates.
	 *
	 * @return list<array{type:string,payload:array<string,mixed>}>
	 */
	private function getFieldUpdates(): array {
		return array_values(array_filter(
			$this->updates,
			static fn(array $entry): bool => $entry['type'] === 'field'
		));
	}

	/**
	 * Get all group_field updates from captured updates.
	 *
	 * @return list<array{type:string,payload:array<string,mixed>}>
	 */
	private function getGroupFieldUpdates(): array {
		return array_values(array_filter(
			$this->updates,
			static fn(array $entry): bool => $entry['type'] === 'group_field'
		));
	}

	/**
	 * Create a SectionBuilder configured with the provided component builder factories.
	 *
	 * @param array<string, callable(string,string):StubComponentBuilder> $factories
	 */
	private function createSectionBuilder(array $factories = array()): SectionBuilder {
		$this->updates = array();

		$forms       = new StubForms();
		$rootBuilder = new StubRootBuilder($forms);
		$updateFn    = function (string $type, array $payload): void {
			$this->updates[] = array('type' => $type, 'payload' => $payload);
		};

		$context = new StubBuilderContextForElementTest($forms, 'container', $updateFn, $factories);

		return new SectionBuilder($rootBuilder, $context, 'section', 'Heading');
	}
}

/**
 * Stub BuilderContextInterface for element builder tests.
 */
final class StubBuilderContextForElementTest implements BuilderContextInterface {
	/**
	 * @param array<string, callable(string,string):StubComponentBuilder> $factories
	 */
	public function __construct(
		private StubForms $forms,
		private string $containerId,
		private $updateFn,
		private array $factories = array()
	) {
	}

	public function get_forms(): FormsInterface {
		return $this->forms;
	}

	public function get_component_builder_factory(string $component): ?callable {
		return $this->factories[$component] ?? null;
	}

	public function get_update_callback(): callable {
		return $this->updateFn;
	}

	public function get_container_id(): string {
		return $this->containerId;
	}
}
