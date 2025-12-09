<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\GroupBuilder;
use Ran\PluginLib\Forms\Builders\GenericFieldBuilder;
use Ran\PluginLib\Forms\Builders\GenericBuilderContext;
use Ran\PluginLib\Forms\Builders\BuilderContextInterface;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ran\PluginLib\Forms\Builders\GroupBuilder
 */
final class GroupBuilderTest extends TestCase {
	/**
	 * @var list<array{type:string,payload:array<string,mixed>}> $updates
	 */
	private array $updates;

	private ?StubSectionBuilderForGroupTest $currentSection = null;

	private ?StubRootBuilder $currentRoot = null;

	protected function setUp(): void {
		parent::setUp();
		$this->updates        = array();
		$this->currentRoot    = null;
		$this->currentSection = null;
	}

	public function test_field_requires_component_alias(): void {
		$group = $this->createGroupBuilder();

		$this->expectException(\InvalidArgumentException::class);
		$group->field('field', 'Label', '');
	}

	public function test_field_requires_array_component_context(): void {
		$group = $this->createGroupBuilder();

		$this->expectException(\InvalidArgumentException::class);
		$group->field('field', 'Label', 'fields.input', array('context' => 'not-array'));
	}

	public function test_field_with_factory_returns_proxy_and_emits_updates(): void {
		$group = $this->createGroupBuilder(array(
			'fields.input' => fn (string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'fields.input'),
		));

		$result = $group->field('username', 'Username', 'fields.input', array(
			'context'        => array('attributes' => array('placeholder' => 'Name')),
			'order'          => 15,
			'field_template' => 'custom.wrapper',
		));

		self::assertInstanceOf(GenericFieldBuilder::class, $result);

		$groupFieldUpdates = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'group_field'));
		self::assertNotEmpty($groupFieldUpdates, 'Expected group_field update to be emitted.');
		$payload = $groupFieldUpdates[count($groupFieldUpdates) - 1]['payload'];
		self::assertSame('container', $payload['container_id']);
		self::assertSame('section', $payload['section_id']);
		self::assertSame('integration-group', $payload['group_id']);
		self::assertSame('fields.input', $payload['field_data']['component']);
		self::assertSame('Name', $payload['field_data']['component_context']['attributes']['placeholder'] ?? null);
		self::assertSame(15, $payload['field_data']['order']);

		$templateOverrides = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'template_override'));
		self::assertNotEmpty($templateOverrides, 'Expected template override update to be emitted.');
		self::assertSame('field', $templateOverrides[0]['payload']['element_type']);
		self::assertSame('custom.wrapper', $templateOverrides[0]['payload']['overrides']['field-wrapper'] ?? null);
	}

	public function test_field_with_factory_emits_group_field_update(): void {
		$group = $this->createGroupBuilder(array(
			'fields.input' => fn (string $id, string $label): StubComponentBuilder => new StubComponentBuilder($id, $label, 'fields.input'),
		));

		$group->field('api_key', 'API Key', 'fields.input');

		$groupFieldUpdates = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'group_field'));
		self::assertNotEmpty($groupFieldUpdates, 'Expected group_field update to be emitted.');
		self::assertSame('api_key', $groupFieldUpdates[0]['payload']['field_data']['id']);
	}

	public function test_before_after_and_order_emit_metadata_updates(): void {
		$group = $this->createGroupBuilder();

		$group->before(static fn (): string => '<p>before</p>')
			->after(static fn (): string => '<p>after</p>')
			->order(42);

		$metadataUpdates = array_values(array_filter($this->updates, static fn(array $entry): bool => $entry['type'] === 'group_metadata'));
		self::assertNotEmpty($metadataUpdates, 'Expected group metadata updates.');
		$latest = $metadataUpdates[count($metadataUpdates) - 1]['payload'];
		self::assertSame(42, $latest['group_data']['order']);
	}

	public function test_end_group_returns_section_builder(): void {
		$group   = $this->createGroupBuilder();
		$section = $group->end_group();

		self::assertSame($this->currentSection, $section);
	}

	/**
	 * @param array<string, callable(string,string):StubComponentBuilder> $factories
	 */
	private function createGroupBuilder(array $factories = array()): GroupBuilder {
		$this->updates = array();

		$updateFn = function (string $type, array $payload): void {
			$this->updates[] = array('type' => $type, 'payload' => $payload);
		};

		$forms                = new StubForms();
		$this->currentRoot    = new StubRootBuilder($forms);
		$context              = new StubBuilderContextForGroupTest($forms, 'container', $updateFn, $factories);
		$this->currentSection = new StubSectionBuilderForGroupTest(
			$this->currentRoot,
			$context,
			'section',
			'Heading'
		);

		return new GroupBuilder(
			$this->currentSection,
			$context,
			'section',
			'integration-group',
			'Integration Group',
			null
		);
	}
}

final class StubBuilderContextForGroupTest implements BuilderContextInterface {
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

	public function get_forms(): \Ran\PluginLib\Forms\FormsInterface {
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

final class StubSectionBuilderForGroupTest extends SectionBuilder {
	public function __construct(
		BuilderRootInterface $root,
		BuilderContextInterface $context,
		string $section,
		string $heading
	) {
		parent::__construct($root, $context, $section, $heading);
	}
}
