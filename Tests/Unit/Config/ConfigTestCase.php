<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Util\CollectingLogger;
use RanTestCase; // Declared in test_bootstrap.php

/**
 * Base test case for Config suites that enforces CollectingLogger usage and
 * provides helpers to construct hydrated Config instances with injected logger.
 */
abstract class ConfigTestCase extends RanTestCase {
	/** @var CollectingLogger|null */
	protected ?CollectingLogger $logger_mock = null;

	public function setUp(): void {
		parent::setUp();
		$this->logger_mock = new CollectingLogger();
	}

	/**
	 * Build a hydrated Config (plugin) that returns the shared CollectingLogger.
	 */
	protected function configFromPluginFileWithLogger(string $pluginFile): Config {
		return Config::fromPluginFileWithLogger($pluginFile, $this->logger_mock);
	}

	/**
	 * Build a hydrated Config (theme) that returns the shared CollectingLogger.
	 */
	protected function configFromThemeDirWithLogger(string $stylesheetDir): Config {
		return Config::fromThemeDirWithLogger($stylesheetDir, $this->logger_mock);
	}
}


