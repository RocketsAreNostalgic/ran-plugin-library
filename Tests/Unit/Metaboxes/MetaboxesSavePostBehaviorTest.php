<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Metaboxes;

use WP_Mock;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Metaboxes\Metaboxes;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;

/**
 * @covers \Ran\PluginLib\Metaboxes\Metaboxes
 * @covers \Ran\PluginLib\Metaboxes\MetaboxForm
 */
final class MetaboxesSavePostBehaviorTest extends PluginLibTestCase {
	private CollectingLogger $logger;
	private ComponentManifest $manifest;
	private RegisterOptions $baseOptions;
	/** @var array<string,mixed> */
	private array $optionValues = array();

	public function setUp(): void {
		parent::setUp();

		$this->logger = $this->logger_mock instanceof CollectingLogger
			? $this->logger_mock
			: new CollectingLogger(array());
		$this->logger->collected_logs = array();

		// WP functions commonly used throughout forms/options.
		WP_Mock::userFunction('sanitize_key')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_html_class')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
		WP_Mock::userFunction('wp_kses_post')->andReturnArg(0);

		// Transients for message persistence.
		WP_Mock::userFunction('get_transient')->andReturn(false);
		WP_Mock::userFunction('set_transient')->andReturn(true);
		WP_Mock::userFunction('delete_transient')->andReturn(true);

		// Option API used by component cache transient tracking.
		$this->optionValues = array();
		$self               = $this;
		WP_Mock::userFunction('get_option')->andReturnUsing(static function (string $option, mixed $default = false) use ($self) {
			return array_key_exists($option, $self->optionValues) ? $self->optionValues[$option] : $default;
		});
		WP_Mock::userFunction('update_option')->andReturnUsing(static function (string $option, mixed $value, mixed $autoload = null) use ($self) {
			$self->optionValues[$option] = $value;
			return true;
		});

		// Post meta IO.
		WP_Mock::userFunction('get_post_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('update_post_meta')->andReturn(true)->byDefault();
		WP_Mock::userFunction('delete_post_meta')->andReturn(true)->byDefault();

		// Write gate filters.
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(
				\WP_Mock\Functions::type('bool'),
				\WP_Mock\Functions::type('array')
			)
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/post')
			->with(
				\WP_Mock\Functions::type('bool'),
				\WP_Mock\Functions::type('array')
			)
			->reply(true);

		$loader         = new ComponentLoader(__DIR__ . '/../../../inc/Forms/Components', $this->logger);
		$this->manifest = new ComponentManifest($loader, $this->logger);

		$this->registerTemplateStubs();

		$this->baseOptions = new RegisterOptions(
			'base_metaboxes_site_options',
			StorageContext::forSite(),
			false,
			$this->logger
		);
		$this->baseOptions->register_schema(array(
			'valid_field' => array(
				'default'  => '',
				'sanitize' => static fn ($value): string => (string) $value,
				'validate' => static function ($value, callable $emitWarning): bool {
					if (!is_string($value)) {
						$emitWarning('valid_field must be a string');
						return false;
					}
					return true;
				},
			),
		));

		$_POST  = array();
		$_FILES = array();
	}

	public function tearDown(): void {
		$_POST  = array();
		$_FILES = array();
		parent::tearDown();
	}

	public function test_save_post_bails_on_autosave(): void {
		$metaboxes = $this->createMetaboxes();
		$metaboxes->metabox('book_details', 'Book Details', 'book_meta', array(
			'post_types' => array('post'),
		));

		WP_Mock::userFunction('wp_is_post_autosave')->with(123)->andReturn(999);
		WP_Mock::userFunction('wp_is_post_revision')->with(123)->andReturn(false);

		WP_Mock::userFunction('update_post_meta')->times(0);
		WP_Mock::userFunction('wp_verify_nonce')->times(0);

		$metaboxes->__save_metaboxes(123, (object) array('post_type' => 'post'), true);
		self::assertTrue(true);
	}

	public function test_save_post_bails_on_revision(): void {
		$metaboxes = $this->createMetaboxes();
		$metaboxes->metabox('book_details', 'Book Details', 'book_meta', array(
			'post_types' => array('post'),
		));

		WP_Mock::userFunction('wp_is_post_autosave')->with(123)->andReturn(false);
		WP_Mock::userFunction('wp_is_post_revision')->with(123)->andReturn(555);

		WP_Mock::userFunction('update_post_meta')->times(0);
		WP_Mock::userFunction('wp_verify_nonce')->times(0);

		$metaboxes->__save_metaboxes(123, (object) array('post_type' => 'post'), true);
		self::assertTrue(true);
	}

	public function test_save_post_bails_when_capability_fails(): void {
		$metaboxes = $this->createMetaboxes();
		$metaboxes->metabox('book_details', 'Book Details', 'book_meta', array(
			'post_types' => array('post'),
		));

		WP_Mock::userFunction('wp_is_post_autosave')->with(123)->andReturn(false);
		WP_Mock::userFunction('wp_is_post_revision')->with(123)->andReturn(false);
		WP_Mock::userFunction('current_user_can')->with('edit_post', 123)->andReturn(false);

		WP_Mock::userFunction('update_post_meta')->times(0);
		WP_Mock::userFunction('wp_verify_nonce')->times(0);

		$metaboxes->__save_metaboxes(123, (object) array('post_type' => 'post'), true);
		self::assertTrue(true);
	}

	public function test_save_post_skips_metabox_when_payload_missing(): void {
		$metaboxes = $this->createMetaboxes();
		$metaboxes->metabox('book_details', 'Book Details', 'book_meta', array(
			'post_types' => array('post'),
		));

		WP_Mock::userFunction('wp_is_post_autosave')->with(123)->andReturn(false);
		WP_Mock::userFunction('wp_is_post_revision')->with(123)->andReturn(false);
		WP_Mock::userFunction('current_user_can')->with('edit_post', 123)->andReturn(true);

		WP_Mock::userFunction('update_post_meta')->times(0);
		WP_Mock::userFunction('wp_verify_nonce')->times(0);

		$metaboxes->__save_metaboxes(123, (object) array('post_type' => 'post'), true);
		self::assertTrue(true);
	}

	public function test_save_post_checks_nonce_when_files_present_but_payload_missing(): void {
		$metaboxes = $this->createMetaboxes();
		$metaboxes->metabox('book_details', 'Book Details', 'book_meta', array(
			'post_types' => array('post'),
		));

		WP_Mock::userFunction('wp_is_post_autosave')->with(123)->andReturn(false);
		WP_Mock::userFunction('wp_is_post_revision')->with(123)->andReturn(false);
		WP_Mock::userFunction('current_user_can')->with('edit_post', 123)->andReturn(true);

		$_FILES['book_meta'] = array(
			'name' => array(
				'file_required' => 'example.pdf',
			),
		);
		$_POST['book_meta__nonce'] = 'bad_nonce';

		WP_Mock::userFunction('wp_verify_nonce')
			->with('bad_nonce', \WP_Mock\Functions::type('string'))
			->andReturn(false);

		WP_Mock::userFunction('update_post_meta')->times(0);

		$metaboxes->__save_metaboxes(123, (object) array('post_type' => 'post'), true);
		self::assertTrue(true);
	}

	public function test_save_post_skips_metabox_when_nonce_missing(): void {
		$metaboxes = $this->createMetaboxes();
		$metaboxes->metabox('book_details', 'Book Details', 'book_meta', array(
			'post_types' => array('post'),
		));

		WP_Mock::userFunction('wp_is_post_autosave')->with(123)->andReturn(false);
		WP_Mock::userFunction('wp_is_post_revision')->with(123)->andReturn(false);
		WP_Mock::userFunction('current_user_can')->with('edit_post', 123)->andReturn(true);

		$_POST['book_meta'] = array('valid_field' => 'hello');

		WP_Mock::userFunction('update_post_meta')->times(0);
		WP_Mock::userFunction('wp_verify_nonce')->times(0);

		$metaboxes->__save_metaboxes(123, (object) array('post_type' => 'post'), true);
		self::assertTrue(true);
	}

	public function test_save_post_skips_metabox_when_nonce_invalid(): void {
		$metaboxes = $this->createMetaboxes();
		$metaboxes->metabox('book_details', 'Book Details', 'book_meta', array(
			'post_types' => array('post'),
		));

		WP_Mock::userFunction('wp_is_post_autosave')->with(123)->andReturn(false);
		WP_Mock::userFunction('wp_is_post_revision')->with(123)->andReturn(false);
		WP_Mock::userFunction('current_user_can')->with('edit_post', 123)->andReturn(true);

		$_POST['book_meta']        = array('valid_field' => 'hello');
		$_POST['book_meta__nonce'] = 'bad_nonce';

		WP_Mock::userFunction('wp_verify_nonce')
			->with('bad_nonce', \WP_Mock\Functions::type('string'))
			->andReturn(false);

		WP_Mock::userFunction('update_post_meta')->times(0);

		$metaboxes->__save_metaboxes(123, (object) array('post_type' => 'post'), true);
		self::assertTrue(true);
	}

	public function test_save_post_persists_when_payload_and_nonce_valid(): void {
		$metaboxes = $this->createMetaboxes();
		$metaboxes->metabox('book_details', 'Book Details', 'book_meta', array(
			'post_types' => array('post'),
		));

		WP_Mock::userFunction('wp_is_post_autosave')->with(123)->andReturn(false);
		WP_Mock::userFunction('wp_is_post_revision')->with(123)->andReturn(false);
		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true);

		$_POST['book_meta']        = array('valid_field' => 'hello');
		$_POST['book_meta__nonce'] = 'good_nonce';

		WP_Mock::userFunction('wp_verify_nonce')
			->with('good_nonce', \WP_Mock\Functions::type('string'))
			->andReturn(1);

		// Simulate no existing meta (initial read).
		WP_Mock::userFunction('get_post_meta')->with(123, 'book_meta', true)->andReturn(array());

		$captured = null;
		$called   = null;
		WP_Mock::userFunction('update_post_meta')
			->withAnyArgs()
			->times(1)
			->andReturnUsing(static function (int $post_id, string $key, mixed $value, mixed $prev_value = '') use (&$captured, &$called): bool {
				$called = array(
					'post_id'    => $post_id,
					'key'        => $key,
					'prev_value' => $prev_value,
					'value_type' => gettype($value),
				);
				if (is_array($value)) {
					$captured = $value;
				}
				return true;
			});

		$metaboxes->__save_metaboxes(123, (object) array('post_type' => 'post'), true);

		self::assertIsArray($called);
		self::assertSame(123, $called['post_id'] ?? null);
		self::assertSame('book_meta', $called['key'] ?? null);
		self::assertSame('array', $called['value_type'] ?? null);
		self::assertIsArray($captured);
		self::assertSame('hello', $captured['valid_field'] ?? null);
	}

	private function createMetaboxes(): Metaboxes {
		return new Metaboxes($this->baseOptions, $this->manifest, null, $this->logger);
	}

	private function registerTemplateStubs(): void {
		$loader = $this->manifest->get_component_loader();
		$loader->register('layout.zone.section-wrapper', 'admin/sections/test-section.php');
		$loader->register('section-wrapper', 'admin/sections/test-section.php');
		$loader->register('layout.field.field-wrapper', 'admin/fields/example-field-wrapper.php');
		$loader->register('field-wrapper', 'admin/fields/example-field-wrapper.php');
		$loader->register('shared.field-wrapper', 'admin/fields/example-field-wrapper.php');
	}
}
