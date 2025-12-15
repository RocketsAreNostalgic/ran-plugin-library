<?php
declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\FileUpload;

use Ran\PluginLib\Forms\Component\ComponentAssetsDefinitionInterface;
use Ran\PluginLib\EnqueueAccessory\StyleDefinition;

final class Assets implements ComponentAssetsDefinitionInterface {
	public static function get(): array {
		$styleSrc = \plugin_dir_url(__FILE__) . 'file-upload.css';

		return array(
			'scripts' => array(),
			'styles'  => array(
				StyleDefinition::from_array(array(
					'handle'  => 'ran-plugin-lib-file-upload',
					'src'     => $styleSrc,
					'deps'    => array(),
					'version' => null,
					'media'   => 'all',
				)),
			),
			'requires_media' => false,
			'repeatable'     => true,
		);
	}
}
