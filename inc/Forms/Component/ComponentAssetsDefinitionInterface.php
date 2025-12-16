<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component;

use Ran\PluginLib\EnqueueAccessory\StyleDefinition;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;

interface ComponentAssetsDefinitionInterface {
	/**
	 * @return array{
	 *     scripts?: array<int, ScriptDefinition>,
	 *     styles?: array<int, StyleDefinition>,
	 *     requires_media?: bool,
	 *     repeatable?: bool
	 * }
	 */
	public static function get(): array;
}
