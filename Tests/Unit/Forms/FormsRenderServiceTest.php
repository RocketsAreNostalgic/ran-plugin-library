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
			'container' => array(
				'sec1' => array(
					'g1' => array(
						'group_id' => 'g1',
						'type'     => 'fieldset',
						'fields'   => array(
							array(
								'id'                => 'html1',
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
								'id'                => 'hr1',
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
			),
		));
		$state_store->method('get_fields_map')->willReturn(array(
			'container' => array(
				'sec1' => array(),
			),
		));

		$session = $this->createMock(FormsServiceSession::class);
		$session->method('resolve_template')->willReturn('layout.zone.section-wrapper');
		$session->method('note_component_used')->willReturn(null);
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
}
