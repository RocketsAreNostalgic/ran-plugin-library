<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="complete.compact-field-wrapper">' . ($context['inner_html'] ?? '') . '</div>',
	component_type: 'layout_wrapper'
);
