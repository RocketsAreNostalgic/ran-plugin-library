<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Forms\Services\FormsStateStoreInterface;
use Ran\PluginLib\Forms\Services\FormsRenderService;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ran\PluginLib\Forms\Services\FormsRenderService
 */
final class FormsRenderServiceTest extends TestCase {
	public function test_render_callback_output_throws_when_missing_required_keys(): void {
		$svc = new FormsRenderService(
			$this->createMock(FormsStateStoreInterface::class),
			new CollectingLogger(),
			$this->createMock(ComponentLoader::class),
			$this->createMock(FormElementRenderer::class),
			'opt',
			static function (): void {
			},
			static function (): ?FormsServiceSession {
				return null;
			},
			static function (): string {
				return 'layout.zone.section-wrapper';
			}
		);

		$this->expectException(\InvalidArgumentException::class);
		$svc->render_callback_output(static fn (): string => 'x', array('container_id' => 'container', 'values' => array()));
	}

	public function test_render_default_sections_wrapper_callback_contexts_include_required_base_keys(): void {
		$captured = array(
			'section_before' => null,
			'group_before'   => null,
			'field_before'   => null,
			'hr_before'      => null,
			'html_content'   => null,
		);

		$values = array(
			'f1' => 'v1',
		);

		$sections = array(
			'sec1' => array(
				'title'          => 'Section',
				'description_cb' => null,
				'before'         => function (array $ctx) use (&$captured): string {
					$captured['section_before'] = $ctx;
					return '';
				},
				'after' => null,
				'order' => 0,
				'index' => 0,
			),
		);

		$state_store = $this->createMock(FormsStateStoreInterface::class);
		$state_store->method('get_groups_map')->willReturn(array(
			'sec1' => array(
				array(
					'group_id' => 'g1',
					'type'     => 'group',
					'fields'   => array(
						array(
							'id'                => 'html',
							'label'             => '',
							'component'         => '_raw_html',
							'component_context' => array(
								'content' => function (array $ctx) use (&$captured): string {
									$captured['html_content'] = $ctx;
									return '';
								},
							),
							'order' => 0,
							'index' => 0,
						),
						array(
							'id'                => 'hr',
							'label'             => '',
							'component'         => '_hr',
							'component_context' => array('style' => ''),
							'before'            => function (array $ctx) use (&$captured): string {
								$captured['hr_before'] = $ctx;
								return '';
							},
							'after' => null,
							'order' => 1,
							'index' => 1,
						),
						array(
							'id'                => 'f1',
							'label'             => 'Field',
							'component'         => 'fields.text',
							'component_context' => array(),
							'before'            => function (array $ctx) use (&$captured): string {
								$captured['field_before'] = $ctx;
								return '';
							},
							'after' => null,
							'order' => 2,
							'index' => 2,
						),
					),
					'before' => function (array $ctx) use (&$captured): string {
						$captured['group_before'] = $ctx;
						return '';
					},
					'after' => null,
					'order' => 0,
					'index' => 0,
				),
			),
		));
		$state_store->method('get_fields_map')->willReturn(array(
			'sec1' => array(),
		));

		$session = $this->createMock(FormsServiceSession::class);
		$session->method('resolve_template')->willReturn('layout.zone.section-wrapper');
		$session->method('note_component_used');
		$session->method('render_element')->willReturn('wrapped');

		$views = $this->createMock(ComponentLoader::class);
		$views->method('render')->willReturn(new \Ran\PluginLib\Forms\Component\ComponentRenderResult(''));

		$field_renderer = $this->createMock(FormElementRenderer::class);
		$field_renderer->method('prepare_field_context')->willReturn(array());
		$field_renderer->method('render_field_with_wrapper')->willReturn('field');

		$svc = new FormsRenderService(
			$state_store,
			new CollectingLogger(),
			$views,
			$field_renderer,
			'opt',
			static function (): void {
			},
			static function () use ($session): ?FormsServiceSession {
				return $session;
			},
			static function (): string {
				return 'layout.zone.section-wrapper';
			}
		);

		$svc->render_default_sections_wrapper('container', $sections, $values);

		$required_keys = array('field_id', 'container_id', 'root_id', 'section_id', 'group_id', 'value', 'values');
		foreach (array('section_before', 'group_before', 'field_before', 'hr_before', 'html_content') as $key) {
			self::assertIsArray($captured[$key]);
			foreach ($required_keys as $required_key) {
				self::assertArrayHasKey($required_key, $captured[$key]);
			}
			self::assertSame($values, $captured[$key]['values']);
		}

		self::assertArrayHasKey('fields', $captured['group_before']);
	}

	public function test_container_has_file_uploads_detects_file_fields(): void {
		$state_store = $this->createMock(FormsStateStoreInterface::class);
		$state_store->method('get_fields_map')->willReturn(array(
			'section' => array(
				array('component' => 'fields.file-upload'),
			),
		));
		$state_store->method('get_groups_map')->willReturn(array());

		$svc = new FormsRenderService(
			$state_store,
			new CollectingLogger(),
			$this->createMock(ComponentLoader::class),
			$this->createMock(FormElementRenderer::class),
			'opt',
			static function (): void {
			},
			static function (): ?FormsServiceSession {
				return null;
			},
			static function (): string {
				return 'layout.zone.section-wrapper';
			}
		);

		self::assertTrue($svc->container_has_file_uploads('container'));
	}

	public function test_container_has_file_uploads_detects_group_file_fields(): void {
		$state_store = $this->createMock(FormsStateStoreInterface::class);
		$state_store->method('get_fields_map')->willReturn(array(
			'section' => array(
				array('component' => 'fields.text'),
			),
		));
		$state_store->method('get_groups_map')->willReturn(array(
			'section' => array(
				'group' => array(
					'fields' => array(
						array('component' => 'fields.file-upload'),
					),
				),
			),
		));

		$svc = new FormsRenderService(
			$state_store,
			new CollectingLogger(),
			$this->createMock(ComponentLoader::class),
			$this->createMock(FormElementRenderer::class),
			'opt',
			static function (): void {
			},
			static function (): ?FormsServiceSession {
				return null;
			},
			static function (): string {
				return 'layout.zone.section-wrapper';
			}
		);

		self::assertTrue($svc->container_has_file_uploads('container'));
	}

	public function test_container_has_file_uploads_returns_false_when_no_file_fields(): void {
		$state_store = $this->createMock(FormsStateStoreInterface::class);
		$state_store->method('get_fields_map')->willReturn(array(
			'section' => array(
				array('component' => 'fields.text'),
			),
		));
		$state_store->method('get_groups_map')->willReturn(array(
			'section' => array(
				'group' => array(
					'fields' => array(
						array('component' => 'fields.checkbox'),
					),
				),
			),
		));

		$svc = new FormsRenderService(
			$state_store,
			new CollectingLogger(),
			$this->createMock(ComponentLoader::class),
			$this->createMock(FormElementRenderer::class),
			'opt',
			static function (): void {
			},
			static function (): ?FormsServiceSession {
				return null;
			},
			static function (): string {
				return 'layout.zone.section-wrapper';
			}
		);

		self::assertFalse($svc->container_has_file_uploads('container'));
	}

	public function test_render_default_field_wrapper_resolves_component_builder_callables_with_stored_only_ctx(): void {
		$captured = array(
			'ctx'     => null,
			'context' => null,
		);

		$values = array(
			'f1'    => 'stored',
			'other' => 'x',
		);

		$field_item = array(
			'id'           => 'f1',
			'label'        => 'Field',
			'component'    => 'fields.select',
			'root_id'      => 'root',
			'container_id' => 'container',
			'section_id'   => 'section',
			'group_id'     => 'group',
		);

		$state_store = $this->createMock(FormsStateStoreInterface::class);
		$views       = $this->createMock(ComponentLoader::class);
		$views->method('render')->willReturn(new \Ran\PluginLib\Forms\Component\ComponentRenderResult(''));

		$session = $this->createMock(FormsServiceSession::class);
		$session->method('resolve_template')->willReturn('layout.zone.section-wrapper');
		$session->method('note_component_used');
		$session->method('render_element')->willReturn('wrapped');

		$field_renderer = $this->createMock(FormElementRenderer::class);
		$field_renderer->method('prepare_field_context')->willReturn(array(
			// Intentionally differs from stored $values['f1'] to prove callback uses stored-only values.
			'value'    => 'pending',
			'disabled' => function (array $ctx) use (&$captured): bool {
				$captured['ctx'] = $ctx;
				return isset($ctx['values']['other']) && $ctx['values']['other'] === 'x';
			},
			'required' => static function (): bool {
				return true;
			},
			'readonly' => static function (array $ctx): bool {
				return false;
			},
			'options' => static function (array $ctx): array {
				return array(
					array('value' => 'a', 'label' => 'A'),
				);
			},
			'default' => static function (array $ctx): string {
				return 'd';
			},
		));
		$field_renderer->method('render_field_with_wrapper')->willReturnCallback(function (
			string $component,
			string $field_id,
			string $label,
			array $context
		) use (&$captured): string {
			$captured['context'] = $context;
			return 'field';
		});

		$svc = new FormsRenderService(
			$state_store,
			new CollectingLogger(),
			$views,
			$field_renderer,
			'opt',
			static function (): void {
			},
			static function () use ($session): ?FormsServiceSession {
				return $session;
			},
			static function (): string {
				return 'layout.zone.section-wrapper';
			}
		);

		$svc->render_default_field_wrapper($field_item, $values);

		self::assertIsArray($captured['ctx']);
		self::assertSame('f1', $captured['ctx']['field_id']);
		self::assertSame('container', $captured['ctx']['container_id']);
		self::assertSame('root', $captured['ctx']['root_id']);
		self::assertSame('section', $captured['ctx']['section_id']);
		self::assertSame('group', $captured['ctx']['group_id']);
		self::assertSame('stored', $captured['ctx']['value']);
		self::assertSame($values, $captured['ctx']['values']);

		self::assertIsArray($captured['context']);
		self::assertArrayHasKey('disabled', $captured['context']);
		self::assertTrue($captured['context']['disabled']);
		self::assertArrayHasKey('required', $captured['context']);
		self::assertTrue($captured['context']['required']);
		self::assertArrayNotHasKey('readonly', $captured['context']);
		self::assertSame('d', $captured['context']['default']);
		self::assertIsArray($captured['context']['options']);
	}

	public function test_render_default_field_wrapper_resolves_nested_option_disabled_and_radio_group_default_callable(): void {
		$captured = array(
			'context' => null,
		);

		$values = array(
			'f1'    => 'stored',
			'other' => 'x',
		);

		$field_item = array(
			'id'           => 'f1',
			'label'        => 'Field',
			'component'    => 'radio-group',
			'root_id'      => 'root',
			'container_id' => 'container',
			'section_id'   => 'section',
			'group_id'     => 'group',
		);

		$state_store = $this->createMock(FormsStateStoreInterface::class);
		$views       = $this->createMock(ComponentLoader::class);
		$views->method('render')->willReturn(new \Ran\PluginLib\Forms\Component\ComponentRenderResult(''));

		$session = $this->createMock(FormsServiceSession::class);
		$session->method('resolve_template')->willReturn('layout.zone.section-wrapper');
		$session->method('note_component_used');
		$session->method('render_element')->willReturn('wrapped');

		$field_renderer = $this->createMock(FormElementRenderer::class);
		$field_renderer->method('prepare_field_context')->willReturn(array(
			'default' => static function (array $ctx): string {
				return 'b';
			},
			'options' => array(
				array(
					'value'    => 'a',
					'label'    => 'A',
					'disabled' => static function (array $ctx): bool {
						return false;
					},
				),
				array(
					'value'    => 'b',
					'label'    => 'B',
					'disabled' => static function (array $ctx): bool {
						return isset($ctx['values']['other']) && $ctx['values']['other'] === 'x';
					},
				),
			),
		));
		$field_renderer->method('render_field_with_wrapper')->willReturnCallback(function (
			string $component,
			string $field_id,
			string $label,
			array $context
		) use (&$captured): string {
			$captured['context'] = $context;
			return 'field';
		});

		$svc = new FormsRenderService(
			$state_store,
			new CollectingLogger(),
			$views,
			$field_renderer,
			'opt',
			static function (): void {
			},
			static function () use ($session): ?FormsServiceSession {
				return $session;
			},
			static function (): string {
				return 'layout.zone.section-wrapper';
			}
		);

		$svc->render_default_field_wrapper($field_item, $values);

		self::assertIsArray($captured['context']);
		self::assertSame('b', $captured['context']['default']);
		self::assertIsArray($captured['context']['options']);
		self::assertArrayNotHasKey('disabled', $captured['context']['options'][0]);
		self::assertArrayHasKey('disabled', $captured['context']['options'][1]);
		self::assertTrue($captured['context']['options'][1]['disabled']);
		self::assertIsArray($captured['context']['options'][1]['attributes']);
		self::assertSame('disabled', $captured['context']['options'][1]['attributes']['disabled']);
		self::assertArrayHasKey('checked', $captured['context']['options'][1]);
		self::assertTrue($captured['context']['options'][1]['checked']);
	}
}
