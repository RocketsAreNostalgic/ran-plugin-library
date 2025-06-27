<?php

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

/**
 * Handles script enqueuing.
 *
 * @mixin ScriptsEnqueueTrait
 */
class ScriptsHandler extends AssetHandlerBase {
	use ScriptsEnqueueTrait;
}
