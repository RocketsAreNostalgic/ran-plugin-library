<?php

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

/**
 * Handles media enqueuing.
 * Note: MediaEnqueueTrait does not use the EnqueueAssetTraitBase,
 * so it doesn't need the abstract method implementations.
 */
class MediaHandler extends AssetHandlerBase {
	use MediaEnqueueTrait;
}
