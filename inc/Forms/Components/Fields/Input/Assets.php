<?php
declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Input;

use Ran\PluginLib\Forms\Component\ComponentAssetsDefinitionInterface;

final class Assets implements ComponentAssetsDefinitionInterface {
	public static function get(): array {
		return array(
			'repeatable' => true,
		);
	}
}
