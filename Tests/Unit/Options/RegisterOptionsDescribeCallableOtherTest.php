<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

final class RegisterOptionsDescribeCallableOtherTest extends PluginLibTestCase {
	use ExpectLogTrait;

	public function setUp(): void {
		parent::setUp();
		// Common WP stubs to avoid calling real WP functions during constructor
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_site_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_blog_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('get_user_meta')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function($k) {
			$k = strtolower((string)$k);
			$k = preg_replace('/[^a-z0-9_\-]+/i', '_', $k) ?? '';
			return trim($k, '_');
		});
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::_describe_callable
	 * Ensures non-array, non-closure, non-invokable objects hit the (other) branch
	 * and are formatted as 'callable'.
	 */
	public function test_describe_callable_other_branch_for_non_invokable_object(): void {
		$config = $this->getMockBuilder(ConfigInterface::class)->getMock();
		$config->method('get_options_key')->willReturn('opts_call_other_str');
		$config->method('get_logger')->willReturn($this->logger_mock);
		$opts = new RegisterOptions($config->get_options_key(), StorageContext::forSite(), true, $this->logger_mock);

		// Invoke protected _describe_callable via reflection with an object
		// that is NOT invokable (no __invoke), not array, not string, not closure.
		$ref = new \ReflectionClass($opts);
		$m   = $ref->getMethod('_describe_callable');
		$m->setAccessible(true);
		$result = $m->invoke($opts, new \stdClass());
		$this->assertSame('callable', $result);
		// Expect diagnostic log indicating the 'other' branch
		$this->expectLog('debug', '_describe_callable completed (other)');
	}
}
