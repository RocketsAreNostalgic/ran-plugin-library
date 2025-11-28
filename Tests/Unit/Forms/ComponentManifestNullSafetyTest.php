<?php
/**
 * Test ComponentManifest null safety patterns.
 *
 * @package Ran\PluginLib\Tests\Unit\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use WP_Mock;
use Mockery;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;

class ComponentManifestNullSafetyTest extends PluginLibTestCase {
	use ExpectLogTrait;

	private ComponentLoader $loader;
	/** @var array<string,string> */
	private array $aliases = array();
	/** @var array<string,mixed> */
	private array $cachedMetadata = array();

	public function setUp(): void {
		parent::setUp();

		$this->aliases        = array();
		$this->cachedMetadata = array();

		$instance = $this;

		$this->loader = Mockery::mock(ComponentLoader::class);
		$this->loader->shouldReceive('register')->zeroOrMoreTimes();
		$this->loader->shouldReceive('aliases')->andReturnUsing(static function () use ($instance): array {
			return $instance->aliases;
		});
		$this->loader->shouldReceive('resolve_normalizer_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_builder_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_validator_class')->andReturn(null);
		$this->loader->shouldReceive('resolve_sanitizer_class')->andReturn(null);

		WP_Mock::userFunction('get_transient')->andReturnUsing(static function (string $key) use ($instance) {
			return $instance->cachedMetadata[$key] ?? false;
		});
		WP_Mock::userFunction('set_transient')->andReturnUsing(static function (string $key, mixed $value) use ($instance): bool {
			$instance->cachedMetadata[$key] = $value;
			return true;
		});
		WP_Mock::userFunction('delete_transient')->andReturnUsing(static function (string $key) use ($instance): bool {
			unset($instance->cachedMetadata[$key]);
			return true;
		});
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');
	}

	/**
	 * Test that create_normalizers skips invalid cached metadata and logs warnings.
	 */
	public function test_create_normalizers_logs_invalid_cached_metadata(): void {
		$this->aliases = array(
			'valid_component' => 'components/valid.php',
			'null_meta'       => 'components/null.php',
			'string_meta'     => 'components/string.php',
			'missing_keys'    => 'components/missing.php',
		);
		$this->cachedMetadata = array(
			'kepler_comp_meta_valid_component' => array('normalizer' => null, 'builder' => null, 'validator' => null),
			'kepler_comp_meta_null_meta'       => null,
			'kepler_comp_meta_string_meta'     => 'invalid',
			'kepler_comp_meta_missing_keys'    => array(),
		);

		$manifest = $this->createManifest();
		$this->resetLogs();

		$normalizers = $manifest->create_normalizers();

		self::assertSame(array(), $normalizers);
		$this->expectLog('warning', 'ComponentManifest: Skipping cached metadata (invalid format)', 1);
		$this->expectLog('warning', 'ComponentManifest: Skipping cached metadata (missing normalizer key)', 1);
	}

	/**
	 * Test that builder_factories logs and skips invalid cached metadata.
	 */
	public function test_builder_factories_logs_invalid_cached_metadata(): void {
		$this->aliases = array(
			'valid_component' => 'components/valid.php',
			'invalid_format'  => 'components/invalid.php',
			'missing_builder' => 'components/missing-builder.php',
		);
		$this->cachedMetadata = array(
			'kepler_comp_meta_valid_component' => array('normalizer' => null, 'builder' => null, 'validator' => null),
			'kepler_comp_meta_invalid_format'  => 123,
			'kepler_comp_meta_missing_builder' => array(),
		);

		$manifest = $this->createManifest();
		$this->resetLogs();

		$factories = $manifest->builder_factories();

		self::assertSame(array(), $factories);
		$this->expectLog('warning', 'ComponentManifest: Skipping cached metadata (invalid format)', 1);
		$this->expectLog('warning', 'ComponentManifest: Skipping cached metadata (missing builder key)', 1);
	}

	/**
	 * Test that validator_factories logs and skips invalid cached metadata.
	 */
	public function test_validator_factories_logs_invalid_cached_metadata(): void {
		$this->aliases = array(
			'valid_component'   => 'components/valid.php',
			'invalid_format'    => 'components/invalid.php',
			'missing_validator' => 'components/missing-validator.php',
		);
		$this->cachedMetadata = array(
			'kepler_comp_meta_valid_component'   => array('normalizer' => null, 'builder' => null, 'validator' => null),
			'kepler_comp_meta_invalid_format'    => array('normalizer' => null, 'builder' => null),
			'kepler_comp_meta_missing_validator' => array(),
		);

		$manifest = $this->createManifest();
		$this->resetLogs();

		$factories = $manifest->validator_factories();

		self::assertSame(array(), $factories);
		$this->expectLog('warning', 'ComponentManifest: Skipping cached metadata (missing validator key)', 2);
	}

	/**
	 * Test that empty component metadata results in empty factory collections without logs.
	 */
	public function test_empty_component_metadata_returns_empty_results_without_logs(): void {
		$this->aliases        = array();
		$this->cachedMetadata = array();

		$manifest = $this->createManifest();
		$this->resetLogs();

		self::assertSame(array(), $manifest->create_normalizers());
		self::assertSame(array(), $manifest->builder_factories());
		self::assertSame(array(), $manifest->validator_factories());
		self::assertSame(array(), $this->logger_mock->get_logs(), 'Expected no warnings when metadata is empty.');
	}

	private function createManifest(): ComponentManifest {
		return new ComponentManifest($this->loader, $this->logger_mock);
	}

	private function resetLogs(): void {
		$this->logger_mock->collected_logs = array();
	}
}
