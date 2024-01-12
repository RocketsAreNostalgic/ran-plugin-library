<?php

// First we need to load the composer autoloader, so we can use WP Mock
require_once __DIR__ . '/../vendor/autoload.php';

use WP_Mock\Tools\TestCase as TestCase;

// Bootstrap WP_Mock to initialize built-in features
WP_Mock::Bootstrap();

/**
 *
 * @package Ran/PluginLib
 */
abstract class RanTestCase extends TestCase
{
	/**
	 * Scaffold WP_Mock setUp method.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function setUp(): void
	{
		\WP_Mock::setUp();
	}

	/**
	 * Scaffold WP_Mock tearDown method.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function tearDown(): void
	{
		\WP_Mock::tearDown();
	}
}
