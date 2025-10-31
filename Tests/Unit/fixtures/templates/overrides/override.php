<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="override">' . ($context['content'] ?? '') . '</div>',
	component_type: 'layout_wrapper'
);
