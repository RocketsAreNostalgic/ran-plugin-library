<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;

final class RegisterOptionsFluentMethodsTest extends PluginLibTestCase {
	private ?CollectingLogger $logger = null;

	public function setUp(): void {
		parent::setUp();
		$this->logger = new CollectingLogger(array());

		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_site_option')->andReturn(array());
		WP_Mock::userFunction('get_blog_option')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('update_user_meta')->andReturn(true);
		WP_Mock::userFunction('update_site_option')->andReturn(true);
		WP_Mock::userFunction('update_blog_option')->andReturn(true);
		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))->reply(true);
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(static function ($key) {
			$key = strtolower($key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});
		WP_Mock::userFunction('sanitize_html_class')->andReturnUsing(static function ($class, $fallback = '') {
			$class = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $class) ?? '';
			$class = trim($class, '-');
			if ($class === '' && $fallback !== '') {
				$fallback = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $fallback) ?? '';
				return trim($fallback, '-');
			}
			return $class;
		});
	}

	public function tearDown(): void {
		if (function_exists('gc_collect_cycles')) {
			gc_collect_cycles();
		}
		parent::tearDown();
	}

	public function test_register_schema_applies_defined_callables(): void {
		$options = new RegisterOptions('register_schema_basic', StorageContext::forSite(), true, $this->logger);

		$options->register_schema(array(
			'example' => array(
				'sanitize' => array(
					static function ($value, callable $emitNotice) {
						$emitNotice('trim');
						return trim((string) $value);
					},
					static function($value, callable $emitNotice) {
						$emitNotice('lower');
						return strtolower((string) $value);
					},
				),
				'validate' => array(
					static function($value, callable $emitWarning) {
						if ($value === '') {
							$emitWarning('empty');
							return false;
						}
						return true;
					},
					static function($value, callable $emitWarning) {
						if (!ctype_alpha((string) $value)) {
							$emitWarning('alpha');
							return false;
						}
						return true;
					},
				),
			),
		));

		$options->stage_option('example', '  Hello  ');
		self::assertTrue($options->commit_merge());
		self::assertSame('hello', $options->get_option('example'));

		$messages = $options->take_messages();
		self::assertEqualsCanonicalizing(array('trim', 'lower'), $messages['example']['notices']);
		self::assertSame(array(), $messages['example']['warnings']);

		$options->stage_option('example', '1234');
		self::assertFalse($options->commit_merge());
		$messages = $options->take_messages();
		self::assertContains('alpha', $messages['example']['warnings']);
	}

	public function test_with_schema_merges_new_callables(): void {
		$options = new RegisterOptions('with_schema_merge', StorageContext::forSite(), true, $this->logger);

		$options->register_schema(array(
			'field_two' => array(
				'sanitize' => array(
					static function($value, callable $emitNotice) {
						$emitNotice('trim');
						return trim((string) $value);
					},
				),
				'validate' => array(
					static function($value, callable $emitWarning) {
						if ($value === '') {
							$emitWarning('required');
							return false;
						}
						return true;
					},
				),
			),
		));

		$options->with_schema(array(
			'field_two' => array(
				'sanitize' => array(
					static function($value, callable $emitNotice) {
						$emitNotice('dedupe');
						$normalized = preg_replace('/\s+/', ' ', (string) $value) ?? '';
						return trim($normalized);
					},
				),
				'validate' => array(
					static function($value, callable $emitWarning) {
						if (strlen((string) $value) > 10) {
							$emitWarning('length');
							return false;
						}
						return true;
					},
				),
			),
		));

		$options->stage_option('field_two', '  Short  ');
		self::assertTrue($options->commit_merge());
		self::assertSame('Short', $options->get_option('field_two'));

		$options->stage_option('field_two', 'excessively long value');
		self::assertFalse($options->commit_merge());
		$messages = $options->take_messages();
		self::assertContains('length', $messages['field_two']['warnings']);
	}

	public function test_get_schema_returns_empty_arrays_when_missing_buckets(): void {
		$options = new RegisterOptions('get_schema_defaults', StorageContext::forSite(), true, $this->logger);
		$options->register_schema(array(
			'partial' => array(
				'validate' => array('is_string'),
			),
		));

		$schema = $options->get_schema();
		self::assertArrayHasKey('partial', $schema);
		self::assertSame(array(), $schema['partial']['sanitize']);
		self::assertSame(array('is_string'), $schema['partial']['validate']);
	}

	public function test_with_schema_appends_additional_callables(): void {
		$options = new RegisterOptions('with_schema_replace', StorageContext::forSite(), true, $this->logger);

		$options->register_schema(array(
			'title' => array(
				'validate' => array('strlen'),
			),
		));

		$options->with_schema(array(
			'title' => array(
				'validate' => array('ctype_alpha', 'ctype_upper'),
			),
		));

		$schema = $options->get_schema();
		self::assertSame(array('strlen', 'ctype_alpha', 'ctype_upper'), $schema['title']['validate']);
	}

	public function test_logging_only_reports_schema_bucket(): void {
		$options = new RegisterOptions('logging_schema_bucket', StorageContext::forSite(), true, $this->logger);

		$options->register_schema(array(
			'log_field' => array(
				'sanitize' => array(
					static function ($value) {
						return trim((string) $value);
					},
				),
				'validate' => array(
					static function ($value) {
						return $value !== '';
					},
				),
			),
		));

		$options->stage_option('log_field', ' example ');
		self::assertTrue($options->commit_merge());

		$logs             = $this->logger->get_logs();
		$sanitizerBuckets = array();
		$validatorBuckets = array();
		foreach ($logs as $record) {
			if (($record['message'] ?? '') === 'RegisterOptions: running sanitizer') {
				$sanitizerBuckets[] = $record['context']['bucket'] ?? '';
			}
			if (($record['message'] ?? '') === 'RegisterOptions: running validator') {
				$validatorBuckets[] = $record['context']['bucket'] ?? '';
			}
		}

		self::assertNotEmpty($sanitizerBuckets);
		self::assertNotEmpty($validatorBuckets);
		self::assertEquals(array('schema'), array_values(array_unique($sanitizerBuckets)));
		self::assertEquals(array('schema'), array_values(array_unique($validatorBuckets)));
	}

	public function test_schema_merges_preserve_callable_order(): void {
		$options = new RegisterOptions('with_schema_appends', StorageContext::forSite(), true, $this->logger);

		$validateOrder = array();

		$options->register_schema(array(
			'test_field' => array(
				'sanitize' => array(
					static function($value, callable $emitNotice) {
						$emitNotice('register');
						$value = (string) $value;
						return str_contains($value, '_r') ? $value : $value . '_r';
					},
				),
				'validate' => array(
					static function($value, callable $emitWarning) use (&$validateOrder) {
						$validateOrder[] = 'register';
						return true;
					},
				),
			),
		));

		$options->with_schema(array(
			'test_field' => array(
				'sanitize' => array(
					static function($value, callable $emitNotice) {
						$emitNotice('with_schema_one');
						$value = (string) $value;
						return str_contains($value, '_ws1') ? $value : $value . '_ws1';
					},
					static function($value, callable $emitNotice) {
						$emitNotice('with_schema_two');
						$value = (string) $value;
						return str_contains($value, '_ws2') ? $value : $value . '_ws2';
					},
				),
				'validate' => array(
					static function($value, callable $emitWarning) use (&$validateOrder) {
						$validateOrder[] = 'with_schema';
						return true;
					},
				),
			),
		));

		$options->stage_option('test_field', 'value');
		self::assertTrue($options->commit_merge());
		$messages = $options->take_messages();
		self::assertSame(array('register', 'with_schema_one', 'with_schema_two'), $messages['test_field']['notices']);
		self::assertSame(array('register', 'with_schema'), $validateOrder);
		self::assertSame('value_r_ws1_ws2', $options->get_option('test_field'));
	}

	public function test_validator_execution_stops_on_failure(): void {
		$options = new RegisterOptions('validator_order', StorageContext::forSite(), true, $this->logger);

		$execution = array();

		$options->register_schema(array(
			'test_field' => array(
				'validate' => array(
					static function($value, callable $emitWarning) use (&$execution) {
						$execution[] = 'first';
						if ($value === 'fail') {
							$emitWarning('first failed');
							return false;
						}
						return true;
					},
					static function($value, callable $emitWarning) use (&$execution) {
						$execution[] = 'second';
						return true;
					},
				),
			),
		));

		$options->stage_option('test_field', 'ok');
		self::assertTrue($options->commit_merge());
		self::assertSame(array('first', 'second'), $execution);
		$options->take_messages();

		$execution = array();
		$options->stage_option('test_field', 'fail');
		self::assertFalse($options->commit_merge());
		$messages = $options->take_messages();
		self::assertSame(array('first'), $execution);
		self::assertContains('first failed', $messages['test_field']['warnings']);
	}

	public function test_multiple_sanitizers_execute_in_order(): void {
		$options = new RegisterOptions('test_fluent_methods', StorageContext::forSite(), true, $this->logger_mock);

		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'sanitize' => array(
					static function($value, callable $emitNotice) {
						$emitNotice('first');
						$value = (string) $value;
						return str_contains($value, '_first') ? $value : $value . '_first';
					},
				),
				'validate' => array(
					static function($value, callable $emitWarning) {
						if (strpos((string) $value, 'fail') !== false) {
							$emitWarning('Contains fail');
							return false;
						}
						return true;
					},
				),
			),
		));

		$options->with_schema(array(
			'test_field' => array(
				'sanitize' => array(
					static function($value, callable $emitNotice) {
						$emitNotice('second');
						$value = (string) $value;
						return str_contains($value, '_second') ? $value : $value . '_second';
					},
				),
			),
		));

		$options->with_schema(array(
			'test_field' => array(
				'sanitize' => array(
					static function($value, callable $emitNotice) {
						$emitNotice('third');
						$value = (string) $value;
						return str_contains($value, '_third') ? $value : $value . '_third';
					},
				),
			),
		));

		$options->stage_option('test_field', 'test');
		self::assertTrue($options->commit_merge());
		$messages = $options->take_messages();
		self::assertSame(array('first', 'second', 'third'), $messages['test_field']['notices']);
		self::assertSame('test_first_second_third', $options->get_option('test_field'));
	}

	public function test_sanitizer_and_validator_logging_includes_bucket_context(): void {
		$options = new RegisterOptions('test_logging', StorageContext::forSite(), true, $this->logger_mock);

		$options->register_schema(array(
			'log_field' => array(
				'default'  => '',
				'sanitize' => array(
					static function($value, callable $emitNotice) {
						return strtolower(trim((string) $value));
					},
					static function($value, callable $emitNotice) {
						return strtoupper((string) $value);
					},
				),
				'validate' => array(
					static function($value, callable $emitWarning) {
						if ($value === '') {
							$emitWarning('component validator requires value');
							return false;
						}
						return true;
					},
					static function($value, callable $emitWarning) {
						return strlen((string) $value) < 20;
					},
				),
			),
		));

		$beforeCount = count($this->logger_mock->get_logs());
		$options->stage_option('log_field', '  Example  ');
		$logs = array_slice($this->logger_mock->get_logs(), $beforeCount);

		$sanitizerBuckets = array();
		foreach ($logs as $idx => $record) {
			if ($record['message'] !== 'RegisterOptions: running sanitizer') {
				continue;
			}
			if (!isset($record['context']['bucket'])) {
				continue;
			}
			$bucket                      = $record['context']['bucket'];
			$sanitizerBuckets[$bucket][] = $idx;
		}

		self::assertArrayHasKey('schema', $sanitizerBuckets);
		self::assertArrayNotHasKey('component', $sanitizerBuckets);
		self::assertNotEmpty($sanitizerBuckets['schema']);

		$validatorBuckets = array();
		foreach ($logs as $idx => $record) {
			if ($record['message'] !== 'RegisterOptions: running validator') {
				continue;
			}
			if (!isset($record['context']['bucket'])) {
				continue;
			}
			$bucket                      = $record['context']['bucket'];
			$validatorBuckets[$bucket][] = $idx;
		}

		self::assertArrayHasKey('schema', $validatorBuckets);
		self::assertArrayNotHasKey('component', $validatorBuckets);
		self::assertNotEmpty($validatorBuckets['schema']);
	}
}
