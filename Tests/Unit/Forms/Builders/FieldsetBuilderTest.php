<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use Ran\PluginLib\Forms\Builders\FieldsetBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ran\PluginLib\Forms\Builders\FieldsetBuilder
 */
final class FieldsetBuilderTest extends TestCase {
	/**
	 * @var list<array{type:string,payload:array<string,mixed>}> $updates
	 */
	private array $updates;

	private ?StubSectionBuilder $currentSection = null;

	protected function setUp(): void {
		parent::setUp();
		$this->updates        = array();
		$this->currentSection = null;
	}

	public function test_constructor_emits_default_metadata_and_template_override(): void {
		$this->createFieldsetBuilder();

		$metadata = $this->latestUpdateOfType('group_metadata');
		self::assertSame('fieldset', $metadata['group_data']['id']);
		self::assertSame('', $metadata['group_data']['style']); // No default style
		self::assertSame('', $metadata['group_data']['form']);
		self::assertSame('', $metadata['group_data']['name']);
		self::assertFalse($metadata['group_data']['disabled']);

		$template = $this->latestUpdateOfType('template_override');
		self::assertSame('group', $template['element_type']);
		self::assertSame('fieldset', $template['element_id']);
		self::assertSame('fieldset-wrapper', $template['overrides']['group-wrapper'] ?? null);
	}

	public function test_style_update_trims_and_emits_metadata(): void {
		$builder = $this->createFieldsetBuilder();

		$builder->style(' inline ');

		$metadata = $this->latestUpdateOfType('group_metadata');
		self::assertSame('inline', $metadata['group_data']['style']);
	}

	public function test_disabled_update_sets_flag(): void {
		$builder = $this->createFieldsetBuilder();

		$builder->disabled(true);

		$metadata = $this->latestUpdateOfType('group_metadata');
		self::assertTrue($metadata['group_data']['disabled']);
	}

	public function test_form_update_sets_value(): void {
		$builder = $this->createFieldsetBuilder();

		$builder->form('my-form');

		$metadata = $this->latestUpdateOfType('group_metadata');
		self::assertSame('my-form', $metadata['group_data']['form']);
	}

	public function test_name_update_sets_value(): void {
		$builder = $this->createFieldsetBuilder();

		$builder->name('my-fieldset');

		$metadata = $this->latestUpdateOfType('group_metadata');
		self::assertSame('my-fieldset', $metadata['group_data']['name']);
	}

	public function test_style_accepts_empty_string(): void {
		$builder = $this->createFieldsetBuilder();

		$builder->style('');

		$metadata = $this->latestUpdateOfType('group_metadata');
		self::assertSame('', $metadata['group_data']['style']);
	}

	public function test_end_fieldset_returns_section_builder(): void {
		$builder = $this->createFieldsetBuilder();

		$section = $builder->end_fieldset();

		self::assertSame($this->currentSection, $section);
	}

	/**
	 * @param array<string,mixed> $args
	 */
	private function createFieldsetBuilder(array $args = array()): FieldsetBuilder {
		$this->updates        = array();
		$this->currentSection = null;

		$forms    = new StubForms();
		$root     = new StubRootBuilder($forms);
		$updateFn = function (string $type, array $payload): void {
			$this->updates[] = array('type' => $type, 'payload' => $payload);
		};

		$this->currentSection = new StubSectionBuilder($root, 'container', 'section', 'Heading', $updateFn);

		return new FieldsetBuilder(
			$this->currentSection,
			'container',
			'section',
			'fieldset',
			'Legend',
			null,
			$updateFn,
			$args
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function latestUpdateOfType(string $type): array {
		$filtered = array_values(array_filter(
			$this->updates,
			static fn(array $entry): bool => $entry['type'] === $type
		));

		self::assertNotEmpty($filtered, sprintf('Expected at least one update of type %s.', $type));

		return $filtered[count($filtered) - 1]['payload'];
	}
}
