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
