<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use Ran\PluginLib\Forms\Builders\SubmitControlsBuilder;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * @covers \Ran\PluginLib\Forms\Builders\SubmitControlsBuilder
 */
final class SubmitControlsBuilderTest extends TestCase {
	/**
	 * @var list<array{type:string,payload:array<string,mixed>}> $updates
	 */
	private array $updates;

	private StubRootBuilder $root;

	protected function setUp(): void {
		parent::setUp();
		$this->updates = array();
		$this->root    = new StubRootBuilder(new StubForms());
	}

	public function test_constructor_emits_defaults(): void {
		$builder = $this->createBuilder();

		self::assertInstanceOf(SubmitControlsBuilder::class, $builder);

		$zoneUpdates = $this->pluckUpdates('submit_controls_zone');
		self::assertNotEmpty($zoneUpdates, 'Expected zone metadata update.');
		$payload = $zoneUpdates[0]['payload'];
		self::assertSame('container', $payload['container_id']);
		self::assertSame('zone', $payload['zone_id']);
		self::assertNull($payload['before']);
		self::assertNull($payload['after']);

		$templateUpdates = $this->pluckUpdates('template_override');
		self::assertEmpty($templateUpdates, 'Default construction should not emit template overrides.');
	}

	public function test_before_and_after_emit_zone_updates(): void {
		$builder = $this->createBuilder();

		$builder->before(static fn (): string => '<p>before</p>')
			->after(static fn (): string => '<p>after</p>');

		$zoneUpdates = $this->pluckUpdates('submit_controls_zone');
		self::assertGreaterThanOrEqual(3, count($zoneUpdates));
		$latest = $zoneUpdates[count($zoneUpdates) - 1]['payload'];
		self::assertIsCallable($latest['before']);
		self::assertIsCallable($latest['after']);
	}

	public function test_template_override_requires_non_empty_key(): void {
		$builder = $this->createBuilder();

		$this->expectException(InvalidArgumentException::class);
		$builder->template('');
	}

	public function test_template_override_emits_update(): void {
		$builder = $this->createBuilder();
		$builder->template('custom.submit.wrapper');

		$templateUpdates = $this->pluckUpdates('template_override');
		self::assertNotEmpty($templateUpdates, 'Expected template override emission.');
		$latestOverride = $templateUpdates[count($templateUpdates) - 1]['payload'];
		self::assertSame('root', $latestOverride['element_type']);
		self::assertSame('container', $latestOverride['element_id']);
		self::assertSame('zone', $latestOverride['zone_id']);
		self::assertSame('custom.submit.wrapper', $latestOverride['overrides']['submit-controls-wrapper'] ?? null);
	}

	public function test_button_requires_id_and_label(): void {
		$builder = $this->createBuilder();

		$this->expectException(InvalidArgumentException::class);
		$builder->button('', 'Label');
	}

	public function test_button_requires_label(): void {
		$builder = $this->createBuilder();

		$this->expectException(InvalidArgumentException::class);
		$builder->button('primary', '');
	}

	public function test_button_emits_controls_set_with_defaults(): void {
		$builder = $this->createBuilder();

		$builder->button('primary', 'Save Changes');

		$payload = $this->latestControlsPayload();
		self::assertCount(1, $payload['controls']);
		self::assertSame('primary', $payload['controls'][0]['id']);
		self::assertSame('components.button', $payload['controls'][0]['component']);
		self::assertSame('Save Changes', $payload['controls'][0]['label']);
		self::assertSame('submit', $payload['controls'][0]['component_context']['type'] ?? null);
	}

	public function test_button_allows_customization_via_proxy(): void {
		$builder = $this->createBuilder();

		$builder->button('danger', 'Delete')->variant('danger')->disabled();

		$payload = $this->latestControlsPayload();
		self::assertSame('danger', $payload['controls'][0]['component_context']['variant']);
		self::assertTrue($payload['controls'][0]['component_context']['disabled']);
	}

	public function test_field_requires_id(): void {
		$builder = $this->createBuilder();

		$this->expectException(InvalidArgumentException::class);
		$builder->field('', 'Label', 'components.notice');
	}

	public function test_field_requires_component_alias(): void {
		$builder = $this->createBuilder();

		$this->expectException(InvalidArgumentException::class);
		$builder->field('divider', 'Label', '');
	}

	public function test_field_accepts_arbitrary_component_without_label(): void {
		$builder = $this->createBuilder();

		$builder->field('divider', '', 'layout.custom-divider', array(
			'context' => array('content' => '<span>|</span>'),
			'order'   => 15,
		));

		$payload = $this->latestControlsPayload();
		self::assertSame('divider', $payload['controls'][0]['id']);
		self::assertSame('layout.custom-divider', $payload['controls'][0]['component']);
		self::assertSame('<span>|</span>', $payload['controls'][0]['component_context']['content'] ?? null);
		self::assertSame('', $payload['controls'][0]['label']);
		self::assertSame(15, $payload['controls'][0]['order']);
	}

	public function test_button_replaces_existing_control_with_same_id(): void {
		$builder = $this->createBuilder();

		$builder->button('primary', 'Save Changes');
		$builder->button('primary', 'Save & Continue')->variant('secondary');

		$payload = $this->latestControlsPayload();
		self::assertCount(1, $payload['controls']);
		self::assertSame('Save & Continue', $payload['controls'][0]['label']);
		self::assertSame('secondary', $payload['controls'][0]['component_context']['variant']);
	}

	public function test_multiple_buttons_sorted_by_order(): void {
		$builder = $this->createBuilder();

		$builder->button('secondary', 'Cancel')
			->type('button')
			->order(20);
		$builder->button('primary', 'Save Changes')
			->order(10);

		$payload = $this->latestControlsPayload();
		self::assertSame(array('primary', 'secondary'), array_column($payload['controls'], 'id'));
	}

	public function test_end_submit_controls_returns_root(): void {
		$builder = $this->createBuilder();

		self::assertSame($this->root, $builder->end_submit_controls());
		self::assertSame($this->root, $builder->end());
	}

	/**
	 * @return SubmitControlsBuilder
	 */
	private function createBuilder(): SubmitControlsBuilder {
		$this->updates = array();

		$updateFn = function(string $type, array $payload): void {
			$this->updates[] = array('type' => $type, 'payload' => $payload);
		};

		return new SubmitControlsBuilder(
			$this->root,
			'container',
			'zone',
			$updateFn
		);
	}

	/**
	 * @param string $type
	 * @return list<array{type:string,payload:array<string,mixed>}>
	 */
	private function pluckUpdates(string $type): array {
		return array_values(array_filter(
			$this->updates,
			static fn(array $entry): bool => $entry['type'] === $type
		));
	}

	/**
	 * @return array{container_id:string,zone_id:string,controls:list<array<string,mixed>>}
	 */
	private function latestControlsPayload(): array {
		$controls = $this->pluckUpdates('submit_controls_set');
		self::assertNotEmpty($controls, 'Expected submit_controls_set update.');
		return $controls[count($controls) - 1]['payload'];
	}
}
