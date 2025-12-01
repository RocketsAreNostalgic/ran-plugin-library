<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<div class="theme-page">' . ($context['inner_html'] ?? '') . '</div>',
	component_type: 'layout_wrapper'
);
