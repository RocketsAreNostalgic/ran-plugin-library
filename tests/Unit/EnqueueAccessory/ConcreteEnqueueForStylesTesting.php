<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract;
use Ran\PluginLib\Util\Logger;

/**
 * Concrete implementation of StylesEnqueueTrait for testing asset-related methods.
 */
class ConcreteEnqueueForStylesTesting extends AssetEnqueueBaseAbstract {
	public function __construct(ConfigInterface $config) {
		parent::__construct($config);
	}

	public function load(): void {
		// Minimal implementation for testing purposes.
	}

	// Mocked implementation for trait's dependency.
	protected function _add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
	}

	// Mocked implementation for trait's dependency.
	public function _enqueue_external_inline_styles(): void {
	}
}
