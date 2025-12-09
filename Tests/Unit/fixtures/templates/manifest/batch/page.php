<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<main class="batch-page">' . ($context['inner_html'] ?? '') . '</main>',
	component_type: 'layout_wrapper'
);
