<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="modern-field">' . ($context['inner_html'] ?? '') . '</div>',
	component_type: 'layout_wrapper'
);
