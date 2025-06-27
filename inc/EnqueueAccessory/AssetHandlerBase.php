<?php

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;

/**
 * Base handler for asset types.
 * Provides the concrete implementations needed by the traits.
 */
abstract class AssetHandlerBase extends EnqueueAssetBaseAbstract {
	public function __construct(ConfigInterface $config) {
		parent::__construct($config);
	}

	/**
	 * The load method is required by the base abstract class.
	 * In this architecture, the main Enqueue class handles the hooking, so this can be empty.
	 */
	public function load(): void {
		// This is intentionally left empty.
		// The main Enqueue class (e.g., EnqueueAdmin) is responsible for
		// hooking into WordPress and calling the enqueue() method.
	}
}
