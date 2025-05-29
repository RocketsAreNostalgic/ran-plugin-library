<?php
/**
 * Test bootstrap file for Ran Plugin Lib.

 * @package Ran/PluginLib
 */

declare(strict_types = 1);

// First we need to load the composer autoloader, so we can use WP Mock.
require_once __DIR__ . '/../vendor/autoload.php';

use WP_Mock\Tools\TestCase;

// Bootstrap WP_Mock to initialize built-in features.
WP_Mock::Bootstrap();

/**
 * Base test case class for Ran Plugin Lib.

 * @package Ran/PluginLib
 */
abstract class RanTestCase extends TestCase {
	/**
	 * Scaffold WP_Mock setUp method.
	 *
	 * @throws \Exception If setUp fails.
	 */
	public function setUp(): void {
		\WP_Mock::setUp();
	}

	/**
	 * Scaffold WP_Mock tearDown method.
	 *
	 * @throws \Exception If tearDown fails.
	 */
	public function tearDown(): void {
		\WP_Mock::tearDown();
	}
}
