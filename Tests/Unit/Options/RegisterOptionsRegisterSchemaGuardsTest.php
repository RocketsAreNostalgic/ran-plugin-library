<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Focused tests for register_schema() guard clauses:
 * - validate is required and must be callable (lines 347–348)
 * - sanitize, when present and non-null, must be callable (lines 351–352)
 */
final class RegisterOptionsRegisterSchemaGuardsTest extends PluginLibTestCase {
	public function setUp(): void {
		parent::setUp();
		// Minimal WP stubs used by options during construction/reads
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array())->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function($k) {
			$k = strtolower((string)$k);
			$k = preg_replace('/[^a-z0-9_\-]+/i', '_', $k) ?? '';
			return trim($k, '_');
		});
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_allows_missing_validate_key(): void {
		$opts = RegisterOptions::site('schema_guard_validate', true, $this->logger_mock);

		self::assertArrayNotHasKey('no_validate', $opts->__get_schema_internal());
		self::assertFalse($opts->register_schema(array(
		    'no_validate' => array(
		        'default' => 'x',
		    ),
		)));
		$internalSchema = $opts->__get_schema_internal();
		self::assertArrayHasKey('no_validate', $internalSchema);
		self::assertSame(array(), $internalSchema['no_validate']['validate']['component']);
		self::assertSame(array(), $internalSchema['no_validate']['validate']['schema']);
	}

	/**
	 * @covers \Ran\PluginLib\Options\RegisterOptions::register_schema
	 */
	public function test_register_schema_throws_when_sanitize_present_and_non_callable(): void {
		$opts = RegisterOptions::site('schema_guard_sanitize', true, $this->logger_mock);

		// sanitize provided but not callable (and not null)
		$this->expectException(\InvalidArgumentException::class);
		$opts->register_schema(array(
		    'bad_sanitize' => array(
		        'sanitize' => 123,
		        'validate' => function($v) {
		        	return true;
		        },
		    ),
		));
	}
}
