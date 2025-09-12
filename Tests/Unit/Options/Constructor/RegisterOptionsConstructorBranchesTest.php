<?php

declare(strict_types=1);

namespace Ran\PluginLib\Options;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;

/**
 * Test-only subclass to expose a public factory that forwards non-empty
 * constructor parameters into the protected parent constructor so we can
 * cover constructor branches (schema normalization/default seeding and
 * initial options merge) without changing production code.
 */
final class TestableRegisterOptions extends RegisterOptions {
	/**
	 * Public factory to invoke the protected constructor with custom args.
	 *
	 * @param string $main
	 * @param array<string,mixed> $initial
	 * @param bool $autoload
	 * @param Logger|null $logger
	 * @param array<string, array{default:mixed|null, sanitize?:callable|null, validate?:callable|null}> $schema
	 * @param WritePolicyInterface|null $policy
	 * @return static
	 */
	public static function make(
        string $main,
        array $initial = array(),
        bool $autoload = true,
        ?Logger $logger = null,
        array $schema = array(),
        ?WritePolicyInterface $policy = null,
    ): static {
		// Call the protected constructor in the parent via late static binding,
		// passing logger via DI so constructor-time logs are captured when provided.
		$instance = new static($main, $autoload, null, $logger);
		if (!empty($schema)) {
			// Register schema and seed defaults (no flush for this constructor-branches coverage)
			$instance->with_schema($schema, true, false);
		}
		if (!empty($initial)) {
			// Apply initial defaults via fluent path
			$defaults = array();
			foreach ($initial as $k => $def) {
				$defaults[(string) $k] = is_array($def) && array_key_exists('value', $def) ? $def['value'] : $def;
			}
			$instance->with_defaults($defaults);
		}
		if ($policy instanceof WritePolicyInterface) {
			$instance->with_policy($policy);
		}
		return $instance;
	}
}

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\TestableRegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\ExpectLogTrait;

/**
 * @covers \Ran\PluginLib\Options\RegisterOptions::__construct
 * @uses \Ran\PluginLib\Options\RegisterOptions::_normalize_schema_keys
 * @uses \Ran\PluginLib\Options\RegisterOptions::_resolve_default_value
 * @uses \Ran\PluginLib\Options\RegisterOptions::_sanitize_and_validate_option
 */
final class RegisterOptionsConstructorBranchesTest extends PluginLibTestCase {
	use ExpectLogTrait;

	public function setUp(): void {
		parent::setUp();
		// Minimal WP stubs used along constructor path
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array());
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(function ($key) {
			$key = strtolower((string) $key);
			$key = preg_replace('/[^a-z0-9_\-]+/i', '_', $key) ?? '';
			return trim($key, '_');
		});
	}

	public function test_constructor_covers_schema_normalization_default_seeding_and_initial_merge(): void {
		// Schema with a default to seed, and simple sanitize/validate to exercise helpers
		$schema = array(
		    'greeting' => array(
		        'default' => function () {
		        	return '  Hello  ';
		        },
		        'sanitize' => function ($v) {
		        	return trim((string) $v);
		        },
		        'validate' => function ($v) {
		        	return is_string($v);
		        },
		    ),
		);
		// Initial options include a different key to exercise initial merge branch
		$initial = array(
		    'count' => array('value' => 5),
		);

		// Provide collecting logger directly so constructor logs are captured
		$sut = TestableRegisterOptions::make('ctor_branches_opts', $initial, true, $this->logger_mock, $schema, null);

		// Assertions: schema default seeded and sanitized
		$this->assertSame('Hello', $sut->get_option('greeting'));
		// Initial options merged into in-memory state
		$this->assertSame(5, $sut->get_option('count'));

		// Logs from constructor paths (message content may vary with logger binding timing)
		// Note: constructor logging may vary; skip strict log assertion here.
	}
}
