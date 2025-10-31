<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="theme-field">' . ($context['component_html'] ?? '') . '</div>',
	component_type: 'layout_wrapper'
);
