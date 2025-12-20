<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Forms\Services\FormsStateStore;
use Ran\PluginLib\Forms\Services\FormsRenderService;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsTemplateOverrideResolver;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use PHPUnit\Framework\TestCase;

final class CallableKeysConstantRegistrationTest extends TestCase {
	public function test_callable_keys_constant_nested_rules_are_registered_and_resolved(): void {
		$logger = new CollectingLogger();

		$manifest = $this->createMock(ComponentManifest::class);
		$manifest->method('builder_classes')->willReturn(array(CallableKeysConstantTestBuilder::class));

		$session = new FormsServiceSession(
			$manifest,
			new FormsTemplateOverrideResolver($logger),
			$logger
		);

		$nested_rules = $session->callable_registry()->nested_rules();
		self::assertArrayHasKey('items.*.label', $nested_rules);
		self::assertSame('string', $nested_rules['items.*.label']);

		$captured_context = null;

		$field_renderer = $this->createMock(FormElementRenderer::class);
		$field_renderer
			->method('prepare_field_context')
			->willReturn(array(
				'items' => array(
					array(
						'label' => static function (array $ctx): string {
							return 'label-' . (string) $ctx['field_id'];
						},
					),
					array(
						'label' => 'already-string',
					),
				),
			));

		$field_renderer
			->method('render_field_with_wrapper')
			->willReturnCallback(function (
				string $component,
				string $field_id,
				string $label,
				array $context
			) use (&$captured_context): string {
				$captured_context = $context;
				return '';
			});

		$views = $this->createMock(\Ran\PluginLib\Forms\Component\ComponentLoader::class);

		$svc = new FormsRenderService(
			$this->createMock(FormsStateStore::class),
			$logger,
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

		$svc->render_default_field_wrapper(
			array(
				'field' => array(
					'id'                => 'myfield',
					'label'             => 'My Field',
					'component'         => 'fields.text',
					'component_context' => array(),
				),
				'container_id' => 'container',
				'root_id'      => 'root',
				'section_id'   => 'section',
				'group_id'     => 'group',
			),
			array('myfield' => 'stored')
		);

		self::assertIsArray($captured_context);
		self::assertIsArray($captured_context['items'] ?? null);
		self::assertSame('label-myfield', $captured_context['items'][0]['label']);
		self::assertSame('already-string', $captured_context['items'][1]['label']);
	}
}

final class CallableKeysConstantTestBuilder {
	public const CALLABLE_KEYS = array(
		'nested_rules' => array(
			'items.*.label' => 'string',
		),
	);
}
