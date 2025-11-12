<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<input type="text" name="test" value="' . ($context['value'] ?? '') . '">',
	submits_data: true,
	component_type: 'input'
);
