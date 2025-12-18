<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\GroupBuilder;
use Ran\PluginLib\Forms\Builders\GenericFieldBuilder;
use Ran\PluginLib\Forms\Builders\FieldsetBuilder;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Forms\Builders\BuilderContextInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ran\PluginLib\Forms\Builders\SectionBuilder
 */
final class SectionBuilderTest extends TestCase {
	/**
	 * @var list<array{type:string,payload:array<string,mixed>}> captured builder updates
	 */
	private array $updates;

	protected function setUp(): void {
		parent::setUp();
		$this->updates = array();
	}

	public function test_field_requires_component_alias(): void {
		$builder = $this->createSectionBuilder();

		$this->expectException(\InvalidArgumentException::class);
		$builder->field('field', 'Label', '');
	}

	public function test_field_requires_array_component_context(): void {
		$builder = $this->createSectionBuilder();

		$this->expectException(\InvalidArgumentException::class);
		$builder->field('field', 'Label', 'fields.input', array('context' => 'not-array'));
	}

	public function test_field_with_factory_returns_proxy_and_emits_field_updates(): void {
		$builder = $this->createSectionBuilder(array(
			'fields.input' => fn (string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'fields.input'),
		));

		$result = $builder->field('username', 'Username', 'fields.input', array(
			'context'        => array('attributes' => array('data-test' => 'value')),
			'order'          => 7,
			'field_template' => 'custom.wrapper',
		));

		self::assertInstanceOf(GenericFieldBuilder::class, $result);

		$fieldUpdates = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'field'));
		self::assertNotEmpty($fieldUpdates, 'Expected at least one field update to be emitted.');
		$lastFieldPayload = $fieldUpdates[count($fieldUpdates) - 1]['payload'];
		self::assertSame('container', $lastFieldPayload['container_id']);
		self::assertSame('section', $lastFieldPayload['section_id']);
		self::assertSame(7, $lastFieldPayload['field_data']['order']);
		self::assertSame('fields.input', $lastFieldPayload['field_data']['component']);
		self::assertSame('value', $lastFieldPayload['field_data']['component_context']['attributes']['data-test'] ?? null);

		$templateOverrides = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'template_override'));
		self::assertNotEmpty($templateOverrides, 'Expected template override update to be emitted.');
		self::assertSame('field', $templateOverrides[0]['payload']['element_type']);
		self::assertSame('custom.wrapper', $templateOverrides[0]['payload']['overrides']['field-wrapper'] ?? null);
	}

	public function test_field_returns_section_field_proxy_and_emits_field_update(): void {
		$builder = $this->createSectionBuilder(array(
			'fields.input' => fn (string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'fields.input'),
		));

		$result = $builder->field('api_key', 'API Key', 'fields.input');

		// field() returns ComponentBuilderProxy when builder factory exists
		self::assertInstanceOf(GenericFieldBuilder::class, $result);

		// Trigger field emission by calling end_field()
		$result->end_field();

		$fieldUpdates = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'field'));
		self::assertNotEmpty($fieldUpdates, 'Expected a field update for direct field registration.');
		self::assertSame('api_key', $fieldUpdates[0]['payload']['field_data']['id']);
	}

	public function test_field_throws_when_no_builder_factory(): void {
		$builder = $this->createSectionBuilder();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('has no registered builder factory');

		$builder->field('api_key', 'API Key', 'fields.custom');
	}

	public function test_group_returns_group_builder_and_emits_group_metadata(): void {
		$builder = $this->createSectionBuilder();

		$group = $builder->group('preferences', 'Preferences');

		self::assertInstanceOf(GroupBuilder::class, $group);
		$groupMetadata = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'group_metadata'));
		self::assertNotEmpty($groupMetadata, 'Expected group metadata update to be emitted.');
		self::assertSame('preferences', $groupMetadata[0]['payload']['group_id']);
	}

	public function test_fieldset_returns_fieldset_builder(): void {
		$builder = $this->createSectionBuilder();

		$fieldSet = $builder->fieldset('profile-details', 'Profile Details', null, array('style' => 'minimal', 'required' => true));

		self::assertInstanceOf(FieldsetBuilder::class, $fieldSet);
	}

	public function test_style_accepts_callable_resolver(): void {
		$builder = $this->createSectionBuilder();

		$style = static fn (): string => '  callable-style  ';
		$builder->style($style);

		$sectionUpdates = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'section_metadata'));
		self::assertNotEmpty($sectionUpdates, 'Expected section metadata updates.');
		$payload = $sectionUpdates[count($sectionUpdates) - 1]['payload'];
		self::assertSame($style, $payload['group_data']['style']);
	}

	public function test_template_throws_when_key_is_blank(): void {
		$builder = $this->createSectionBuilder();

		$this->expectException(\InvalidArgumentException::class);
		$builder->template(' ');
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

		$context = new StubBuilderContextForSectionTest($forms, 'container', $updateFn, $factories);

		return new SectionBuilder($rootBuilder, $context, 'section', 'Heading');
	}
}

final class StubBuilderContextForSectionTest implements BuilderContextInterface {
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
