<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\AssetType;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract;

/**
 * Concrete implementation of EnqueueTrait for testing asset-related methods.
 */
class ConcreteEnqueueForTesting extends AssetEnqueueBaseAbstract {
	public function __construct(ConfigInterface $config) {
		parent::__construct($config);
	}

	public function load(): void {
		// Minimal implementation for testing purposes.
	}

	public function get_asset_url(string $path, ?AssetType $asset_type = null): ?string {
		return 'https://example.com/' . $path;
	}

	public function get_logger(): Logger {
		return $this->config->get_logger();
	}

	protected function _add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
		add_action($hook, $callback, $priority, $accepted_args);
	}
}
