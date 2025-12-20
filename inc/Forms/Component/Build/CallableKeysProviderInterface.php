<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Build;

use Ran\PluginLib\Forms\CallableRegistry;

interface CallableKeysProviderInterface {
	public static function register_callable_keys(CallableRegistry $registry): void;
}
