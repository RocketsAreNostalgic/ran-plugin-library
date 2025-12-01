<?php

use Ran\PluginLib\Forms\Component\ComponentRenderResult;

return new ComponentRenderResult(
	markup: '<section class="batch-section">' . ($context['inner_html'] ?? '') . '</section>',
	component_type: 'layout_wrapper'
);
