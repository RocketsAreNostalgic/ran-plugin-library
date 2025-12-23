<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Metaboxes;

use WP_Mock;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Metaboxes\Metaboxes;
use Ran\PluginLib\Metaboxes\MetaboxForm;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;

/**
 * @covers \Ran\PluginLib\Metaboxes\Metaboxes
 */
final class MetaboxesRegisterMetaboxesBehaviorTest extends PluginLibTestCase {
	private CollectingLogger $logger;
	private ComponentManifest $manifest;
	private RegisterOptions $baseOptions;

	public function setUp(): void {
		parent::setUp();

		$this->logger = $this->logger_mock instanceof CollectingLogger
			? $this->logger_mock
			: new CollectingLogger(array());
		$this->logger->collected_logs = array();

		WP_Mock::userFunction('sanitize_key')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_html_class')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
		WP_Mock::userFunction('wp_kses_post')->andReturnArg(0);

		$loader         = new ComponentLoader(__DIR__ . '/../../../inc/Forms/Components', $this->logger);
		$this->manifest = new ComponentManifest($loader, $this->logger);

		$this->baseOptions = new RegisterOptions(
			'base_metaboxes_site_options',
			StorageContext::forSite(),
			false,
			$this->logger
		);

		$this->baseOptions->register_schema(array(
			'noop' => array(
				'default'  => '',
				'sanitize' => static fn ($value): string => (string) $value,
				'validate' => static fn ($value): bool => true,
			),
		));
	}

	public function test_register_metaboxes_registers_matching_post_type(): void {
		$metaboxes = new Metaboxes($this->baseOptions, $this->manifest, null, $this->logger);

		$metabox_id = 'book_details';
		$post_type  = 'post';

		/** @var MetaboxForm&\PHPUnit\Framework\MockObject\MockObject $form */
		$form = $this->getMockBuilder(MetaboxForm::class)
			->disableOriginalConstructor()
			->onlyMethods(array('get_post_types', 'get_title', 'get_context', 'get_priority', '__render'))
			->getMock();

		$form->method('get_post_types')->willReturn(array('post'));
		$form->method('get_title')->willReturn('Book Details');
		$form->method('get_context')->willReturn('advanced');
		$form->method('get_priority')->willReturn('default');

		$form->expects(self::once())
			->method('__render')
			->with(
				$metabox_id,
				self::callback(static function ($ctx): bool {
					if (!is_array($ctx)) {
						return false;
					}
					return ($ctx['post_id'] ?? null) === 123
						&& isset($ctx['post'])
						&& is_object($ctx['post'])
						&& ($ctx['post']->ID ?? null) === 123
						&& isset($ctx['box'])
						&& is_array($ctx['box']);
				})
			);

		$this->injectMetabox($metaboxes, $metabox_id, $form);

		$captured_callback = null;
		WP_Mock::userFunction('add_meta_box')
			->with(
				$metabox_id,
				'Book Details',
				\WP_Mock\Functions::type('callable'),
				$post_type,
				'advanced',
				'default',
				array()
			)
			->andReturnUsing(static function (
				string $id,
				string $title,
				callable $callback
			) use (&$captured_callback): void {
				$captured_callback = $callback;
			});

		$metaboxes->__register_metaboxes($post_type, (object) array('ID' => 123));

		self::assertIsCallable($captured_callback);

		$captured_callback((object) array('ID' => 123), array('id' => $metabox_id));
	}

	public function test_register_metaboxes_skips_non_matching_post_type(): void {
		$metaboxes = new Metaboxes($this->baseOptions, $this->manifest, null, $this->logger);

		/** @var MetaboxForm&\PHPUnit\Framework\MockObject\MockObject $form */
		$form = $this->getMockBuilder(MetaboxForm::class)
			->disableOriginalConstructor()
			->onlyMethods(array('get_post_types', 'get_title', 'get_context', 'get_priority', '__render'))
			->getMock();

		$form->method('get_post_types')->willReturn(array('page'));
		$form->method('get_title')->willReturn('Book Details');
		$form->method('get_context')->willReturn('advanced');
		$form->method('get_priority')->willReturn('default');

		$form->expects(self::never())->method('__render');

		$this->injectMetabox($metaboxes, 'book_details', $form);

		WP_Mock::userFunction('add_meta_box')->times(0);

		$metaboxes->__register_metaboxes('post', (object) array('ID' => 123));
		self::assertTrue(true);
	}

	private function injectMetabox(Metaboxes $metaboxes, string $metabox_id, MetaboxForm $form): void {
		$ref  = new \ReflectionObject($metaboxes);
		$prop = $ref->getProperty('metaboxes');
		$prop->setAccessible(true);

		$current = $prop->getValue($metaboxes);
		if (!is_array($current)) {
			$current = array();
		}
		$current[$metabox_id] = $form;

		$prop->setValue($metaboxes, $current);
	}
}
