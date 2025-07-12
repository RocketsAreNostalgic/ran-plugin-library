<?php

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;

/**
 * Handles media enqueuing.
 * Note: MediaEnqueueTrait does not use the AssetEnqueueBaseTrait,
 * so it doesn't need the abstract method implementations.
 */
class MediaHandler extends AssetEnqueueBaseAbstract {
	use MediaEnqueueTrait;
	public function __construct(ConfigInterface $config) {
		parent::__construct($config);
	}

	public function load(): void {
		// This load() method is intentionally empty
		// because this handler does not hook itself into WordPress.
		// The main consumer class (e.g. EnqueueAdmin) is responsible
		// for creating the add_action hook that will eventually call
		// the enqueue_assets() method on this handler.
	}
}
