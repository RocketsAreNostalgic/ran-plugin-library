<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;
use Ran\PluginLib\Forms\Builders\GenericFieldBuilder;
use Ran\PluginLib\Forms\Builders\GenericBuilderContext;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ran\PluginLib\Forms\Builders\GenericFieldBuilder
 */
final class GenericFieldBuilderTest extends TestCase {
	/**
	 * @var list<array{type:string,payload:array<string,mixed>}> $updates
	 */
	private array $updates;

	private ?StubComponentBuilder $currentBuilder = null;

	private ?StubSectionBuilder $currentSection = null;

	protected function setUp(): void {
		parent::setUp();
		$this->updates = array();
	}

	public function test_constructor_emits_initial_field_update_and_template_override(): void {
		$proxy = $this->createProxy(initialTemplate: 'custom.wrapper');

		self::assertInstanceOf(GenericFieldBuilder::class, $proxy);

		$fieldUpdates = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'field'));
		self::assertNotEmpty($fieldUpdates);
		self::assertSame('field-proxy', $fieldUpdates[0]['payload']['field_data']['id']);
		self::assertSame('fields.input', $fieldUpdates[0]['payload']['field_data']['component']);

		$templateUpdates = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'template_override'));
		self::assertNotEmpty($templateUpdates);
		self::assertSame('field', $templateUpdates[0]['payload']['element_type']);
		self::assertSame('field-proxy', $templateUpdates[0]['payload']['element_id']);
		self::assertSame('custom.wrapper', $templateUpdates[0]['payload']['overrides']['field-wrapper'] ?? null);
	}

	public function test_delegates_fluent_methods_to_builder(): void {
		$proxy   = $this->createProxy();
		$builder = $this->requireCurrentBuilder();

		$proxy->attribute('data-test', 'value')
			->description('Help text')
			->customOption('custom-value')
			->order(17)
			->readonly();

		self::assertSame('custom-value', $builder->customOption);
		$builderArray = $builder->to_array();
		self::assertSame('value', $builderArray['component_context']['attributes']['data-test'] ?? null);
		self::assertSame('Help text', $builderArray['component_context']['description'] ?? null);

		$fieldUpdates = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'field'));
		self::assertGreaterThanOrEqual(2, count($fieldUpdates));
		$lastPayload = $fieldUpdates[count($fieldUpdates) - 1]['payload']['field_data'];
		self::assertSame(17, $lastPayload['order']);
		self::assertTrue($lastPayload['component_context']['readonly'] ?? false);
	}

	public function test_end_field_returns_parent_builder(): void {
		$proxy   = $this->createProxy();
		$section = $proxy->end_field();
		/** @var StubSectionBuilder $section */

		self::assertInstanceOf(StubSectionBuilder::class, $section);
		self::assertFalse($section->wasMarkerCalled());

		// After end_field(), we can call parent methods directly on the returned section
		$result = $section->parent_marker();

		self::assertSame('marker', $result);
		self::assertTrue($section->wasMarkerCalled());
	}

	public function test_throws_for_unknown_method(): void {
		$proxy = $this->createProxy();

		$this->expectException(\BadMethodCallException::class);
		$proxy->unsupportedMethod();
	}

	public function test_throws_for_non_fluent_proxied_builder_method(): void {
		$proxy = $this->createProxy();

		$this->expectException(\BadMethodCallException::class);
		$this->expectExceptionMessage('must return $this for fluent chaining');
		$proxy->nonFluentMethod();
	}

	public function test_apply_context_hydrates_builder_and_emits_update(): void {
		$proxy   = $this->createProxy();
		$builder = $this->requireCurrentBuilder();

		$proxy->apply_context(array(
			'attributes' => array('placeholder' => 'Email'),
			'order'      => 42,
		));

		self::assertSame('Email', $builder->to_array()['component_context']['attributes']['placeholder'] ?? null);

		$fieldUpdates = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'field'));
		$lastPayload  = $fieldUpdates[count($fieldUpdates) - 1]['payload'];
		self::assertSame(42, $lastPayload['field_data']['order']);
	}

	public function test_field_template_emits_template_override(): void {
		$proxy = $this->createProxy();

		$proxy->template('context.wrapper');

		$templateUpdates = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'template_override'));
		self::assertNotEmpty($templateUpdates, 'Expected template override after calling field_template().');
		self::assertSame('context.wrapper', $templateUpdates[count($templateUpdates) - 1]['payload']['overrides']['field-wrapper'] ?? null);
	}

	public function test_apply_context_hydrates_default_values_via_exact_snake_case_method(): void {
		$proxy = $this->createProxyWithBuilder(new StubDefaultValuesBuilder('field-proxy', 'Field Proxy', 'fields.multi-select'));

		$proxy->apply_context(array(
			'default_values' => array('a', 'b'),
		));

		$fieldUpdates = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'field'));
		$lastPayload  = $fieldUpdates[count($fieldUpdates) - 1]['payload']['field_data'];
		self::assertSame(array('a', 'b'), $lastPayload['component_context']['default'] ?? null);
	}

	public function test_apply_context_throws_when_key_is_ambiguous_between_snake_and_normalized_method(): void {
		$proxy = $this->createProxyWithBuilder(new StubAmbiguousHydrationBuilder('field-proxy', 'Field Proxy', 'fields.input'));

		$this->expectException(\BadMethodCallException::class);
		$this->expectExceptionMessage('Ambiguous builder hydration');
		$proxy->apply_context(array(
			'default_values' => array('a'),
		));
	}

	/**
	 * @return GenericFieldBuilder
	 */
	private function createProxy(?string $initialTemplate = null): GenericFieldBuilder {
		$this->updates        = array();
		$this->currentBuilder = null;
		$this->currentSection = null;

		$forms    = new StubForms();
		$root     = new StubRootBuilder($forms);
		$updateFn = function (string $type, array $payload): void {
			$this->updates[] = array('type' => $type, 'payload' => $payload);
		};
		$context = new GenericBuilderContext($forms, 'container', $updateFn);
		$section = new StubSectionBuilder($root, $context, 'section', 'Heading');
		$builder = new StubComponentBuilder('field-proxy', 'Field Proxy', 'fields.input');

		$this->currentBuilder = $builder;
		$this->currentSection = $section;

		return new GenericFieldBuilder(
			$builder,
			$section,
			$updateFn,
			'container',
			'section',
			'fields.input',
			null,
			$initialTemplate,
			array()
		);
	}

	private function createProxyWithBuilder(ComponentBuilderBase $builder, ?string $initialTemplate = null): GenericFieldBuilder {
		$this->updates        = array();
		$this->currentBuilder = null;
		$this->currentSection = null;

		$forms    = new StubForms();
		$root     = new StubRootBuilder($forms);
		$updateFn = function (string $type, array $payload): void {
			$this->updates[] = array('type' => $type, 'payload' => $payload);
		};
		$context = new GenericBuilderContext($forms, 'container', $updateFn);
		$section = new StubSectionBuilder($root, $context, 'section', 'Heading');

		$this->currentBuilder = null;
		$this->currentSection = $section;

		return new GenericFieldBuilder(
			$builder,
			$section,
			$updateFn,
			'container',
			'section',
			$builder->to_array()['component'] ?? 'fields.input',
			null,
			$initialTemplate,
			array()
		);
	}

	private function requireCurrentBuilder(): StubComponentBuilder {
		self::assertNotNull($this->currentBuilder, 'Current builder should be initialised via createProxy().');
		return $this->currentBuilder;
	}
}

final class StubDefaultValuesBuilder extends ComponentBuilderBase {
	private mixed $default_values = array();

	public function __construct(string $id, string $label, private string $componentAlias) {
		parent::__construct($id, $label);
	}

	public function default_values(array|callable $values): static {
		$this->default_values = $values;
		return $this;
	}

	protected function _get_component(): string {
		return $this->componentAlias;
	}

	protected function _build_component_context(): array {
		$context            = $this->_build_base_context();
		$context['default'] = $this->default_values;
		return $context;
	}
}

final class StubAmbiguousHydrationBuilder extends ComponentBuilderBase {
	public function __construct(string $id, string $label, private string $componentAlias) {
		parent::__construct($id, $label);
	}

	public function default_values(array|callable $values): static {
		return $this;
	}

	public function defaultValues(array|callable $values): static {
		return $this;
	}

	protected function _get_component(): string {
		return $this->componentAlias;
	}

	protected function _build_component_context(): array {
		return $this->_build_base_context();
	}
}
