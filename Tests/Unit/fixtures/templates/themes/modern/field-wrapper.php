<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="modern-field">' . ($context['component_html'] ?? '') . '</div>',
	component_type: 'layout_wrapper'
);
