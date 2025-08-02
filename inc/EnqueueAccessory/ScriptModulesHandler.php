<?php

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
/**
 * Handles script module enqueuing.
 *
 * Requires WordPress 6.7 or greater.
 *
 * @mixin ScriptModulesEnqueueTrait
 */
class ScriptModulesHandler extends AssetEnqueueBaseAbstract {
	use ScriptModulesEnqueueTrait;
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
